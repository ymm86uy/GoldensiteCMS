<?php
// Read-only inventory. No application files are executed and no data is changed.
$root = dirname(__DIR__);
$report = ['php' => PHP_VERSION, 'files' => [], 'invalid_php' => [], 'bom' => [], 'missing_php_tag' => [], 'tables' => [], 'sensitive_operations' => []];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (preg_match('~^(vendor|storage|\.git)/~', $path) || $file->getExtension() !== 'php') continue;
    $source = file_get_contents($file->getPathname());
    $report['files'][] = $path;
    if (str_starts_with($source, "\xEF\xBB\xBF")) $report['bom'][] = $path;
    if (!str_contains($source, '<?php') && preg_match('~^(app|modules|routes|config|database)/~', $path) && !preg_match('~/[Vv]iews/~', $path)) {
        $report['missing_php_tag'][] = $path;
    }
    try { token_get_all($source, TOKEN_PARSE); }
    catch (ParseError $error) { $report['invalid_php'][$path] = $error->getMessage(); }
    preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s*\(/i', $source, $matches);
    foreach (array_unique($matches[1]) as $table) $report['tables'][$table][] = $path;
    foreach (explode("\n", $source) as $i => $line) {
        if (preg_match('/\b(?:eval|shell_exec|exec|system|passthru|unserialize|extractTo|move_uploaded_file|unlink|file_put_contents|curl_exec)\s*\(|DROP\s+TABLE|DISCARD\s+TABLESPACE/i', $line)) {
            $report['sensitive_operations'][] = ['file' => $path, 'line' => $i + 1, 'code' => trim($line)];
        }
    }
}
ksort($report['tables']);
if (in_array('--json', $argv, true)) echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
else {
    echo count($report['files']) . " archivos PHP; " . count($report['tables']) . " tablas declaradas.\n";
    echo 'Errores de sintaxis: ' . json_encode($report['invalid_php']) . "\n";
    echo 'BOM antes de PHP: ' . json_encode($report['bom']) . "\n";
    echo 'Archivos de código sin etiqueta PHP: ' . json_encode($report['missing_php_tag']) . "\n";
    echo count($report['sensitive_operations']) . " operaciones sensibles inventariadas.\n";
}
