<?php

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\PageController;

use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\OwnerController;
use App\Controllers\Admin\PluginController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\WafController;
use App\Controllers\Admin\ThemeController;
use App\Controllers\Admin\ThemeCustomizerController;
use App\Controllers\Admin\MenuController;
use App\Controllers\Admin\MessagesController;
use App\Controllers\Admin\AiBuilderController;
use App\Controllers\Admin\AppBuilderController;
use App\Controllers\Admin\ActivityLogController;

$router = Router::getInstance();

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
foreach (['get','post'] as $method) {
    $router->$method('/login/forgot',[\App\Controllers\PasswordRecoveryController::class,'forgot']);
    $router->$method('/login/reset',[\App\Controllers\PasswordRecoveryController::class,'reset']);
}
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/login/2fa', [AuthController::class, 'show2fa']);
$router->post('/login/2fa', [AuthController::class, 'verify2fa']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/registro', [AuthController::class, 'showRegister']);
$router->post('/registro', [AuthController::class, 'register']);
$router->get('/politica-cookies', [\Modules\GoldensiteGdprBanner\GdprModule::class, 'policy']);
$router->post('/politica-cookies', [\Modules\GoldensiteGdprBanner\GdprModule::class, 'saveConsent']);
$router->get('/mi-cuenta', [\App\Controllers\AccountController::class, 'index']);

$router->get('/admin', [DashboardController::class, 'index']);
$router->get('/admin/dashboard', [DashboardController::class, 'index']);
$router->post('/admin/dashboard', [DashboardController::class, 'index']);

$router->get('/admin/ai-builder', [\App\Controllers\Admin\StudioController::class, 'index']);
$router->post('/admin/ai-builder', [\App\Controllers\Admin\StudioController::class, 'index']);

$router->get('/admin/app-builder', [AppBuilderController::class, 'index']);
$router->post('/admin/app-builder', [AppBuilderController::class, 'index']);

$router->get('/admin/waf', [WafController::class, 'index']);
$router->post('/admin/waf', [WafController::class, 'index']);

$router->get('/admin/messages', [MessagesController::class, 'index']);
$router->post('/admin/messages', [MessagesController::class, 'index']);
$router->get('/admin/themes', [ThemeController::class, 'index']);
$router->post('/admin/themes', [ThemeController::class, 'index']);
$router->get('/admin/themes/customize', [ThemeCustomizerController::class, 'index']);
$router->post('/admin/themes/customize', [ThemeCustomizerController::class, 'index']);
$router->get('/admin/pages', [\App\Controllers\Admin\PageStudioController::class, 'index']);
$router->post('/admin/pages', [\App\Controllers\Admin\PageStudioController::class, 'index']);
$router->get('/admin/pages/home', [\App\Controllers\Admin\PageStudioController::class, 'home']);
$router->post('/admin/pages/home', [\App\Controllers\Admin\PageStudioController::class, 'home']);
$router->get('/admin/posts', [\App\Controllers\Admin\PostStudioController::class, 'index']);
$router->post('/admin/posts', [\App\Controllers\Admin\PostStudioController::class, 'index']);
if (\App\Core\ProductEdition::current()==='owner') {
    $router->get('/admin/owner', [OwnerController::class, 'index']);
    $router->post('/admin/owner', [OwnerController::class, 'index']);
}
$router->get('/admin/menus', [\App\Controllers\Admin\NavigationController::class, 'index']);
$router->post('/admin/menus', [\App\Controllers\Admin\NavigationController::class, 'index']);
$router->get('/admin/plugins', [PluginController::class, 'index']);
$router->post('/admin/plugins', [PluginController::class, 'index']);
$router->get('/extension-catalog/index.json',[\App\Controllers\ExtensionController::class,'feed']);
$router->get('/extension-catalog/package/{hash}',[\App\Controllers\ExtensionController::class,'package']);
$router->get('/extensions/{id}/assets/{name}',[\App\Controllers\ExtensionController::class,'asset']);
foreach (['get','post'] as $extensionMethod) {
    $router->$extensionMethod('/extensions/{id}',[\App\Controllers\ExtensionController::class,'page']);
    $router->$extensionMethod('/admin/plugins/{id}',[\App\Controllers\ExtensionController::class,'admin']);
}
$router->get('/admin/settings', [SettingsController::class, 'index']);
$router->post('/admin/settings', [SettingsController::class, 'save']);
$router->post('/admin/settings/save', [SettingsController::class, 'save']);
$router->get('/admin/activity-log', [\App\Controllers\Admin\ActivityAuditController::class, 'index']);
$router->post('/admin/activity-log', [\App\Controllers\Admin\ActivityAuditController::class, 'index']);

$router->get('/api/v1/manifest', [AiBuilderController::class, 'apiManifest']);
$router->get('/api/v1/data', [AiBuilderController::class, 'apiData']);

$router->get('/admin/typography', [\App\Controllers\Admin\TypographyController::class, 'index']);
$router->post('/admin/typography', [\App\Controllers\Admin\TypographyController::class, 'index']);
$router->post('/admin/users/manage', [\App\Controllers\Admin\UsersController::class, 'save']);
$router->post('/language', static function(): void { \App\Core\RequestGuard::post(); $lang=$_POST['language']??''; if (!is_string($lang)||!in_array($lang,['es','en','pt'],true)) { http_response_code(422); exit('Idioma inválido.'); } setcookie('goldensite_language',$lang,['expires'=>time()+31536000,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax']); $back=$_POST['return_to']??'/'; if (!is_string($back)||!\App\Core\PublicRuntime::internal($back)) $back='/'; header('Location: '.$back); });
foreach (['deploy-manager'=>\App\Controllers\Admin\DeployController::class,'identity-access'=>\App\Controllers\Admin\IdentityController::class,'form-builder'=>\App\Controllers\Admin\FormBuilderController::class] as $path=>$controller) { $router->get('/admin/'.$path,[$controller,'index']); $router->post('/admin/'.$path,[$controller,'index']); }
$router->get('/forms/{slug}',[\App\Controllers\FormController::class,'show']);
$router->post('/forms/{slug}',[\App\Controllers\FormController::class,'show']);
$router->get('/login/enterprise',[\App\Controllers\EnterpriseLoginController::class,'login']);
$router->get('/account/security',[\App\Controllers\AccountSecurityController::class,'index']);
$router->post('/account/security',[\App\Controllers\AccountSecurityController::class,'index']);
$router->get('/buscar',[\App\Controllers\UniversalSearchController::class,'index']);
foreach (['get','post'] as $method) $router->$method('/admin/accessibility',[\App\Controllers\Admin\AccessibilityController::class,'index']);
foreach (['get','post'] as $method) {
    $router->$method('/admin/settings/joomla-catalog', [\App\Controllers\CatalogMediaController::class,'admin']);
    $router->$method('/admin/settings/community-profiles', [\App\Controllers\CommunityProfilesController::class,'settings']);
    $router->$method('/comunidad/mi-perfil', [\App\Controllers\CommunityProfilesController::class,'edit']);
}
$router->get('/comunidad/perfil/{id}', [\App\Controllers\CommunityProfilesController::class,'show']);
if (\App\Core\Settings::get('joomla_catalog_enabled') === '1') {
    $router->post('/biblioteca/aportar',[\App\Controllers\CatalogMediaController::class,'submit']);
    $router->get('/biblioteca/ficha/{id}/{kind}',[\App\Controllers\CatalogMediaController::class,'media']);
$joomla = \App\Controllers\JoomlaCatalogController::class;
foreach (['/biblioteca'=>'catalog', '/index.php'=>'home', '/index.php/midis'=>'catalog', '/index.php/midis-en-ms'=>'catalog', '/index.php/midis/category/{alias}'=>'category', '/index.php/midis-en-ms/category/{alias}'=>'category', '/index.php/midis/download/{category}/{id}'=>'legacyEntry', '/index.php/midis-en-ms/download/{category}/{id}'=>'legacyEntry', '/biblioteca/ficha/{id}'=>'entry', '/index.php/novedades'=>'recent', '/index.php/buscador/search'=>'search', '/index.php/buscador'=>'search', '/index.php/etiquetas'=>'tags', '/index.php/foro'=>'unavailable', '/index.php/partituras'=>'unavailable', '/index.php/partituras/{slug}'=>'unavailable', '/index.php/contacto'=>'contact'] as $path=>$action) $router->get($path,[$joomla,$action]);
foreach (['contenido','tools','vst','terminos','galeria-de-videos','productos-lista','informe-de-errores'] as $alias) {
    $router->get('/index.php/'.$alias, static function () use ($alias): void { (new \App\Controllers\JoomlaCatalogController())->editorial($alias); });
    $router->get('/index.php/'.$alias.'/{slug}', static function (string $slug) use ($alias): void { (new \App\Controllers\JoomlaCatalogController())->editorial($alias, $slug); });
}
}
$catalogHome=\App\Core\Settings::get('joomla_catalog_enabled')==='1' && \App\Core\Settings::get('joomla_catalog_home')==='1';
$router->get('/', $catalogHome?[\App\Controllers\JoomlaCatalogController::class,'home']:[HomeController::class,'index']);
require __DIR__ . '/modules.php';
$router->get('/ads/visit/{id}',[\App\Controllers\AdsController::class,'visit']);
$router->get('/page/{slug}', [PageController::class, 'show']);
$router->get('/{slug}', [PageController::class, 'show']);
