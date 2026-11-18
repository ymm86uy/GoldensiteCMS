<?php
namespace App\Core;
final class DatabaseBackup
{
    public static function write(string $path): void
    {
        $db=Database::getInstance()->getConnection(); $file=fopen($path,'xb');
        if (!$file) throw new \RuntimeException('No se pudo crear el respaldo privado.');
        chmod($path,0600);
        $buffered=$db->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $write=static function(string $value) use ($file): void { if (fwrite($file,$value)!==strlen($value)) throw new \RuntimeException('Escritura incompleta del respaldo.'); };
        try {
            $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $write('-- GoldenSiteCMS / SQL backup '.date('c')."\nSET FOREIGN_KEY_CHECKS=0;\n");
            foreach ($db->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
                $quoted='`'.str_replace('`','``',$table).'`'; $create=$db->query('SHOW CREATE TABLE '.$quoted)->fetch(\PDO::FETCH_NUM)[1];
                $write('DROP TABLE IF EXISTS '.$quoted.";\n".$create.";\n");
                $db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
                $rows=$db->query('SELECT * FROM '.$quoted);
                while ($row=$rows->fetch(\PDO::FETCH_ASSOC)) {
                    $keys=array_map(static fn($k)=>'`'.str_replace('`','``',$k).'`',array_keys($row));
                    $values=array_map(static fn($v)=>$v===null?'NULL':$db->quote((string)$v),array_values($row));
                    $write('INSERT INTO '.$quoted.' ('.implode(',',$keys).') VALUES ('.implode(',',$values).");\n");
                }
                $rows->closeCursor();
                $db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n"); fflush($file); $db->commit();
        } catch (\Throwable $e) { if (isset($rows)) $rows->closeCursor(); $db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered); if ($db->inTransaction()) $db->rollBack(); fclose($file); @unlink($path); throw $e; }
        fclose($file);
    }
    public static function directory(): string
    {
        $path=dirname(__DIR__,2).'/storage/backups';
        if (!is_dir($path) && !mkdir($path,0700,true)) throw new \RuntimeException('No se pudo preparar el almacén privado.');
        if (is_link($path)) throw new \RuntimeException('El almacén de respaldos no puede ser un enlace.');
        return $path;
    }
    public static function download(): void
    {
        $path=self::directory().'/.export-'.bin2hex(random_bytes(10)).'.sql'; self::write($path);
        try { header('Content-Type: application/sql; charset=utf-8'); header('Content-Disposition: attachment; filename="goldensite-backup-'.date('Ymd-His').'.sql"'); header('Cache-Control: no-store'); header('Content-Length: '.filesize($path)); readfile($path); }
        finally { unlink($path); }
    }
}
