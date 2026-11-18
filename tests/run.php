<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Csrf;
use App\Core\Environment;
use App\Core\SafeArchive;
use App\Core\SafeHttp;
use App\Core\Session;
use App\Core\Totp;
use App\Core\Uploader;

$checks = 0;
function verify(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
$root = dirname(__DIR__);
$directory = $root . '/storage/tests-' . bin2hex(random_bytes(5));
mkdir($directory, 0700, true);
session_save_path($directory);
Session::start();
$token = Csrf::generate();
verify(strlen($token) === 64 && Csrf::validate($token), 'CSRF válido');
verify(session_get_cookie_params()['httponly'] && session_get_cookie_params()['samesite'] === 'Lax', 'Cookies protegidas');
verify(!Csrf::validate('wrong') && !Csrf::validate(['x']) && !Csrf::validate(null), 'CSRF rechaza valores inválidos');
verify(Totp::counter('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59) === 1, 'Vector RFC 6238');
verify(Totp::counter('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '888123', 59) === null, 'No hay código 2FA de prueba');
$rateKey = 'test:' . bin2hex(random_bytes(16));
verify(App\Core\RateLimiter::consume($rateKey, 1, 60), 'Primer intento permitido');
session_regenerate_id(true);
verify(!App\Core\RateLimiter::consume($rateKey, 1, 60), 'Cambiar sesión no reinicia límites de intentos');
$signed = App\Core\PaywallEngine::generateSignedDownloadUrl(42, 60);
parse_str(parse_url($signed, PHP_URL_QUERY), $signedParams);
verify(App\Core\PaywallEngine::validateSignedUrl(42, (int) $signedParams['expires'], $signedParams['signature']), 'Firma de descarga válida');
verify(!App\Core\PaywallEngine::validateSignedUrl(43, (int) $signedParams['expires'], $signedParams['signature']), 'Firma impide cambiar el archivo');
foreach ([App\Controllers\BlogController::class, App\Controllers\Admin\DatingController::class, \Plugins\GoldensiteChat\ChatEngine::class, App\Core\PaywallEngine::class] as $class) verify(class_exists($class), 'Carga de módulo: ' . $class);
$qrSvg = App\Core\QrEncoder::getSvg('https://example.test/security', 250);
verify(str_contains($qrSvg, '<svg') && str_contains($qrSvg, 'viewBox='), 'QR SVG generado por codificador estándar');
if (class_exists('DOMDocument')) { $qrDocument = new DOMDocument(); verify($qrDocument->loadXML($qrSvg), 'QR es XML válido'); }
foreach (['../escape', '/etc/passwd', 'app/../../escape', 'app\\escape', 'C:/app/test', 'config/database.php', '.env', 'storage/keys/x', 'public/uploads/x.php', 'app/.hidden'] as $path) verify(!SafeArchive::pathAllowed($path), 'ZIP rechaza ' . $path);
verify(SafeArchive::pathAllowed('app/Core/Example.php'), 'ZIP permite código del proyecto');
foreach (['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.0.1', '169.254.169.254', '100.64.0.1', '::1', '::ffff:127.0.0.1', 'fc00::1'] as $ip) verify(!SafeHttp::isPublicIp($ip), 'SSRF rechaza ' . $ip);
verify(SafeHttp::isPublicIp('8.8.8.8'), 'IP pública válida');
try { new Uploader('../storage'); verify(false, 'Traversal de carga'); } catch (InvalidArgumentException $expected) { verify(true, 'Traversal de carga bloqueado'); }
verify((new Uploader())->upload(['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__, 'name' => 'x.png']) === null, 'Carga rechaza archivo local y MIME falso');
$envFile = $directory . '/env';
file_put_contents($envFile, "GOLDENSITE_TEST_QUOTED=\"a\\nb\\\"c\"\nGOLDENSITE_TEST_LITERAL='literal value'\n");
Environment::load($envFile);
verify(Environment::get('GOLDENSITE_TEST_QUOTED') === "a\nb\"c", 'Lectura segura del entorno');
verify(Environment::get('GOLDENSITE_TEST_LITERAL') === 'literal value', 'Lectura de comillas simples');
$archive = $directory . '/attack.zip';
$zip = new ZipArchive();
$zip->open($archive, ZipArchive::CREATE);
$zip->addFromString('app/ok.txt', 'valid');
$zip->addFromString('../escape.txt', 'attack');
$zip->close();
mkdir($directory . '/extract');
try { SafeArchive::apply($archive, $directory . '/extract'); verify(false, 'ZIP malicioso'); } catch (RuntimeException $expected) { verify(!file_exists($directory . '/extract/app/ok.txt'), 'ZIP se valida completo antes de escribir'); }
session_write_close();

$database = getenv('GOLDENSITE_TEST_DB');
if (!$database) {
    echo "OK: $checks comprobaciones. Integración omitida: configura GOLDENSITE_TEST_DB con una base dedicada.\n";
    exit;
}
if (!preg_match('/^goldensite_test_[a-z0-9_]+$/D', $database)) throw new RuntimeException('Las pruebas requieren una base exclusiva goldensite_test_*.');
putenv('DB_NAME=' . $database);
$db = App\Core\Database::getInstance()->getConnection();
$changes = App\Core\SchemaMigrator::migrate($db);
verify(App\Core\SchemaMigrator::migrate($db) === [], 'Migración idempotente');
$db->exec("INSERT INTO products (name, price) VALUES ('pricing_security_test', 12.34)");
$pricingId = (int) $db->lastInsertId();
verify(App\Core\CartPricing::total($db, [['id' => $pricingId, 'price' => 0.01, 'quantity' => 2]]) === '24.68', 'Checkout ignora precios manipulados o desactualizados');
try { App\Core\CartPricing::total($db, [['id' => $pricingId, 'quantity' => -1]]); verify(false, 'Cantidad negativa'); }
catch (InvalidArgumentException $expected) { verify(true, 'Cantidad negativa rechazada'); }
$db->prepare('DELETE FROM products WHERE id = ?')->execute([$pricingId]);
$db->exec("INSERT INTO goldensite_dating_profiles (username, wallet_balance) VALUES ('wallet_sender', 5.00), ('wallet_receiver', 0.00)");
$walletSender = (int) $db->lastInsertId();
$walletReceiver = $walletSender + 1;
$db->exec("INSERT INTO goldensite_dating_gifts (name, price, icon, status) VALUES ('security_test_gift', 4.00, 'gift', 1)");
$gift = (int) $db->lastInsertId();
App\Core\WalletService::sendGift($db, $walletSender, $walletReceiver, $gift, 'ignored');
verify((string) $db->query('SELECT wallet_balance FROM goldensite_dating_profiles WHERE id = ' . $walletSender)->fetchColumn() === '1.00', 'Regalos usan el precio del catálogo');
try { App\Core\WalletService::sendGift($db, $walletSender, $walletReceiver, $gift, 'ignored'); verify(false, 'Doble gasto'); }
catch (RuntimeException $expected) { verify((string) $db->query('SELECT wallet_balance FROM goldensite_dating_profiles WHERE id = ' . $walletSender)->fetchColumn() === '1.00', 'Doble gasto rechazado sin modificar saldo'); }
$db->prepare('DELETE FROM goldensite_dating_messages WHERE sender_id = ?')->execute([$walletSender]);
$db->prepare('DELETE FROM goldensite_dating_transactions WHERE user_id = ?')->execute([$walletSender]);
$db->prepare('DELETE FROM goldensite_dating_profiles WHERE id IN (?, ?)')->execute([$walletSender, $walletReceiver]);
$db->prepare('DELETE FROM goldensite_dating_gifts WHERE id = ?')->execute([$gift]);
$password = bin2hex(random_bytes(16));
$userName = 'test_' . bin2hex(random_bytes(5));
$insert = $db->prepare("INSERT INTO users (username, email, password, role, role_id) VALUES (?, ?, ?, 'admin', 1)");
$insert->execute([$userName, $userName . '@example.test', password_hash($password, PASSWORD_BCRYPT)]);
$id = (int) $db->lastInsertId();
// This server shares the source tree's Owner configuration, but uses a separate database.
if ($id === (int)(App\Core\OwnerAccess::configuration()['user_id'] ?? 0)) {
    $nextId = (int)$db->query('SELECT MAX(id) + 1 FROM users')->fetchColumn();
    $db->prepare('UPDATE users SET id=? WHERE id=?')->execute([$nextId,$id]);
    $id = $nextId;
}
$originalHash = $db->query('SELECT password FROM users WHERE id = ' . $id)->fetchColumn();
$cookie = $directory . '/cookies';
$port = 18081;
$address = 'http://127.0.0.1:' . $port;
$log = $directory . '/http.log';
$environment = getenv();
$environment['DB_NAME'] = $database;
$environment['RATE_LIMIT_NAMESPACE'] = bin2hex(random_bytes(16));
$testEnvironmentFile = $directory . '/database.env';
$testEnvironment = '';
foreach (['DB_HOST' => Environment::get('DB_HOST', '127.0.0.1'), 'DB_PORT' => Environment::get('DB_PORT', '3306'), 'DB_USER' => Environment::get('DB_USER', 'root'), 'DB_PASS' => Environment::get('DB_PASS'), 'DB_NAME' => $database] as $key => $value) {
    $testEnvironment .= $key . '=' . json_encode($value) . "\n";
}
file_put_contents($testEnvironmentFile, $testEnvironment);
$environment['GOLDENSITE_ENV_FILE'] = $testEnvironmentFile;
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/tools/dev-router.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $environment, ['bypass_shell' => true]);
if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar el servidor de pruebas.');
fclose($pipes[0]);
function request(string $path, ?array $form = null, ?array $json = null, ?string $csrf = null): array {
    global $address, $cookie;
    $curl = curl_init($address . $path);
    $headers = [];
    if ($csrf !== null) $headers[] = 'X-CSRF-Token: ' . $csrf;
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false];
    if ($form !== null) { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = http_build_query($form); }
    if ($json !== null) { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = json_encode($json); $headers[] = 'Content-Type: application/json'; }
    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $result = ['status' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'headers' => substr((string) $response, 0, $size), 'body' => substr((string) $response, $size)];
    curl_close($curl);
    return $result;
}
function token(array $response): string {
    if (!preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $response['body'], $match)) throw new RuntimeException('No se encontró token en la respuesta HTTP ' . $response['status']);
    return $match[1];
}
try {
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port, $number, $description, 0.1);
        if ($connection) { fclose($connection); break; }
        usleep(100000);
    }
    verify(request('/admin')['status'] === 302, 'Admin requiere autenticación');
    verify(request('/restore.php')['status'] === 302, 'Restaurador requiere autenticación');
    verify(request('/install.php')['status'] === 403, 'Instalador cerrado por defecto');
    verify(request('/api/save_page.php', null, ['title' => 'attack'])['status'] === 403, 'API impide escritura anónima');
    verify(request('/cache/926e27eecdbc7a18858b3798ba99bddd.html')['status'] === 404, 'Caché pública inaccesible');
    verify(request('/login', ['email' => $userName, 'password' => $password])['status'] === 403, 'Login sin CSRF rechazado');
    $login = request('/login');
    verify($login['status'] === 200, 'Login disponible');
    $csrf = token($login);
    verify($db->query('SELECT password FROM users WHERE id = ' . $id)->fetchColumn() === $originalHash, 'Mostrar login no modifica credenciales');
    $login = request('/login', ['csrf_token' => $csrf, 'email' => $userName, 'password' => $password]);
    verify($login['status'] === 302 && str_contains($login['headers'], 'Location: /admin'), 'Credenciales correctas');
    foreach (['/admin', '/admin/ai-builder', '/admin/app-builder', '/admin/waf', '/admin/messages', '/admin/themes', '/admin/themes/customize', '/admin/pages', '/admin/posts', '/admin/menus', '/admin/plugins', '/admin/settings', '/admin/activity-log'] as $path) {
        $response = request($path);
        verify($response['status'] === 200, 'Pantalla disponible: ' . $path . ' HTTP ' . $response['status']);
    }
    verify(request('/admin/owner')['status'] === 403, 'Otro administrador no puede abrir Owner');
    $csrf = token(request('/admin/pages'));
    verify(request('/admin/owner', ['action_update_prices' => 1])['status'] === 403, 'Owner sin CSRF rechazado');
    verify(request('/admin/settings?action=flush_cache')['status'] === 405, 'GET no purga caché');
    $slug = 'test-' . bin2hex(random_bytes(6));
    foreach (['Original', 'Updated'] as $title) {
        $existingPage=$db->query('SELECT id FROM goldensite_pages WHERE slug='.$db->quote($slug))->fetchColumn();
        verify(request('/admin/pages', ['csrf_token' => $csrf, 'action' => $existingPage?'save':'create','page_id'=>$existingPage?:0, 'title' => $title, 'slug' => $slug, 'content' => '<script>example()</script>', 'status' => 'draft'])['status'] === 302, 'Crear y actualizar página con contenido HTML');
    }
    $page = $db->query("SELECT title FROM goldensite_pages WHERE slug = " . $db->quote($slug))->fetchColumn();
    verify($page === 'Updated', 'Edición por identificador con consultas preparadas nativas');
    verify(request('/page/' . $slug)['status'] === 404, 'Borradores no publicados');
    $db->prepare("UPDATE goldensite_pages SET status = 'published',is_published=1 WHERE slug = ?")->execute([$slug]);
    $published = request('/page/' . $slug);
    verify($published['status'] === 200 && str_contains($published['body'], 'example()'), 'Ruta dinámica publica contenido existente');
    verify(str_contains(strtolower($published['headers']), 'cache-control: private, no-store'), 'Contenido privado no se guarda en caché pública');
    $api = request('/api/save_page.php', null, ['id' => 0, 'business_id' => 1, 'title' => 'Builder test', 'blocks' => [], 'global' => [], 'status' => 'published'], $csrf);
    $data = json_decode($api['body'], true);
    verify($api['status'] === 200 && ($data['id'] ?? 0) > 0, 'API guarda y devuelve identificador');
    verify(request('/logout')['status'] === 404, 'GET no cierra sesión');
    verify(request('/logout', ['csrf_token' => $csrf])['status'] === 302, 'Logout POST funciona');
    verify(request('/admin')['status'] === 302, 'Logout invalida sesión');
    // A real TOTP challenge must finish before any administrative access.
    $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    $db->prepare('UPDATE users SET totp_secret = ?, totp_last_counter = NULL WHERE id = ?')->execute([$secret, $id]);
    $csrf = token(request('/login'));
    $challenge = request('/login', ['csrf_token' => $csrf, 'email' => $userName, 'password' => $password]);
    verify(str_contains($challenge['headers'], 'Location: /login/2fa'), '2FA obligatorio antes de crear sesión autenticada');
    verify(request('/admin')['status'] === 302, 'Sesión pendiente no tiene permisos');
    $csrf = token(request('/login/2fa'));
    verify(request('/login/2fa', ['csrf_token' => $csrf, 'totp_code' => '888123'])['status'] === 302 && request('/admin')['status'] === 302, 'Código 2FA de prueba rechazado');
    $step = intdiv(time(), 30);
    $digest = hash_hmac('sha1', pack('N2', 0, $step), '12345678901234567890', true);
    $offset = ord(substr($digest, -1)) & 15;
    $number = unpack('N', substr($digest, $offset, 4))[1] & 0x7fffffff;
    $code = sprintf('%06d', $number % 1000000);
    verify(request('/login/2fa', ['csrf_token' => $csrf, 'totp_code' => $code])['status'] === 302 && request('/admin')['status'] === 200, 'Código TOTP real aceptado');
    $csrf = token(request('/admin/pages'));
    request('/logout', ['csrf_token' => $csrf]);
    $csrf = token(request('/login'));
    request('/login', ['csrf_token' => $csrf, 'email' => $userName, 'password' => $password]);
    $csrf = token(request('/login/2fa'));
    request('/login/2fa', ['csrf_token' => $csrf, 'totp_code' => $code]);
    verify(request('/admin')['status'] === 302, 'TOTP usado no se puede reutilizar');
    // Revoking a user invalidates a session on its next request.
    $db->prepare('UPDATE users SET totp_secret = NULL WHERE id = ?')->execute([$id]);
    $csrf = token(request('/login'));
    request('/login', ['csrf_token' => $csrf, 'email' => $userName, 'password' => $password]);
    $db->prepare("UPDATE users SET status = 'disabled' WHERE id = ?")->execute([$id]);
    verify(request('/admin')['status'] === 302, 'Desactivar usuario revoca su sesión');
    echo "OK: $checks comprobaciones, incluyendo integración HTTP y MySQL.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
}
