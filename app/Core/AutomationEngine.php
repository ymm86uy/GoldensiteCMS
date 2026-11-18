<?php
namespace App\Core;
final class AutomationEngine
{
 public const EVENTS=['post_published','page_published','user_registered','contact_received','form_submitted'];
 public static function trigger(string $event,array $payload=[]): void {
  if (!FrontendEngine::isModuleActive('automation-hub')||!in_array($event,self::EVENTS,true)) return;
  try {
   $db=Database::getInstance(); $eventId=bin2hex(random_bytes(16));
   foreach ($db->fetchAll('SELECT * FROM goldensite_automations WHERE trigger_event=? AND is_active=1 ORDER BY id LIMIT 200',[$event]) as $rule) {
    if (!self::validRule($rule)||!self::evaluateCondition($rule,$payload)) continue;
    if ($rule['action_type']==='record_event'&&(int)$rule['execution_delay']===0) self::log($rule['name'],'SUCCESS','Evento local registrado: '.$event);
    else { ModuleJobs::enqueue('automation-hub','automation',$eventId.':'.$rule['id'],['rule_id'=>(int)$rule['id'],'signature'=>self::signature($rule),'event_payload'=>$payload],time()+(int)$rule['execution_delay'],'automation:'.$rule['id']); self::log($rule['name'],'QUEUED','Acción preparada en la cola privada.'); }
   }
  } catch (\Throwable $e) { self::log('Goldensite Automation','FAILED','No se completó el registro de una tarea.'); }
 }
 private static function signature(array $rule): string { return hash('sha256',json_encode(array_intersect_key($rule,array_flip(['trigger_event','condition_field','condition_operator','condition_value','action_type','execution_delay','target_value'])),JSON_THROW_ON_ERROR)); }
 private static function evaluateCondition(array $rule,array $payload): bool {
  $field=$rule['condition_field']??''; if ($field==='') return true; $value=$payload[$field]??''; if (!is_scalar($value)&&$value!==null) return false;
  $value=mb_strtolower((string)$value); $target=mb_strtolower((string)($rule['condition_value']??''));
  return match($rule['condition_operator']??'equals') { 'equals'=>$value===$target,'contains'=>str_contains($value,$target),'greater_than'=>is_numeric($value)&&is_numeric($target)&&(float)$value>(float)$target,'not_empty'=>$value!=='',default=>false };
 }
 public static function executeQueued(array $data,?callable $transport=null): ?bool {
  if ($transport!==null&&PHP_SAPI!=='cli') throw new \LogicException('Transporte reservado a CLI.');
  $rule=Database::getInstance()->fetch('SELECT * FROM goldensite_automations WHERE id=?',[(int)($data['rule_id']??0)]);
  if (!$rule||!(int)$rule['is_active']||!self::validRule($rule)||!hash_equals(self::signature($rule),(string)($data['signature']??''))) return null;
  $payload=$data['event_payload']??[]; if (!is_array($payload)) return false;
  $target=$rule['target_value']; $action=$rule['action_type'];
  if ($action==='record_event') $success=true;
  elseif ($transport) $success=(bool)$transport($action,['target'=>$target,'payload'=>$payload]);
  elseif ($action==='send_email') $success=MailTransport::send($target,'Goldensite Automation · '.$rule['name'],json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
  else $success=SafeHttp::postJson($target,$payload);
  self::log($rule['name'],$success?'SUCCESS':'FAILED',$success?'Acción completada; el proveedor de correo, si corresponde, aceptó el mensaje.':'El proveedor no confirmó la operación.');
  return $success;
 }
 public static function validRule(array $rule): bool {
  $action=$rule['action_type']??''; $target=$rule['target_value']??''; $delay=filter_var($rule['execution_delay']??0,FILTER_VALIDATE_INT);
  if ($delay===false||$delay<0||$delay>604800||!in_array($rule['trigger_event']??'',self::EVENTS,true)||!in_array($rule['condition_operator']??'equals',['equals','contains','greater_than','not_empty'],true)) return false;
  if ($action==='record_event') return true;
  if ($action==='send_email') return filter_var($target,FILTER_VALIDATE_EMAIL)!==false;
  if ($action==='call_webhook') { try { EnterpriseOidc::url($target); return true; } catch (\InvalidArgumentException $e) { return false; } }
  return false;
 }
 public static function isProLicensed(): bool { return LicenseEngine::isLicenseActive('automation-hub'); }
 private static function log(string $name,string $state,string $details): void { try { Database::getInstance()->execute('INSERT INTO goldensite_automation_logs (automation_name,status,details) VALUES (?,?,?)',[$name,$state,$details]); } catch (\Throwable $e) {} }
}
