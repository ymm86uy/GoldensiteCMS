<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$db = \App\Core\Database::getInstance()->getConnection();
$report = ['database' => $db->query('SELECT DATABASE()')->fetchColumn(), 'tables' => []];
foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    $name = str_replace('`', '``', $table);
    try {
        $columns = $db->query("SHOW FULL COLUMNS FROM `$name`")->fetchAll(PDO::FETCH_ASSOC);
        $report['tables'][$table] = [
            'rows' => (int) $db->query("SELECT COUNT(*) FROM `$name`")->fetchColumn(),
            'columns' => $columns,
            'indexes' => $db->query("SHOW INDEX FROM `$name`")->fetchAll(PDO::FETCH_ASSOC),
        ];
    } catch (Throwable $error) {
        $report['tables'][$table] = ['error' => $error->getMessage()];
    }
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
