<?php
// Development only. Production must use public/ as its document root.
$public = realpath(dirname(__DIR__) . '/public');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(?:^|/)\.~', $path) || preg_match('~^/(cache|uploads)/.*\.(php[0-9]*|phtml|phar|html?|svg|js)(?:\.|$)~i', $path)) {
    http_response_code(404); exit;
}
$file = realpath($public . $path);
if ($file && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $public) . '/') && is_file($file)) {
    if (str_starts_with($path, '/cache/') || preg_match('/\.(bak|sql|ini|env|log|lock)$/i', $path)) { http_response_code(404); exit; }
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
        if (!in_array($path, ['/index.php', '/install.php', '/restore.php', '/api/save_page.php'], true)) { http_response_code(404); exit; }
        require $file;
        return true;
    }
    return false;
}
require $public . '/index.php';
