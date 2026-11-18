<?php
namespace App\Core;

final class SqlBackup
{
    public static function restore(\PDO $db, string $file): void
    {
        if (!is_file($file) || filesize($file) < 1 || filesize($file) > 52428800) throw new \RuntimeException('Respaldo inválido o demasiado grande.');
        $sql = file_get_contents($file);
        if ($sql === false || str_contains($sql, "\0")) throw new \RuntimeException('Respaldo SQL inválido.');
        $previous = (int) $db->query('SELECT @@FOREIGN_KEY_CHECKS')->fetchColumn();
        try {
            $db->exec('SET FOREIGN_KEY_CHECKS=0');
            // Let the SQL parser handle semicolons inside quoted values.
            $db->exec($sql);
        } finally { $db->exec('SET FOREIGN_KEY_CHECKS=' . $previous); }
    }
}
