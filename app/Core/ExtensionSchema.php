<?php
declare(strict_types=1);
namespace App\Core;

/** Additive storage for extensions. The signer supplies data, never statements. */
final class ExtensionSchema
{
    private const SQL_TYPES = [
        'string' => 'VARCHAR(255)',
        'text' => 'TEXT',
        'integer' => 'BIGINT',
        'flag' => 'TINYINT',
        'datetime' => 'DATETIME',
    ];

    public static function tableName(string $id, string $suffix): string
    {
        $name = 'gs_ext_'.str_replace('-', '_', $id).'__'.$suffix;
        if (!preg_match('/^addon-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $id)
            || !preg_match('/^[a-z][a-z0-9_]{0,23}$/D', $suffix) || strlen($name)>64) {
            throw new \InvalidArgumentException('Nombre de tabla de extensión inválido.');
        }
        return $name;
    }

    public static function parse(string $id, string $json): array
    {
        if (strlen($json)>16384) throw new \InvalidArgumentException('El esquema de extensión supera el límite.');
        $schema = json_decode($json, false, 6, JSON_THROW_ON_ERROR);
        if (!$schema instanceof \stdClass || count(get_object_vars($schema))!==2 || ($schema->version??null)!==1
            || !($schema->tables??null) instanceof \stdClass) throw new \InvalidArgumentException('Formato de esquema de extensión inválido.');
        $tables = get_object_vars($schema->tables);
        if (!$tables || count($tables)>8) throw new \InvalidArgumentException('El esquema admite entre una y ocho tablas.');
        $plan = [];
        foreach ($tables as $suffix=>$definition) {
            $name = self::tableName($id, (string)$suffix);
            $columns = $definition instanceof \stdClass ? get_object_vars($definition) : null;
            if ($columns===null || count($columns)>24) throw new \InvalidArgumentException('Columnas de extensión inválidas.');
            $plan[$name] = [];
            foreach ($columns as $column=>$type) {
                if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/D', (string)$column) || $column==='id'
                    || !is_string($type) || !isset(self::SQL_TYPES[$type])) throw new \InvalidArgumentException('Columna o tipo de extensión inválido.');
                $plan[$name][$column] = self::SQL_TYPES[$type];
            }
        }
        return $plan;
    }

    public static function apply(array $plan): void
    {
        if (!$plan) return;
        $db = Database::getInstance();
        $statements = [];
        foreach ($plan as $table=>$columns) {
            if (!preg_match('/^gs_ext_[a-z0-9_]{1,57}$/D', $table)) throw new \LogicException('Tabla de extensión fuera de su espacio.');
            $declared = [];
            foreach ($columns as $column=>$type) {
                if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $column) || !in_array($type,self::SQL_TYPES,true)) throw new \LogicException('Definición no validada.');
                $declared[$column] = "`$column` $type NULL";
            }
            $existing = $db->fetchAll('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            if (!$existing) {
                $parts = array_merge(['`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'], array_values($declared));
                $statements[] = "CREATE TABLE IF NOT EXISTS `$table` (".implode(', ', $parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
                continue;
            }
            $actual = array_column($existing, null, 'COLUMN_NAME');
            $id = $actual['id']??[];
            $idType = preg_replace('/\(\d+\)/', '', strtolower($id['COLUMN_TYPE']??''));
            if ($idType!=='bigint unsigned' || ($id['COLUMN_KEY']??null)!=='PRI' || ($id['EXTRA']??null)!=='auto_increment') {
                throw new \InvalidArgumentException('Una tabla de la extensión tiene una estructura incompatible.');
            }
            $add = [];
            foreach ($declared as $column=>$declaration) {
                if (!isset($actual[$column])) {
                    $add[] = 'ADD COLUMN '.$declaration;
                    continue;
                }
                $row = $actual[$column];
                $actualType = preg_replace('/^(bigint|tinyint)\(\d+\)$/', '$1', strtolower($row['COLUMN_TYPE']));
                if ($actualType!==strtolower($columns[$column]) || $row['IS_NULLABLE']!=='YES' || $row['EXTRA']!=='') {
                    throw new \InvalidArgumentException('El esquema de extensión solo permite añadir columnas compatibles.');
                }
            }
            if ($add) $statements[] = "ALTER TABLE `$table` ".implode(', ', $add);
        }
        foreach ($statements as $statement) $db->getConnection()->exec($statement);
    }
}
