<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
$package = $argv[1] ?? '';
$update = isset($argv[2]) ? realpath($argv[2]) : null;
if ($update === false || ($update !== null && !is_file($update.'.signature.txt'))) throw new RuntimeException('Indica un parche con su firma.');
$database = getenv('GOLDENSITE_TEST_DB');
if (!is_file($package) || !is_string($database) || !preg_match('/^goldensite_test_[a-z0-9_]+$/D', $database)) throw new RuntimeException('Indica un paquete ZIP y una base vacía GOLDENSITE_TEST_DB que empiece por goldensite_test_.');
putenv('DB_NAME='.$database);
putenv('GOLDENSITE_ENV_FILE='.__DIR__.'/no-site-environment');
$fixture = $root . '/storage/install-test-' . bin2hex(random_bytes(5));
mkdir($fixture, 0700, true);
$zip = new ZipArchive();
if ($zip->open($package) !== true) throw new RuntimeException('Paquete inválido.');
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = $zip->getNameIndex($i);
    if (str_contains($name, '..') || str_contains($name, '\\') || str_starts_with($name, '/') || str_contains($name, ':')) throw new RuntimeException('Ruta ZIP no permitida.');
}
$zip->extractTo($fixture);
$zip->close();
$config = ['db_host' => getenv('DB_HOST') ?: '127.0.0.1', 'db_port' => getenv('DB_PORT') ?: '3306', 'db_name' => $database, 'db_user' => getenv('DB_USER') ?: 'root', 'db_pass' => getenv('DB_PASS') ?: ''];
$db = new PDO('mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'] . ';dbname=' . $database . ';charset=utf8mb4', $config['db_user'], $config['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
if ($db->query('SHOW TABLES')->fetchAll()) throw new RuntimeException('La base de instalación de prueba debe estar vacía.');
$environment = getenv();
unset($environment['GOLDENSITE_INSTALL_TOKEN']);
$environment['OPENWEATHER_API_KEY'] = getenv('GOLDENSITE_TEST_NO_WEATHER_KEY')==='1'?'':'fixture-local-not-a-provider-key';
$environment['GOLDENSITE_ENV_FILE'] = $fixture . '/.env';
$environment['RATE_LIMIT_NAMESPACE'] = bin2hex(random_bytes(16));
foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_NAME', 'APP_KEY', 'APP_URL'] as $key) unset($environment[$key]);
$fixturePort = filter_var(getenv('GOLDENSITE_INSTALL_TEST_PORT') ?: '18084', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1024,'max_range'=>65535]]);
if ($fixturePort === false) throw new RuntimeException('Puerto de prueba inválido.');
$fixtureAddress = '127.0.0.1:'.$fixturePort;
$fixtureOrigin = 'http://'.$fixtureAddress;
$cookie = $fixture . '/storage/test.cookie';
$log = $fixture . '/storage/http-test.log';
$server = proc_open([PHP_BINARY, '-d', 'max_execution_time=120', '-S', $fixtureAddress, '-t', $fixture . '/public', $fixture . '/tools/dev-router.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $fixture, $environment, ['bypass_shell' => true]);
if (!is_resource($server)) throw new RuntimeException('No se pudo iniciar el servidor de instalación de prueba.');
fclose($pipes[0]);
$checks = 0;
function checkInstall(bool $condition, string $label): void { global $checks; $checks++; if (!$condition) throw new RuntimeException($label); }
function installRequest(string $path, ?array $form = null, array $headers = [], bool $multipart = false): array {
    global $cookie, $fixtureOrigin;
    $curl = curl_init($fixtureOrigin . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_COOKIEJAR => $cookie, CURLOPT_TIMEOUT => 135]);
    foreach ($headers as $index=>$header) if (stripos($header,'Cookie: ')===0) { curl_setopt($curl,CURLOPT_COOKIE,substr($header,8)); unset($headers[$index]); }
    if ($headers) curl_setopt($curl, CURLOPT_HTTPHEADER, array_values($headers));
    if ($form !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $multipart?$form:http_build_query($form)]);
    $response = curl_exec($curl);
    $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $result = ['status' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'headers' => substr((string) $response, 0, $size), 'body' => substr((string) $response, $size)];
    curl_close($curl);
    return $result;
}
function installCsrf(array $response): string {
    if (!preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $response['body'], $match)) throw new RuntimeException('No hay token en la respuesta HTTP ' . $response['status']);
    return $match[1];
}
try {
    $reviewZip = new ZipArchive(); $reviewZip->open($package);
    $privateLeak = false;
    $legacyEntries = [];
    for ($entry = 0; $entry < $reviewZip->numFiles; $entry++) {
        $name = $reviewZip->getNameIndex($entry);
        if (preg_match('~(?:^\.env$|^storage/(?!\.htaccess$).+|license-private\.pem|initial-access\.json|recovery-credentials\.json)~', $name)) { { $privateLeak = true; fwrite(STDERR, "Material privado en el paquete: ".$name.PHP_EOL); } fwrite(STDERR, "Ruta privada en el paquete: ".$name.PHP_EOL); }
        $contents = $reviewZip->getFromIndex($entry);
        if (stripos($name, App\Core\LegacyFormat::PREFIX) !== false
            || (is_string($contents) && stripos($contents, App\Core\LegacyFormat::PREFIX) !== false)) $legacyEntries[] = $name;
        if (is_string($contents) && preg_match('/-----BEGIN ((?:EC |RSA |ENCRYPTED )?PRIVATE KEY)-----\r?\n(?:[A-Za-z0-9+\/=]+\r?\n){2,}-----END \1-----/', $contents)) { $privateLeak = true; fwrite(STDERR, "Material privado en el paquete: ".$name.PHP_EOL); }
    }
    checkInstall(!$privateLeak, 'El paquete distribuye configuración o claves privadas.');
    checkInstall(!$legacyEntries, 'Identificador de marca anterior en el paquete: '.implode(', ', $legacyEntries));
    checkInstall($reviewZip->locateName('config/license-public.pem') !== false, 'Falta la identidad pública del emisor.');
    $packagePrices = json_decode($reviewZip->getFromName('config/module-prices.json') ?: '', true);
    $packageOffers=[];
    if (($signedCatalog=$reviewZip->getFromName('config/catalog-policy.nfx'))!==false) {
        $parts=explode('.',trim($signedCatalog));
        $signature=count($parts)===3?base64_decode(strtr($parts[2],'-_','+/'),true):false;
        checkInstall($signature!==false&&$parts[0]==='NFXC1'&&openssl_verify($parts[0].'.'.$parts[1],$signature,$reviewZip->getFromName('config/license-public.pem'),OPENSSL_ALGO_SHA256)===1,'La política comercial incluida no tiene firma válida.');
        $packageClaims=json_decode(base64_decode(strtr($parts[1],'-_','+/')),true); $packageOffers=$packageClaims['offers']??[];
    }
    $packagePaid=[];
    foreach (App\Core\ModuleCatalog::definitions() as $id=>$definition) {
        if ($id==='security-audit' && $reviewZip->locateName('app/Controllers/Admin/SecurityAuditController.php')===false) continue;
        if (isset($packageOffers[$id])?$packageOffers[$id]['tier']==='pro':$definition['is_pro']) $packagePaid[]=$id;
    }
    checkInstall(is_array($packagePrices)&&!array_diff($packagePaid,array_keys($packagePrices))&&!array_diff(array_keys($packagePrices),$packagePaid),'El paquete no incluye los precios públicos vigentes según las categorías Owner.');
    $reviewZip->close();
    for ($i = 0; $i < 30; $i++) { $socket = @fsockopen('127.0.0.1', $fixturePort, $errno, $description, 0.1); if ($socket) { fclose($socket); break; } usleep(100000); }
    checkInstall(installRequest('/install.php')['status'] === 403, 'El instalador debe permanecer cerrado sin preparación.');
    $prepare = proc_open([PHP_BINARY, $fixture . '/tools/prepare-install.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $preparePipes, $fixture, $environment, ['bypass_shell' => true]);
    fclose($preparePipes[0]);
    $prepareOutput = stream_get_contents($preparePipes[1]);
    $prepareError = stream_get_contents($preparePipes[2]);
    fclose($preparePipes[1]); fclose($preparePipes[2]);
    checkInstall(proc_close($prepare) === 0, 'No se pudo preparar el instalador: ' . $prepareError);
    $installToken = trim(file_get_contents($fixture . '/storage/install-token'));
    checkInstall(strlen($installToken) === 64, 'La clave de instalación debe ser criptográfica.');
    $wizard = installRequest('/install.php');
    checkInstall($wizard['status'] === 200, 'El asistente no se abre después de prepararlo.');
    $csrf = installCsrf($wizard);
    $password = 'Installed_' . bin2hex(random_bytes(12));
    $form = array_merge($config, ['install_token' => $installToken, 'csrf_token' => $csrf, 'admin_user' => 'chosen_admin', 'admin_email' => 'owner@example.test', 'admin_pass' => $password, 'site_title' => 'Installed Goldensite', 'site_url' => $fixtureOrigin]);
    checkInstall(installRequest('/install.php', array_merge($form, ['csrf_token' => 'invalid']))['status'] === 403, 'La instalación debe validar CSRF.');
    checkInstall(installRequest('/install.php', array_merge($form, ['install_token' => 'invalid']))['status'] === 403, 'La instalación debe validar su clave privada.');
    checkInstall(!$db->query('SHOW TABLES')->fetchAll(), 'Un intento no autorizado no puede crear tablas.');
    $invalid = installRequest('/install.php', array_merge($form, ['admin_pass' => 'short']));
    checkInstall(str_contains($invalid['body'], '12 y 72'), 'El formulario debe explicar una contraseña inválida.');
    checkInstall(!$db->query('SHOW TABLES')->fetchAll(), 'Datos inválidos no pueden dejar una instalación parcial.');
    // Simulate failure to write configuration, then resume with the same owner.
    mkdir($fixture . '/.env');
    $interrupted = installRequest('/install.php', $form);
    checkInstall(!str_contains($interrupted['body'], 'completada con éxito') && !is_file($fixture . '/storage/installed.lock'), 'Una escritura fallida no puede anunciar una instalación completa.');
    checkInstall((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'No se conservó la cuenta para reanudar la instalación pendiente.');
    checkInstall(!glob($fixture . '/.env.install-*'), 'La instalación fallida dejó credenciales temporales sin limpiar.');
    rmdir($fixture . '/.env');
    $result = installRequest('/install.php', $form);
    checkInstall($result['status'] === 200 && str_contains($result['body'], 'completada con éxito'), 'El asistente no completó la instalación. Consulta ' . $log);
    checkInstall(is_file($fixture . '/.env') && is_file($fixture . '/storage/installed.lock'), 'Falta configuración o bloqueo de instalación.');
    checkInstall(!is_file($fixture . '/storage/install-token'), 'La clave de un solo uso debe retirarse al finalizar.');
    $admin = $db->query('SELECT * FROM users')->fetchAll();
    checkInstall(count($admin) === 1 && $admin[0]['username'] === 'chosen_admin' && $admin[0]['email'] === 'owner@example.test', 'La cuenta debe corresponder a lo elegido por el propietario.');
    checkInstall($admin[0]['role'] === 'admin' && (int) $admin[0]['role_id'] === 1 && $admin[0]['status'] === 'active', 'Faltan permisos de administrador.');
    checkInstall(password_verify($password, $admin[0]['password']), 'La contraseña elegida no coincide.');
    checkInstall(!str_contains(file_get_contents($fixture . '/.env'), $password), 'La contraseña administradora no debe guardarse en texto plano.');
    checkInstall(installRequest('/install.php')['status'] === 403, 'El instalador debe quedar cerrado tras completar.');
    checkInstall(installRequest('/install.php', $form)['status'] === 403, 'Repetir la instalación no puede restablecer la cuenta.');
    if ($update !== null) {
        $hasSocial=$db->query("SHOW TABLES LIKE 'goldensite_social_echoes'")->fetchColumn();
        if ($hasSocial) {
            $db->prepare("INSERT INTO goldensite_social_echoes (user_id,title,body,region) VALUES (?,'Eco anterior al parche','Ecos conservados','UY')")->execute([(int)$admin[0]['id']]);
            $legacyEchoId=(int)$db->lastInsertId();
        }
        installRequest('/login',['csrf_token'=>installCsrf(installRequest('/login')),'email'=>'owner@example.test','password'=>$password]);
        if (getenv('GOLDENSITE_FORUM_CHANNEL_PACKAGE')) require __DIR__.'/forum-channel-seed.php';
        $token = installCsrf(installRequest('/admin/settings'));
        $wafFixture = '203.0.113.77';
        $db->prepare("INSERT INTO goldensite_waf_blacklist (ip_address,reason,expires_at) VALUES (?,'Update fixture',NULL)")->execute([$wafFixture]);
        $previousTier = $db->query("SELECT tier FROM goldensite_module_offers WHERE module_id='release-backup'")->fetchColumn();
        $db->exec("INSERT INTO goldensite_module_offers (module_id,tier,amount_cents,currency,period) VALUES ('release-backup','free',0,'USD','once') ON DUPLICATE KEY UPDATE tier='free'");
        $upload = ['csrf_token'=>$token,'current_password'=>$password,'update_signature'=>trim(file_get_contents($update.'.signature.txt')),'update_file'=>new CURLFile($update,'application/zip','update.zip')];
        checkInstall(installRequest('/admin/release-backup/apply-update',$upload,[],true)['status']===303,'El panel no recibió el ZIP.');
        $prepared = installRequest('/admin/backups');
        checkInstall(preg_match('/name="prepared_id" value="([a-f0-9]{32})"/', $prepared['body'],$job)===1, 'El panel no verificó la firma y preparó la actualización.');
        checkInstall(installRequest('/admin/release-backup/install-prepared',['csrf_token'=>$token,'current_password'=>$password,'prepared_id'=>$job[1]])['status']===303, 'No se ejecutó la instalación desde el panel.');
        $archive = new ZipArchive(); $archive->open($update);
        $manifest = json_decode($archive->getFromName(App\Core\ReleaseUpdate::MANIFEST), true, 8, JSON_THROW_ON_ERROR);
        $addsSocialLocation=$archive->locateName('modules/GoldensiteSocial/Location.php')!==false;
        $archive->close();
        checkInstall(trim(file_get_contents($fixture.'/release-version.txt'))===$manifest['version'] && is_file($fixture.'/app/Core/WeatherTime.php'), 'El panel no actualizó los archivos y la versión.');
        if ($hasSocial && $addsSocialLocation) {
            $legacyEcho=$db->query('SELECT region,zone_row,zone_col,image_key FROM goldensite_social_echoes WHERE id='.$legacyEchoId)->fetch();
            checkInstall($legacyEcho===['region'=>'UY','zone_row'=>null,'zone_col'=>null,'image_key'=>null],'La migración cambió la privacidad de un eco existente.');
        }
        if ($hasSocial) $db->exec('DELETE FROM goldensite_social_echoes WHERE id='.$legacyEchoId);
        checkInstall((int)$db->query("SELECT COUNT(*) FROM goldensite_waf_blacklist WHERE ip_address='203.0.113.77' AND expires_at IS NULL")->fetchColumn()===1,'La actualización perdió un bloqueo permanente.');
        $db->prepare('DELETE FROM goldensite_waf_blacklist WHERE ip_address=?')->execute([$wafFixture]);
        if ($previousTier === false) $db->exec("DELETE FROM goldensite_module_offers WHERE module_id='release-backup'");
        else $db->prepare("UPDATE goldensite_module_offers SET tier=? WHERE module_id='release-backup'")->execute([$previousTier]);
        installRequest('/logout',['csrf_token'=>$token]);
    }
    if (getenv('GOLDENSITE_TEST_CHAT_CONSOLIDATION')==='1') {
        require __DIR__.'/chat-consolidation.php';
        return;
    }
    if (getenv('GOLDENSITE_TEST_ACCESS_RECOVERY')==='1') {
        require __DIR__.'/edition-channel.php';
        require __DIR__.'/access-recovery.php';
        require __DIR__.'/hosting-diagnostics.php';
        require __DIR__.'/mail-health.php';
        return;
    }
    if (getenv('GOLDENSITE_TEST_EDITION_CHANNEL')==='1') {
        require __DIR__.'/edition-channel.php';
        return;
    }
    if (getenv('GOLDENSITE_TEST_EXTENSION_CHANNEL')==='1') {
        require __DIR__.'/extension-channel.php';
        return;
    }
    if (getenv('GOLDENSITE_FORUM_CHANNEL_PACKAGE')) {
        require __DIR__.'/forum-channel.php';
        return;
    }
    if (getenv('GOLDENSITE_TEST_COMMERCIAL_PUBLICATION')) {
        require __DIR__.'/commercial-publication.php';
        return;
    }
    if (getenv('GOLDENSITE_TEST_SECURITY_AUDIT')==='1') {
        require __DIR__.'/security-audit-module.php';
        return;
    }
    if (getenv('GOLDENSITE_TEST_EDITORIAL_WORKFLOW')==='1') {
        require __DIR__.'/editorial-workflow.php';
        return;
    }
    if (is_file($fixture.'/app/Core/HomePage.php')) {
        require __DIR__.'/home-publication.php';
        if (getenv('GOLDENSITE_TEST_HOME_PUBLICATION_ONLY')==='1') return;
    }
    if (getenv('GOLDENSITE_TEST_WAF_INTAKE')==='1') {
        require __DIR__.'/waf-intake.php';
        return;
    }
    if (is_file($fixture.'/app/Core/WeatherTime.php')) require __DIR__.'/weather-waf.php';
    if (is_file($fixture.'/app/Core/Ads.php')) require __DIR__.'/ads.php';
    if (is_file($fixture.'/app/Core/AccountRegistration.php')) require __DIR__.'/registration.php';
    if (is_file($fixture.'/public/assets/goldensite-registration.css')) require __DIR__.'/registration-theme.php';
    if (is_file($fixture.'/app/Core/EditorialHistory.php')) require __DIR__.'/editorial-history.php';
    if (is_file($fixture.'/app/Core/PasswordRecovery.php')) require __DIR__.'/password-recovery.php';
    $login = installRequest('/login');
    $csrf = installCsrf($login);
    installRequest('/login', ['csrf_token' => $csrf, 'email' => 'owner@example.test', 'password' => 'wrong']);
    $login = installRequest('/login');
    checkInstall(str_contains($login['body'], 'role="alert"'), 'El error de credenciales debe ser visible.');
    $login = installRequest('/login', ['csrf_token' => installCsrf($login), 'email' => 'owner@example.test', 'password' => $password]);
    checkInstall($login['status'] === 302 && str_contains($login['headers'], 'Location: /admin'), 'El administrador recién instalado no puede entrar.');
    $dashboardVersion=html_entity_decode(installRequest('/admin/dashboard')['body'],ENT_QUOTES|ENT_HTML5,'UTF-8');
    $editionLabel=trim(file_get_contents($fixture.'/release-edition.txt'))==='owner'?'Owner':'Comercial';
    $versionLabel=trim(file_get_contents($fixture.'/release-version.txt'));
    checkInstall(str_contains($dashboardVersion,'GoldenSiteCMS '.$editionLabel.' · Versión instalada '.$versionLabel),'El dashboard no indica la edición y versión instaladas.');
    $geoFixtureUser=(int)$admin[0]['id'];
    $fixtureSession=$db->prepare("INSERT INTO active_sessions (session_id,user_id,username,email,last_url,ip_address,user_agent,last_activity) VALUES (?,?,'Geo fixture','geo@example.test','/','8.8.8.8','',DATE_ADD(NOW(),INTERVAL 5 SECOND))");
    $geoSessionId=hash('sha256','geo-fixture-'.$fixture);
    $fixtureSession->execute([$geoSessionId,$geoFixtureUser]);
    $countryMap=json_decode(installRequest('/admin/dashboard?api=geo')['body'],true,512,JSON_THROW_ON_ERROR);
    checkInstall($countryMap['available'] && $countryMap['last_30']===1 && $countryMap['countries'][0]['code']==='US'
        && !str_contains(json_encode($countryMap),'8.8.8.8'),'El mapa de cuentas no deduplica o muestra información identificable.');
    $db->prepare('DELETE FROM active_sessions WHERE session_id=?')->execute([$geoSessionId]);
    $eventRef=bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO goldensite_waf_logs (ip_address, username, user_agent, method, target_url, vector, risk_score, request_id, rule_ids, disposition, matched_fields) VALUES ('8.8.8.8','Fixture','','GET','/fixture','Acceso',100,?,'NX-400','BLOCK','client')")->execute([$eventRef]);
    $wafMap=json_decode(installRequest('/admin/waf?api=geo')['body'],true,512,JSON_THROW_ON_ERROR);
    checkInstall($wafMap['available'] && $wafMap['events'][0]['country']['code']==='US'
        && $wafMap['events'][0]['disposition']==='BLOCK','El mapa WAF no localiza un evento registrado.');
    $db->prepare('DELETE FROM goldensite_waf_logs WHERE request_id=?')->execute([$eventRef]);
    $localLookup=new \App\Geo\CountryLookup();
    checkInstall($localLookup->country('127.0.0.1')===null && $localLookup->country('::ffff:8.8.8.8')['code']==='US','Ubicación incorrecta de IP local o IPv4 mapeada.');
    foreach (['/', '/admin', '/admin/messages', '/admin/ai-builder', '/admin/app-builder', '/admin/waf', '/admin/themes', '/admin/themes/customize', '/admin/pages', '/admin/posts', '/admin/menus', '/admin/plugins', '/admin/settings', '/admin/activity-log'] as $path) {
        $response = installRequest($path);
        $initialModule=null; foreach (App\Core\ModuleCatalog::definitions() as $definition) if ($path===$definition['url']) $initialModule=$definition['id'];
        $requiresInitialLicense=$initialModule&&(($packageOffers[$initialModule]['tier']??'')==='pro')&&!App\Core\ModuleCatalog::definitions()[$initialModule]['is_pro'];
        checkInstall($response['status'] === ($requiresInitialLicense?403:200), 'Pantalla de instalación nueva: ' . $path . ' HTTP ' . $response['status']);
    }
    $routeCheck = $fixture.'/storage/verify-routes.php';
    file_put_contents($routeCheck, <<<'PHP'
<?php
require dirname(__DIR__).'/app/bootstrap.php';
App\Core\Settings::set('joomla_catalog_enabled', '1');
try {
    require dirname(__DIR__).'/routes/web.php';
    $definitions = (new ReflectionProperty(App\Core\Router::class, 'routes'))->getValue($router);
    $seen = [];
    foreach ($definitions as $route) {
        $key = $route['method'].' '.$route['path'];
        if (isset($seen[$key])) throw new RuntimeException('Ruta duplicada: '.$key);
        $seen[$key] = true;
        $handler = $route['handler'];
        if ($handler instanceof Closure) continue;
        $method = new ReflectionMethod($handler[0], $handler[1]);
        if (!$method->isPublic()) throw new RuntimeException('Acción inaccesible: '.$key);
    }
    echo count($definitions);
} finally {
    App\Core\Settings::set('joomla_catalog_enabled', '0');
}
PHP);
    $routeProcess = proc_open([PHP_BINARY, $routeCheck], [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $routePipes, $fixture, $environment, ['bypass_shell'=>true]);
    if (!is_resource($routeProcess)) throw new RuntimeException('No se pudo iniciar la revisión de rutas.');
    fclose($routePipes[0]);
    $routesVerified = trim(stream_get_contents($routePipes[1]));
    $routeErrors = stream_get_contents($routePipes[2]);
    fclose($routePipes[1]);
    fclose($routePipes[2]);
    checkInstall(proc_close($routeProcess)===0 && ctype_digit($routesVerified) && (int)$routesVerified>100, 'Rutas inválidas: '.$routeErrors);
    unlink($routeCheck);
    echo "Rutas verificadas: $routesVerified.\n";
    require __DIR__.'/admin-deletion.php';
    if (is_file($fixture.'/modules/GoldensiteSocial/module.json')) {
        $socialStart=$checks;
        require __DIR__.'/social-network.php';
        echo 'Red social: '.($checks-$socialStart)." comprobaciones.\n";
        if (getenv('GOLDENSITE_TEST_SOCIAL_ONLY')==='1') return;
    }
    if (is_file($fixture.'/modules/GoldensiteForum/module.json')) {
        $forumStart=$checks;
        require __DIR__ . '/forum.php';
        echo 'Foro: '.($checks-$forumStart)." comprobaciones.\n";
        if (getenv('GOLDENSITE_TEST_FORUM_ONLY')==='1') return;
    }
    if (is_file($fixture.'/release-edition.txt') && trim(file_get_contents($fixture.'/release-edition.txt'))==='commercial') {
        require __DIR__ . '/commercial.php';
        echo "OK: $checks comprobaciones de instalación comercial.\n";
        return;
    }
    require __DIR__ . '/owner.php';
    require __DIR__ . '/joomla-online.php';
    require __DIR__ . '/modules.php';
    require __DIR__ . '/wordpress-wxr.php';
    require __DIR__ . '/console.php';
    require __DIR__ . '/expansion.php';
    require __DIR__ . '/module-review.php';
    require __DIR__ . '/module-professional.php';
    require __DIR__ . '/dating-downloads.php';
    require __DIR__ . '/marketplace.php';
    require __DIR__ . '/enterprise-sso.php';
    require __DIR__ . '/booking-support.php';
    require __DIR__ . '/academy-events-s3.php';
    preg_match_all("/^    '([a-z-]+)' => /m", file_get_contents($fixture . '/routes/modules.php'), $moduleMatches);
    $moduleFailures = [];
    foreach ($moduleMatches[1] as $module) {
        $response = installRequest('/admin/' . $module);
        $checks++;
        if (!in_array($response['status'], [200, 302, 403], true)) $moduleFailures[] = $module . ': HTTP ' . $response['status'];
    }
    checkInstall(!$moduleFailures, 'Errores al abrir módulos: ' . implode(', ', $moduleFailures) . '. Registro: ' . $log);
    $csrf = installCsrf(installRequest('/admin/pages'));
    $studioForm = installRequest('/admin/ai-builder');
    preg_match('/name="revision" value="([a-f0-9]{64})"/',$studioForm['body'],$studioRevision);
    checkInstall(isset($studioRevision[1]),'El estudio no presenta control de versión del borrador.');
    $studioGenerated = ['csrf_token'=>$csrf,'action'=>'generate','revision'=>$studioRevision[1],'name'=>'Goldensite Fixture',
        'headline'=>'Portada con varias líneas','description'=>"Primera línea\nSegunda línea",'cta'=>'Contacto','url'=>'/contacto',
        'template'=>'theme','benefits'=>"Claridad\nEficiencia"];
    checkInstall(installRequest('/admin/ai-builder',$studioGenerated)['status']===302,'El estudio no genera un borrador con texto multilínea.');
    checkInstall(installRequest('/admin/ai-builder',$studioGenerated)['status']===422,'Una revisión obsoleta sobrescribe el borrador.');
    $studioPreview = installRequest('/admin/ai-builder')['body'];
    checkInstall(str_contains(html_entity_decode($studioPreview,ENT_QUOTES,'UTF-8'),'Segunda línea'),'El borrador no conserva la descripción multilínea.');
    checkInstall(preg_match('/<iframe\b[^>]*\bsandbox\b/i',$studioPreview)===1 && !str_contains(html_entity_decode(installRequest('/')['body'],ENT_QUOTES,'UTF-8'),'Portada con varias líneas'),'La vista previa no está aislada o el borrador aparece en producción.');
    $pageSlug = 'installed-page-test';
    installRequest('/admin/pages', ['csrf_token' => $csrf, 'action' => 'create', 'title' => 'Created page', 'slug' => $pageSlug, 'content' => '<p>Published page marker</p>', 'status' => 'published']);
    checkInstall(str_contains(installRequest('/page/' . $pageSlug)['body'], 'Published page marker'), 'La página guardada no aparece publicada.');
    installRequest('/admin/posts', ['csrf_token' => $csrf, 'action' => 'create', 'title' => 'Published article marker', 'slug' => 'installed-article', 'content' => '<p>Published article content</p>', 'status' => 'published']);
    checkInstall(str_contains(installRequest('/blog')['body'], 'Published article marker'), 'El artículo del panel no aparece en el blog.');
    checkInstall(str_contains(installRequest('/blog/installed-article')['body'], 'Published article content'), 'El artículo guardado no se puede abrir.');
    $sitemap = installRequest('/sitemap.xml');
    checkInstall($sitemap['status'] === 200 && str_contains($sitemap['body'], '/blog/installed-article'), 'El mapa del sitio no incluye el contenido publicado.');
    installRequest('/admin/sitemap', ['csrf_token' => $csrf, 'action_save_sitemap' => 1, 'robots_content' => "User-agent: *\nDisallow: /private-test/"]);
    $robots = installRequest('/robots.txt');
    checkInstall($robots['status'] === 200 && str_contains($robots['body'], '/private-test/') && str_contains($robots['body'], $fixtureOrigin.'/sitemap.xml'), 'robots.txt no respeta los ajustes o el dominio de la instalación.');
    installRequest('/admin/settings', ['csrf_token' => $csrf, 'site_title' => 'Changed site title']);
    checkInstall($db->query("SELECT setting_value FROM settings WHERE setting_key = 'site_title'")->fetchColumn() === 'Changed site title', 'Los ajustes no se guardaron.');
    $backup = installRequest('/admin/settings?action=export_db', ['csrf_token' => $csrf]);
    checkInstall($backup['status'] === 200 && str_contains($backup['headers'], 'application/sql') && str_contains($backup['body'], 'CREATE TABLE'), 'El botón de exportación no devuelve un respaldo SQL.');
    installRequest('/admin/ai-builder', ['csrf_token' => $csrf, 'action' => 'publish_layout', 'current_password' => $password, 'html_code' => '<!doctype html><html><head><title>Installed Layout</title></head><body><h1>Installed layout marker</h1></body></html>', 'business_type' => 'generic', 'app_name' => 'InstalledApp']);
    checkInstall(str_contains(installRequest('/')['body'], 'Installed layout marker'), 'El diseño publicado no se muestra en la portada.');
    installRequest('/admin/ai-builder', ['csrf_token' => $csrf, 'action' => 'disable_ai_frontend','current_password'=>$password]);
    checkInstall(!str_contains(installRequest('/')['body'], 'Installed layout marker'), 'Desactivar el diseño no restaura la portada base.');
    $search = json_decode(installRequest('/api/search?q=Published')['body'], true);
    checkInstall(($search['success'] ?? false) && count($search['data'] ?? []) >= 1, 'La búsqueda pública no encuentra el contenido publicado.');
    $blogApi = json_decode(installRequest('/api/blog/posts')['body'], true);
    checkInstall(($blogApi['success'] ?? false) && in_array('installed-article',array_column($blogApi['posts']??[],'slug'),true), 'La paginación del blog no usa los artículos publicados.');
    $contact = installRequest('/contacto');
    checkInstall($contact['status'] === 200, 'El formulario de contacto no abre.');
    $contactNonce=''; preg_match('/name="contact_nonce" value="([a-f0-9]+)"/', $contact['body'], $contactNonceMatch); $contactNonce=$contactNonceMatch[1]??'';
    $contact = installRequest('/contacto', ['contact_nonce'=>$contactNonce,'contact_consent'=>'1','csrf_token' => installCsrf($contact), 'name' => 'Install test visitor', 'email' => 'visitor@example.test', 'subject' => 'Install test message', 'message' => 'Contact request persisted in the test database.']);
    checkInstall($contact['status'] === 303 && (int) $db->query('SELECT COUNT(*) FROM goldensite_messages')->fetchColumn() === 1, 'El contacto no llega al panel de mensajes.');
    installRequest('/admin/gallery/save', ['csrf_token' => $csrf, 'title' => 'Installed gallery', 'images' => ["https://example.test/first.png\nhttps://example.test/second.png"], 'layout_type' => 'masonry', 'animation_fx' => 'zoom-fade']);
    $images = json_decode($db->query('SELECT images_json FROM goldensite_galleries ORDER BY id DESC LIMIT 1')->fetchColumn(), true);
    checkInstall(count($images) === 2, 'La galería no guarda las imágenes introducidas en líneas separadas.');
    $galleryId = $db->query('SELECT id FROM goldensite_galleries ORDER BY id DESC LIMIT 1')->fetchColumn();
    installRequest('/admin/pages', ['csrf_token' => $csrf, 'action' => 'create', 'title' => 'Gallery page', 'slug' => 'installed-gallery', 'content' => '[goldensite_gallery id="' . $galleryId . '"]', 'status' => 'published']);
    checkInstall(str_contains(installRequest('/page/installed-gallery')['body'], 'data-image="https://example.test/first.png"'), 'La galería activada con licencia no aparece en el sitio público.');
    installRequest('/admin/plugins', ['csrf_token' => $csrf, 'action_toggle_module' => 1, 'module_id' => 'gdpr-banner', 'toggle_state' => 1]);
    checkInstall(str_contains(installRequest('/')['body'], 'id="goldensite-gdpr-banner"'), 'Activar el aviso de cookies no lo muestra en la web.');
    $newPassword = 'Updated_' . bin2hex(random_bytes(12));
    $changed = installRequest('/admin/users/reset-password', ['csrf_token' => $csrf, 'user_id' => $admin[0]['id'], 'current_password' => $password, 'new_password' => $newPassword]);
    checkInstall($changed['status'] === 302 && str_contains($changed['headers'], 'Location: /login'), 'Cambiar la contraseña propia no cierra la sesión correctamente.');
    $login = installRequest('/login');
    checkInstall(str_contains($login['body'], 'Contraseña actualizada'), 'El cambio de contraseña no muestra confirmación.');
    $login = installRequest('/login', ['csrf_token' => installCsrf($login), 'email' => 'owner@example.test', 'password' => $newPassword]);
    checkInstall($login['status'] === 302 && str_contains($login['headers'], 'Location: /admin'), 'La nueva contraseña del panel no permite volver a entrar.');
    $csrf = installCsrf(installRequest('/admin/pages'));
    installRequest('/admin/settings', ['csrf_token' => $csrf, 'maintenance_mode' => '1']);
    installRequest('/logout', ['csrf_token' => $csrf]);
    checkInstall(installRequest('/')['status'] === 503, 'El modo mantenimiento no protege el acceso público.');
    $login = installRequest('/login');
    checkInstall($login['status'] === 200, 'Mantenimiento impide entrar al administrador.');
    installRequest('/login', ['csrf_token' => installCsrf($login), 'email' => 'owner@example.test', 'password' => $newPassword]);
    $csrf = installCsrf(installRequest('/admin/pages'));
    installRequest('/admin/settings', ['csrf_token' => $csrf, 'maintenance_mode' => '0']);
    installRequest('/logout', ['csrf_token' => $csrf]);
    checkInstall(installRequest('/')['status'] === 200, 'No se puede desactivar el mantenimiento desde el panel.');
    checkInstall(!preg_match('/PHP (?:Warning|Fatal error)|PDOException|Deprecated:/', file_get_contents($log)), 'Hay errores o avisos en el registro de la instalación de prueba: ' . $log);
    echo "OK: $checks comprobaciones de instalación nueva desde el ZIP, permisos y acceso administrador.\n";
} finally { proc_terminate($server); proc_close($server); }


