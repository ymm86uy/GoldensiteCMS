<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database,ModuleCapabilities,Settings};
final class SiteDoctorController {
 public function index(): void {
  Auth::requireAdmin(); header('Cache-Control: no-store, private'); $db=Database::getInstance(); $notice=null;
  if ($_SERVER['REQUEST_METHOD']==='POST') { \App\Core\RequestGuard::post(); try {
   if (!\App\Core\RateLimiter::consume('module-job-action:'.Auth::id(),10,300)) throw new \InvalidArgumentException('Espera antes de repetir la operación.'); ConsoleView::password(); $id=ConsoleView::field($_POST,'job_id',32); $action=ConsoleView::field($_POST,'job_action',20);
   $job=$db->fetch('SELECT state,attempts FROM goldensite_module_jobs WHERE id=?',[$id]);
   if (!$job) throw new \InvalidArgumentException('Tarea inexistente.');
   if ($action==='retry'&&in_array($job['state'],['failed','uncertain'],true)) {
    if ($job['state']==='uncertain'&&($_POST['duplicate_risk']??'')!=='1') throw new \InvalidArgumentException('Confirma que revisaste la posible entrega anterior.');
    $db->execute("UPDATE goldensite_module_jobs SET state='queued',attempts=0,run_at=UNIX_TIMESTAMP(),lease_token=NULL,claimed_at=NULL,finished_at=NULL WHERE id=? AND state=?",[$id,$job['state']]);
   } elseif ($action==='cancel'&&in_array($job['state'],['queued','retry','failed','uncertain'],true)) $db->execute("UPDATE goldensite_module_jobs SET state='cancelled',finished_at=NOW() WHERE id=? AND state=?",[$id,$job['state']]);
   else throw new \InvalidArgumentException('La tarea no admite esta operación en su estado actual.');
   if ((int)$db->getConnection()->query('SELECT ROW_COUNT()')->fetchColumn()!==1) throw new \InvalidArgumentException('La tarea cambió de estado. Recarga para revisarla.');
   ConsoleView::audit('module_job_'.$action,$id.' / '.$job['state'].' / intentos anteriores: '.$job['attempts']); header('Location: /admin/site-health'); return;
  } catch (\InvalidArgumentException $e) { http_response_code(422); $notice=$e->getMessage(); } }
  $databaseOk=(int)$db->getConnection()->query('SELECT 1')->fetchColumn()===1;
  $root=dirname(__DIR__,3); $checks=[['name'=>'Base de datos','ok'=>$databaseOk,'detail'=>'Consulta PDO de comprobación.'],['name'=>'Almacenamiento privado','ok'=>is_writable($root.'/storage'),'detail'=>'Permisos locales de escritura.'],['name'=>'Medios públicos','ok'=>is_writable($root.'/public/uploads'),'detail'=>'Permisos de la biblioteca de medios.'],['name'=>'HTTPS en esta petición','ok'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'detail'=>'HTTP local genera una advertencia; en producción configura TLS en el servidor.']];
  foreach (['pdo_mysql','mbstring','openssl','curl','zip','dom','gd'] as $extension) $checks[]=['name'=>'PHP · '.$extension,'ok'=>extension_loaded($extension),'detail'=>'Disponibilidad local de la extensión.'];
  $checks[]=['name'=>'Versión de PHP','ok'=>(PHP_VERSION_ID>=80427&&PHP_VERSION_ID<80500)||(PHP_VERSION_ID>=80512&&PHP_VERSION_ID<80600),'detail'=>'Versión observada: '.PHP_VERSION];
  $modules=ModuleCapabilities::report(); $jobs=$db->fetchAll('SELECT module_id,state,COUNT(*) count FROM goldensite_module_jobs GROUP BY module_id,state');
  $backup=\App\Core\RecoveryReport::latestBackup(); $recovery=\App\Core\RecoveryReport::verified();
  $maintenanceSeen=(int)Settings::get('maintenance_worker_seen','0');
  $checks[]=['name'=>'Trabajador de mantenimiento','ok'=>$maintenanceSeen>0 && time()-$maintenanceSeen<86400,
      'detail'=>$maintenanceSeen>0?'Última ejecución completada · '.gmdate('Y-m-d H:i',$maintenanceSeen).' UTC':'Pendiente de la primera ejecución de tools/maintenance.php.'];
  if (($_GET['export']??'')==='json') { header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="goldensite-diagnostics.json"'); echo json_encode(['generated_at'=>gmdate('c'),'php'=>PHP_VERSION,'checks'=>$checks,'modules'=>$modules,'jobs'=>$jobs,'last_backup'=>$backup,'last_recovery_drill'=>$recovery],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR); return; }
  $smtpConfigured=Settings::get('smtp_host')!=='' && filter_var(Settings::get('admin_email'),FILTER_VALIDATE_EMAIL)
      && (Settings::get('smtp_user')==='' || Settings::get('smtp_pass')!=='');
  $workerSeen=(int)Settings::get('module_worker_seen');
  ConsoleView::render('module_health',['checks'=>$checks,'modules'=>$modules,'jobs'=>$jobs,'notice'=>$notice,'smtpConfigured'=>$smtpConfigured,'workerSeen'=>$workerSeen,'backup'=>$backup,'recovery'=>$recovery,'recentJobs'=>$db->fetchAll('SELECT id,module_id,kind,state,attempts FROM goldensite_module_jobs ORDER BY created_at DESC,id DESC LIMIT 30')],'Goldensite Site Health & Doctor');
 }
}
