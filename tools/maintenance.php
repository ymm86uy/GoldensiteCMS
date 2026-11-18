<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
use App\Core\{Database,DatabaseBackup,Settings};
$lock=fopen(dirname(__DIR__).'/storage/maintenance.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit("Otro mantenimiento está en ejecución.\n");
try {
    $db=Database::getInstance()->getConnection(); $days=max(7,min(90,(int)Settings::get('waf_retention_days','30')));
    $db->exec('DELETE FROM goldensite_waf_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL '.$days.' DAY)');
    $db->exec('DELETE FROM goldensite_waf_blacklist WHERE expires_at <= DATE_SUB(NOW(),INTERVAL 30 DAY)');
    $db->exec('DELETE FROM goldensite_password_resets WHERE expires_at <= UNIX_TIMESTAMP()');
    $db->exec("DELETE FROM goldensite_module_jobs WHERE kind='password_reset' AND state<>'processing' AND created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)");
    $db->exec('DELETE FROM goldensite_revoked_sessions WHERE expires_at <= NOW()');
    if (class_exists(\Modules\GoldensiteSocial\Store::class)) \Modules\GoldensiteSocial\Store::prune();
    $published=\App\Core\EditorialWorkflow::publishDue();
    if ($published>0) echo "Publicaciones programadas completadas: $published.\n";
    $frequency=Settings::get('backup_schedule_freq','off'); $period=['daily'=>86400,'weekly'=>604800][$frequency]??null;
    $last=(int)Settings::get('backup_last_run','0');
    if ($period && time()-$last >= $period) {
        $path=DatabaseBackup::directory().'/scheduled-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
        DatabaseBackup::write($path); Settings::writeMany(['backup_last_run'=>(string)time(),'backup_last_file'=>basename($path)]);
        echo "Respaldo SQL privado completado.\n";
    }
    Settings::set('maintenance_worker_seen',(string)time()); echo "Mantenimiento completado.\n";
} finally { flock($lock,LOCK_UN); fclose($lock); }
