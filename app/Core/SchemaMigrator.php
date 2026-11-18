<?php
namespace App\Core;

use PDO;

final class SchemaMigrator
{
    public static function withModuleSchemas(string $sql): string
    {
        foreach (['GoldensiteForum'=>'goldensite_forum_categories','GoldensiteSocial'=>'goldensite_social_echoes'] as $module=>$table) {
            $path=dirname(__DIR__,2).'/modules/'.$module.'/Migrations/install.sql';
            if (!str_contains($sql,'CREATE TABLE IF NOT EXISTS '.$table) && is_file($path)) $sql.="\n".file_get_contents($path);
        }
        return $sql;
    }

    public static function migrate(PDO $db, ?string $schema = null): array
    {
        $sql = $schema ?? file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        if ($schema===null) $sql=self::withModuleSchemas($sql);
        preg_match_all('/CREATE TABLE IF NOT EXISTS (\w+)\s*\((.*?)\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4[^;]*;/s', $sql, $statements, PREG_SET_ORDER);
        if ($schema !== null && !$statements) throw new \RuntimeException('El esquema de actualización no contiene tablas compatibles.');
        $changes = [];
        foreach ($statements as $statement) {
            $table = $statement[1];
            $db->exec($statement[0]);
            $columns = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
            preg_match_all('/^\s*`?(\w+)`?\s+((?:INT|BIGINT|SMALLINT|TINYINT|VARCHAR|CHAR|TEXT|LONGTEXT|MEDIUMTEXT|DATE|TIME|DATETIME|TIMESTAMP|DECIMAL|ENUM|FLOAT|DOUBLE|JSON)\b[^\r\n]*)/mi', $statement[2], $definitions, PREG_SET_ORDER);
            foreach ($definitions as $column) {
                if (in_array($column[1], $columns, true)) continue;
                $definition = rtrim(trim($column[2]), ',');
                if (str_contains($definition, 'AUTO_INCREMENT') || str_contains($definition, 'PRIMARY KEY')) {
                    throw new \RuntimeException("La tabla $table requiere revisión manual de su clave primaria.");
                }
                // Legacy rows cannot be assigned invented mandatory values.
                if (stripos($definition, 'DEFAULT') === false) $definition = str_ireplace('NOT NULL', 'NULL', $definition);
                $definition = preg_replace('/\s+UNIQUE\b/i', '', $definition);
                $db->exec("ALTER TABLE `$table` ADD COLUMN `{$column[1]}` $definition");
                $changes[] = "$table.{$column[1]}";
            }
        }
        if ($schema !== null) return $changes;
        if (SessionSchema::ensure($db)) $changes[] = 'active_sessions.unique session identifier';
        $db->exec("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'user'");
        $db->exec("ALTER TABLE users ALTER COLUMN role_id SET DEFAULT 2");
        $settingsColumns = $db->query('SHOW COLUMNS FROM settings')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($settingsColumns as $column) {
            if ($column['Field'] === 'setting_value' && strtolower($column['Type']) !== 'longtext') {
                $db->exec('ALTER TABLE settings MODIFY COLUMN setting_value LONGTEXT NULL');
                $changes[] = 'settings.setting_value capacity';
            }
        }
        foreach (['goldensite_chat_messages' => ['idx_chat_channel_id', 'channel, id'], 'goldensite_analytics_hits' => ['idx_analytics_created', 'created_at'], 'goldensite_licenses' => ['idx_license_module_status', 'module_id, status'], 'goldensite_waf_logs' => ['idx_waf_action_created', 'disposition, created_at']] as $table => [$name, $columns]) {
            $indexes = $db->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!in_array($name, array_column($indexes, 'Key_name'), true)) { $db->exec("CREATE INDEX `$name` ON `$table` ($columns)"); $changes[] = "$table.$name"; }
        }
        return $changes;
    }
}
