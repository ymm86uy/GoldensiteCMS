<?php
namespace App\Core;
final class Settings
{
    private static ?array $values = null;
    public static function reset(): void { self::$values = null; }
    public static function all(): array
    {
        if (self::$values === null) {
            self::$values = [];
            foreach (Database::getInstance()->fetchAll('SELECT setting_key, setting_value FROM settings') as $row) self::$values[$row['setting_key']] = (string)($row['setting_value'] ?? '');
        }
        return self::$values;
    }
    public static function get(string $key, string $default = ''): string
    {
        try { $value = self::all()[$key] ?? $default; }
        catch (\Throwable $e) { error_log('Settings unavailable: ' . $e->getMessage()); return $default; }
        return $key === 'active_theme' && !ThemeCatalog::valid($value) ? 'dark-cyber' : $value;
    }
    public static function set(string $key, string $value): bool { self::writeMany([$key => $value]); return true; }
    public static function writeMany(array $values): void
    {
        foreach ($values as $key => $value) if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_]{0,99}$/D', $key)) throw new \InvalidArgumentException('Configuración inválida.');
        $db = Database::getInstance()->getConnection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($values as $key => $value) $stmt->execute([$key, $value]);
            if ($ownsTransaction) $db->commit();
            self::reset();
        } catch (\Throwable $e) { if ($ownsTransaction && $db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
