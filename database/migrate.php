<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    $db = \App\Core\Database::getInstance()->getConnection();
    $changes = \App\Core\SchemaMigrator::migrate($db);
    echo 'Migración aditiva completada. Columnas añadidas: ' . count($changes) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Migración detenida sin eliminar tablas: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
