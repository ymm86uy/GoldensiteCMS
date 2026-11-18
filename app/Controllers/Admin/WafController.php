<?php
namespace App\Controllers\Admin;
use App\Core\{AdminPath,Auth,Csrf,Database,LicenseService,RequestGuard,Settings};
use App\Security\{Clamd,HeuristicAntivirus,IpRange,UploadAntivirus,WafAccess,WafCustomRules,WafGateway,WafPolicy};
final class WafController
{
    public function index(): void
    {
        Auth::requireAdmin(); $db=Database::getInstance(); $status=null; $scanResult=null;
        $wafUrl = AdminPath::url('/admin/waf');
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET' && ($_GET['api']??'')==='geo') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(\App\Geo\ActivityMap::waf(),JSON_THROW_ON_ERROR);
            return;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                $action=LicenseService::field($_POST,'action',30);
                if ($action==='save_policy') {
                    $mode=LicenseService::field($_POST,'waf_mode',10); $limit=LicenseService::field($_POST,'waf_request_limit',4); $retention=LicenseService::field($_POST,'waf_retention_days',2);
                    if (!in_array($mode,['block','monitor'],true) || !ctype_digit($limit) || (int)$limit<60 || (int)$limit>2000 || !ctype_digit($retention) || (int)$retention<7 || (int)$retention>90) throw new \InvalidArgumentException('Revisa el modo, el límite de 60 a 2000 solicitudes y la retención de 7 a 90 días.');
                    Settings::writeMany(['waf_mode'=>$mode,'waf_request_limit'=>$limit,'waf_retention_days'=>$retention]); $message='Política de seguridad guardada y aplicada.';
                } elseif ($action==='save_custom_rule') {
                    $field=static fn(string $name,int $limit): string=>LicenseService::field($_POST,$name,$limit);
                    $id=WafCustomRules::save($field('rule_id',14),$field('label',80),$field('path',160),$field('scope',6),$field('method',7),$field('mode',7));
                    ActivityLogController::log('waf_custom_rule_saved',$id);
                    $message='Regla guardada. Identificador '.$id.'.';
                } elseif (in_array($action,['toggle_custom_rule','remove_custom_rule'],true)) {
                    $id=LicenseService::field($_POST,'rule_id',14);
                    if ($action==='toggle_custom_rule') WafCustomRules::toggle($id);
                    else WafCustomRules::remove($id);
                    ActivityLogController::log('waf_'.$action,$id);
                    $message='Regla actualizada.';
                } elseif ($action==='ban_ip') {
                    $ip=LicenseService::field($_POST,'ip_to_ban',45); $reason=LicenseService::field($_POST,'reason',255); $duration=LicenseService::field($_POST,'duration',6)?:'0';
                    if (!IpRange::valid($ip) || $reason==='' || !in_array($duration,['900','3600','86400','0'],true)) throw new \InvalidArgumentException('Introduce una IP o red CIDR válida, un motivo y una duración admitida.');
                    if (!str_contains($ip,'/')) $ip=WafAccess::address($ip);
                    $remote=$_SERVER['REMOTE_ADDR']??'';
                    if (IpRange::contains($ip,$remote) || IpRange::contains($ip,WafAccess::address($remote))) throw new \InvalidArgumentException('Ese bloqueo incluiría tu conexión actual. Usa una red distinta para evitar perder acceso.');
                    $expires=$duration==='0'?null:$db->fetch('SELECT DATE_ADD(NOW(), INTERVAL '.(int)$duration.' SECOND) AS deadline')['deadline'];
                    $db->execute('INSERT INTO goldensite_waf_blacklist (ip_address, username, reason, expires_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason=VALUES(reason), expires_at=VALUES(expires_at), username=VALUES(username)',[$ip,Auth::user()['username'],$reason,$expires]); $message='Regla de acceso aplicada.';
                    ActivityLogController::log('waf_access_rule_saved',$ip.' · '.($expires??'sin vencimiento'));
                } elseif ($action==='unban_ip') {
                    $ip=LicenseService::field($_POST,'ip_to_unban',45); if (!IpRange::valid($ip)) throw new \InvalidArgumentException('Regla de acceso inválida.');
                    $delete=$db->getConnection()->prepare('DELETE FROM goldensite_waf_blacklist WHERE ip_address = ? AND (expires_at IS NULL OR expires_at>NOW())');
                    $delete->execute([$ip]);
                    $message='Esa dirección o red ya no tiene esa regla activa.';
                    if ($delete->rowCount() === 1) {
                        ActivityLogController::log('waf_access_rule_removed',$ip);
                        $message='Bloqueo retirado para '.$ip.'. Una nueva detección grave puede volver a bloquearlo.';
                    }
                } elseif ($action==='scan_file') {
                    $file=$_FILES['file_to_scan']??[];
                    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new \InvalidArgumentException('Selecciona un archivo de hasta 20 MB recibido mediante el formulario.');
                    $name=basename($file['name']); $scanResult=HeuristicAntivirus::scanFile($file['tmp_name'],$name);
                    if ($scanResult['status'] !== 'success') {
                        $reason = $scanResult['reasons'][0];
                        $scanResult = null;
                        throw new \InvalidArgumentException($reason);
                    }
                    $message='Análisis heurístico completado. Consulta las señales en el resultado.';
                    $status=['type'=>'success','msg'=>$message];
                } else throw new \InvalidArgumentException('Acción de seguridad desconocida.');
                if ($scanResult===null) { $_SESSION['waf_notice']=['type'=>'success','msg'=>$message]; header('Location: '.$wafUrl); return; }
            } catch (\InvalidArgumentException $error) { http_response_code(422); $status=['type'=>'danger','msg'=>$error->getMessage()]; }
            catch (\Throwable $error) { error_log('WAF administration: '.$error->getMessage()); http_response_code(500); $status=['type'=>'danger','msg'=>'No se pudo completar la operación. Revisa el registro del servidor.']; }
        }
        $config=WafGateway::configuration();
        $filter=is_string($_GET['rule']??null)?substr($_GET['rule'],0,14):''; $disposition=is_string($_GET['disposition']??null)?substr($_GET['disposition'],0,10):'';
        if ($filter!=='' && !isset(WafPolicy::rules()[$filter]) && !preg_match('/^NX-M[0-9A-F]{10}$/D',$filter)) $filter='';
        if (!in_array($disposition,['','BLOCK','MONITOR','LEGACY'],true)) $disposition='';
        $where=[]; $params=[];
        if ($filter!=='') { $where[]='FIND_IN_SET(?, rule_ids)'; $params[]=$filter; }
        if ($disposition!=='') { $where[]='disposition = ?'; $params[]=$disposition; }
        $sql=$where?' WHERE '.implode(' AND ',$where):'';
        $page=max(1,min(1000,(int)($_GET['page']??1))); $offset=($page-1)*25;
        $total=(int)$db->fetch('SELECT COUNT(*) AS total FROM goldensite_waf_logs'.$sql,$params)['total'];
        $logs=$db->fetchAll('SELECT id, ip_address, method, target_url, vector, risk_score, request_id, rule_ids, disposition, matched_fields, created_at FROM goldensite_waf_logs'.$sql.' ORDER BY id DESC LIMIT 25 OFFSET '.$offset,$params);
        $active='(expires_at IS NULL OR expires_at>NOW())';
        $accessTotal=(int)$db->fetch("SELECT COUNT(*) AS total FROM goldensite_waf_blacklist WHERE $active")['total'];
        $accessPage=max(1,min(max(1,(int)ceil($accessTotal/100)),(int)($_GET['access_page']??1)));
        $accessOffset=($accessPage-1)*100;
        $blacklist=$db->fetchAll("SELECT ip_address, username, reason, expires_at, created_at FROM goldensite_waf_blacklist WHERE $active ORDER BY id DESC LIMIT 100 OFFSET $accessOffset");
        $expiredRules=$db->fetchAll('SELECT ip_address,reason,expires_at FROM goldensite_waf_blacklist WHERE expires_at <= NOW() ORDER BY expires_at DESC LIMIT 25');
        $metrics=$db->fetch("SELECT COUNT(*) AS events, COALESCE(SUM(disposition='BLOCK'),0) AS blocked, COALESCE(SUM(disposition='MONITOR'),0) AS observed, COUNT(DISTINCT ip_address) AS sources FROM goldensite_waf_logs WHERE created_at>=NOW()-INTERVAL 24 HOUR");
        if (($_GET['api']??'')==='realtime_logs') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['status'=>'success','logs'=>$logs,'blacklist'=>$blacklist,'metrics'=>$metrics],JSON_THROW_ON_ERROR); return; }
        if (($_GET['export']??'')==='csv') {
            header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="goldensite-security-events.csv"');
            $out=fopen('php://output','w'); fputcsv($out,['Fecha','Referencia','IP','Método','Ruta','Reglas','Acción','Riesgo'],',','"','');
            foreach ($db->fetchAll('SELECT created_at, request_id, ip_address, method, target_url, rule_ids, disposition, risk_score FROM goldensite_waf_logs'.$sql.' ORDER BY id DESC LIMIT 5000',$params) as $row) { $cells=array_map(static fn($v)=>preg_match('/^[=+@-]/',(string)$v)?"'".$v:(string)$v,array_values($row)); fputcsv($out,$cells,',','"',''); } fclose($out); return;
        }
        $accessByOrigin=[];
        $countryByOrigin=[];
        $countryLookup=new \App\Geo\CountryLookup();
        $origins=array_values(array_unique(array_column($logs,'ip_address')));
        $activeRules=[];
        if ($origins) {
            $slots=implode(',',array_fill(0,count($origins),'?'));
            $activeRules=$db->fetchAll("SELECT ip_address FROM goldensite_waf_blacklist WHERE $active AND (ip_address IN ($slots)
                OR ip_address LIKE '%/%' OR (ip_address LIKE '%:%' AND COALESCE(username,'')<>?))", [...$origins,WafAccess::AUTOMATIC]);
        }
        foreach ($origins as $origin) {
            $accessByOrigin[$origin]=array_filter($activeRules,static fn(array $rule): bool=>IpRange::contains($rule['ip_address'],$origin));
            $countryByOrigin[$origin]=$countryLookup->country($origin);
        }
        $uploadPolicy=UploadAntivirus::policy();
        $antivirusVersion=$uploadPolicy==='required'?Clamd::version():null;
        $antivirusLabel=match($uploadPolicy) {
            'required'=>$antivirusVersion!==null?'CLAMAV ACTIVO':'CLAMAV NO DISPONIBLE · SUBIDAS BLOQUEADAS',
            'provider'=>'PROTECCIÓN DEL PROVEEDOR · SIN VERIFICAR',
            'disabled'=>'SUBIDAS DESHABILITADAS',
            'invalid'=>'POLÍTICA INVÁLIDA · SUBIDAS BLOQUEADAS',
            default=>'SIN ESCANEO AUTOMÁTICO',
        };
        $quarantine=$db->fetchAll('SELECT file_name, file_hash, risk_score, risk_level, created_at FROM goldensite_quarantine ORDER BY id DESC LIMIT 8');
        $status??=$_SESSION['waf_notice']??null; unset($_SESSION['waf_notice']);
        $manualRules=WafCustomRules::all();
        $title='Centro de seguridad · Goldensite'; $csrfToken=Csrf::generate(); ob_start(); require dirname(__DIR__,3).'/views/admin/waf.php'; $content=ob_get_clean(); require dirname(__DIR__,3).'/views/layouts/admin.php';
    }
}
