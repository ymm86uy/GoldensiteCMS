<?php

require_once __DIR__.'/../app/Core/LegacyFormat.php';

if (PHP_SAPI !== 'cli' && \App\Core\LegacyFormat::updateInProgress(dirname(__DIR__))) {
    http_response_code(503);
    header('Cache-Control: no-store');
    header('Retry-After: 120');
    exit('GoldenSiteCMS se está actualizando. Vuelve a intentarlo en unos minutos.');
}

require_once __DIR__ . '/../app/bootstrap.php';
if (!\App\Core\Installer::installed()) {
    header('Location: /install.php');
    exit;
}

require_once __DIR__ . '/../routes/web.php';

$activityPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_string($activityPath) && !empty($_SESSION['user_id'])
    && !preg_match('~^/(?:admin|api|login|logout|register|registro|download)(?:/|$)~', $activityPath)
    && (int)($_SESSION['public_activity_logged_at'] ?? 0) <= time() - 60) {
    App\Core\Auth::logActivity();
    $_SESSION['public_activity_logged_at'] = time();
}

$router = App\Core\Router::getInstance();
$router->get('/admin/weather-time', [App\Controllers\WeatherTimeController::class, 'settings']);
$router->post('/admin/weather-time', [App\Controllers\WeatherTimeController::class, 'settings']);
$router->post('/weather-time/current', [App\Controllers\WeatherTimeController::class, 'current']);
$router->get('/admin/ads', [App\Controllers\AdsController::class, 'index']);
$router->post('/admin/ads', [App\Controllers\AdsController::class, 'index']);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && is_string($activityPath)
    && !preg_match('~^/(?:admin|api|login|logout|register|registro|account|mi-cuenta|weather-time|politica-cookies)(?:/|$)~', App\Core\AdminPath::normalize($activityPath))) {
    if (App\Core\FrontendEngine::isModuleActive(App\Core\WeatherTime::ID)) {
        $weatherWidget = App\Core\WeatherTime::widget();
        ob_start(static fn(string $html): string => App\Core\WeatherTime::decorate($html, $weatherWidget));
    }
    if (App\Core\FrontendEngine::isModuleActive(App\Core\Ads::ID)) {
        header('Cache-Control: no-store, private');
        $topAd = App\Core\Ads::render('top', $activityPath);
        $bottomAd = App\Core\Ads::render('bottom', $activityPath);
        ob_start(static fn(string $html): string => App\Core\Ads::decorate($html, $topAd, $bottomAd));
    }
}
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
