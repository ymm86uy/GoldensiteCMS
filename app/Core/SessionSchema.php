<?php
namespace App\Core;
final class SessionSchema
{
    public static function ensure(\PDO $db): bool
    {
        $indexes=$db->query('SHOW INDEX FROM active_sessions')->fetchAll(\PDO::FETCH_ASSOC); $keys=[];
        foreach ($indexes as $index) if ((int)$index['Non_unique']===0) $keys[$index['Key_name']][(int)$index['Seq_in_index']]=$index['Column_name'];
        foreach ($keys as $columns) { ksort($columns); if (array_values($columns)===['session_id']) return false; }
        $duplicates=(int)$db->query('SELECT COUNT(*) FROM (SELECT session_id FROM active_sessions GROUP BY session_id HAVING COUNT(*)>1) duplicate_sessions')->fetchColumn();
        if (!$duplicates) { $db->exec('CREATE UNIQUE INDEX idx_session_identifier ON active_sessions (session_id)'); return true; }
        $directory=dirname(__DIR__,2).'/storage/schema-backups';
        if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new \RuntimeException('No se pudo respaldar el historial de sesiones.');
        $archive=$directory.'/sessions-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.ndjson';
        $file=fopen($archive,'x');
        if (!$file) throw new \RuntimeException('No se pudo crear la copia de sesiones.');
        chmod($archive,0600);
        try {
            $rows=$db->query('SELECT * FROM active_sessions');
            while ($row=$rows->fetch(\PDO::FETCH_ASSOC)) { $line=json_encode($row,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE)."\n"; if (fwrite($file,$line)!==strlen($line)) throw new \RuntimeException('Copia de sesiones incompleta.'); }
            $rows->closeCursor(); fflush($file);
        } finally { fclose($file); }
        $suffix=bin2hex(random_bytes(6)); $staging='nx_session_stage_'.$suffix; $old='nx_session_old_'.$suffix; $swapped=false;
        try {
            $db->exec("CREATE TABLE `$staging` LIKE active_sessions");
            $db->exec("CREATE UNIQUE INDEX idx_session_identifier ON `$staging` (session_id)");
            $db->exec("INSERT IGNORE INTO `$staging` SELECT * FROM active_sessions ORDER BY last_activity DESC");
            $expected=(int)$db->query('SELECT COUNT(DISTINCT session_id) FROM active_sessions')->fetchColumn();
            if ((int)$db->query("SELECT COUNT(*) FROM `$staging`")->fetchColumn()!==$expected) throw new \RuntimeException('La consolidación de sesiones no coincide con los identificadores existentes.');
            $db->exec("RENAME TABLE active_sessions TO `$old`, `$staging` TO active_sessions"); $swapped=true;
            $db->exec("DROP TABLE `$old`");
        } finally { if (!$swapped) $db->exec("DROP TABLE IF EXISTS `$staging`"); }
        return true;
    }
}
