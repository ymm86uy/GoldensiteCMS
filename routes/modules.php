<?php
if (is_file($forumRoutes=dirname(__DIR__).'/modules/GoldensiteForum/routes.php')) require $forumRoutes;
unset($forumRoutes);
if (is_file($socialRoutes=dirname(__DIR__).'/modules/GoldensiteSocial/routes.php')) require $socialRoutes;
unset($socialRoutes);
// Explicit handlers keep the module catalog and its existing controllers in sync.
$moduleScreens = [
    'gdpr-banner' => \App\Controllers\Admin\GdprController::class,
    'sitemap' => \App\Controllers\Admin\SitemapAdminController::class,
    'webhooks' => \App\Controllers\Admin\WebhooksController::class,
    'asset-optimizer' => \App\Controllers\Admin\AssetOptimizerController::class,
    'qr-generator' => \App\Controllers\Admin\QrGeneratorController::class,
    'analytics' => \App\Controllers\Admin\AnalyticsController::class,
    'monitor-redirects' => \App\Controllers\Admin\RedirectMonitorController::class,
    'redirects' => \App\Controllers\Admin\RedirectMonitorController::class,
    'admin-tools' => \App\Controllers\Admin\AdminToolsController::class,
    'maintenance' => \App\Controllers\Admin\MaintenanceController::class,
    'site-health' => \App\Controllers\Admin\SiteDoctorController::class,
    'live-chat' => \App\Controllers\Admin\ChatModerationController::class,
    'login-security' => \App\Controllers\Admin\LoginSecurityController::class,
    'marketplace' => \Modules\GoldensiteMarketplace\Controllers\MarketplaceController::class,
    'downloads' => \App\Controllers\Admin\DownloadsController::class,
    'gallery' => \App\Controllers\Admin\GalleryController::class,
    'importer' => \App\Controllers\Admin\MigratorController::class,
    'paywall' => \App\Controllers\Admin\VaultController::class,
    'commerce' => \App\Controllers\Admin\SalesController::class,
    'seo-tracker' => \App\Controllers\Admin\SeoSettingsController::class,
    'dam' => \App\Controllers\Admin\AssetLibraryController::class,
    'db-cleaner' => \App\Controllers\Admin\DatabaseCleanerController::class,
    'automation-hub' => \App\Controllers\Admin\AutomationHubController::class,
    'sso' => \App\Controllers\Admin\SsoController::class,
    'backups' => \App\Controllers\Admin\ReleaseBackupController::class,
    'dating' => \App\Controllers\Admin\DatingController::class,
    'media' => \App\Controllers\Admin\MediaController::class,
    'newsletter' => \App\Controllers\Admin\NewsletterController::class,
    'chat-moderation' => \App\Controllers\Admin\ChatModerationController::class,
    'comments' => \App\Controllers\Admin\CommentsController::class,
    'seo-settings' => \App\Controllers\Admin\SeoSettingsController::class,
    'sales' => \App\Controllers\Admin\SalesController::class,
    'release-backup' => \App\Controllers\Admin\ReleaseBackupController::class,
    'migrator' => \App\Controllers\Admin\MigratorController::class,
    'menu' => \App\Controllers\Admin\MenuController::class,
    'ftp' => \App\Controllers\Admin\FtpController::class,
];
foreach ($moduleScreens as $path => $controller) {
    $router->get('/admin/' . $path, [$controller, 'index']);
    $router->post('/admin/' . $path, [$controller, 'index']);
}
$router->get('/admin/import-export', [\App\Controllers\Admin\ConfigTransferController::class, 'index']);
$router->post('/admin/import-export', [\App\Controllers\Admin\ConfigTransferController::class, 'index']);
foreach (['get','post'] as $method) {
    $router->$method('/admin/import-export/joomla',[\App\Controllers\CatalogMediaController::class,'migration']);
    $router->$method('/admin/downloads/catalog',[\App\Controllers\CatalogMediaController::class,'library']);
}
$router->post('/admin/gallery/save', [\App\Controllers\Admin\GalleryController::class, 'save']);
$router->post('/admin/sso/save', [\App\Controllers\Admin\SsoController::class, 'save']);
$router->get('/login/saml', [\App\Controllers\SamlController::class, 'start']);
$router->get('/login/saml/metadata', [\App\Controllers\SamlController::class, 'metadata']);
$router->post('/login/saml/acs', [\App\Controllers\SamlController::class, 'acs']);
$router->get('/api/scim/v2/ServiceProviderConfig', [\App\Controllers\ScimController::class, 'discovery']);
$router->get('/api/scim/v2/Users', [\App\Controllers\ScimController::class, 'users']);
$router->post('/api/scim/v2/Users', [\App\Controllers\ScimController::class, 'users']);
foreach (['GET','PUT','PATCH','DELETE'] as $scimMethod) {
    $router->add($scimMethod,'/api/scim/v2/Users/{id}',[\App\Controllers\ScimController::class,'users']);
}
$router->post('/admin/db-cleaner/clean', [\App\Controllers\Admin\DatabaseCleanerController::class, 'clean']);
$router->get('/admin/db-cleaner/backup', [\App\Controllers\Admin\DatabaseCleanerController::class, 'backup']);
$router->post('/admin/release-backup/create', [\App\Controllers\Admin\ReleaseBackupController::class, 'create']);
$router->get('/admin/security-audit',[\App\Controllers\Admin\SecurityAuditController::class,'index']);
$router->post('/admin/security-audit',[\App\Controllers\Admin\SecurityAuditController::class,'index']);
$router->get('/admin/release-backup/download/{name}', [\App\Controllers\Admin\ReleaseBackupController::class, 'download']);
$router->get('/admin/release-backup/sql/{name}', [\App\Controllers\Admin\ReleaseBackupController::class, 'downloadSql']);
$router->post('/admin/release-backup/apply-update', [\App\Controllers\Admin\ReleaseBackupController::class, 'applyUpdate']);
$router->post('/admin/release-backup/install-prepared', [\App\Controllers\Admin\ReleaseBackupController::class, 'installPrepared']);
$router->post('/admin/migrator/import', [\App\Controllers\Admin\MigratorController::class, 'processImport']);
$router->post('/admin/users/reset-password', [\App\Controllers\UserController::class, 'resetPassword']);
$router->get('/blog', [\App\Controllers\BlogController::class, 'index']);
foreach (['GET','POST'] as $businessMethod) {
    $router->add($businessMethod,'/admin/booking',[\App\Controllers\BookingController::class,'admin']);
    $router->add($businessMethod,'/reservas',[\App\Controllers\BookingController::class,'publicPage']);
    $router->add($businessMethod,'/admin/support-desk',[\App\Controllers\Admin\MessagesController::class,'index']);
    $router->add($businessMethod,'/admin/academy',[\App\Controllers\AcademyController::class,'admin']);
    $router->add($businessMethod,'/academia',[\App\Controllers\AcademyController::class,'publicPage']);
    $router->add($businessMethod,'/admin/events',[\App\Controllers\EventsController::class,'admin']);
    $router->add($businessMethod,'/eventos',[\App\Controllers\EventsController::class,'publicPage']);
    $router->add($businessMethod,'/admin/s3-connector',[\App\Controllers\Admin\S3Controller::class,'index']);
}
$router->get('/academia/certificado/{code}',[\App\Controllers\AcademyController::class,'verify']);
$router->get('/chat', [\App\Controllers\ChatController::class, 'index']);
$router->post('/chat', [\App\Controllers\ChatController::class, 'index']);
$router->get('/api/chat/messages', [\App\Controllers\ChatApiController::class, 'getMessages']);
$router->post('/api/chat/send', [\App\Controllers\ChatApiController::class, 'sendMessage']);
$router->get('/blog/{slug}', [\App\Controllers\BlogController::class, 'show']);
$router->get('/api/search', [\App\Controllers\UniversalSearchController::class, 'api']);
$router->get('/api/blog/posts', [\App\Controllers\BlogApiController::class, 'getPaginatedPosts']);
$router->get('/contacto', [\App\Controllers\ContactController::class, 'index']);
$router->post('/contacto', [\App\Controllers\ContactController::class, 'index']);
$router->get('/faq', [\App\Controllers\FaqController::class, 'index']);
$router->get('/sitemap.xml', [\App\Controllers\SitemapController::class, 'xml']);
$router->get('/sitemap-core.xml', [\App\Controllers\SitemapController::class, 'core']);
$router->get('/sitemap-library/{file}', [\App\Controllers\SitemapController::class, 'library']);
$router->get('/robots.txt', [\App\Controllers\SitemapController::class, 'robots']);
$router->get('/marketplace', [\Modules\GoldensiteMarketplace\Controllers\MarketplaceController::class, 'publicStore']);
$router->post('/marketplace', [\Modules\GoldensiteMarketplace\Controllers\MarketplaceController::class, 'publicStore']);
$router->get('/dating',[\App\Controllers\DatingCommunityController::class,'index']);
$router->post('/dating',[\App\Controllers\DatingCommunityController::class,'index']);
$router->get('/dating/confirm',[\App\Controllers\DatingCommunityController::class,'confirm']);
$router->post('/dating/confirm',[\App\Controllers\DatingCommunityController::class,'confirm']);
$router->get('/dating/media/{id}',[\App\Controllers\DatingCommunityController::class,'media']);
$router->get('/dating/messages/{id}',[\App\Controllers\DatingCommunityController::class,'messages']);
$router->get('/tienda', [\App\Controllers\ShopController::class, 'index']);
$router->get('/producto/{id}', [\App\Controllers\ProductDetailController::class, 'show']);
$router->get('/cart', [\App\Controllers\CartController::class, 'index']);
$router->post('/cart/add', [\App\Controllers\CartController::class, 'add']);
$router->post('/cart/remove', [\App\Controllers\CartController::class, 'remove']);
$router->get('/checkout', [\App\Controllers\CartController::class, 'checkout']);
$router->post('/checkout', [\App\Controllers\CartController::class, 'checkout']);
$router->get('/download/{id}', [\App\Controllers\PublicDownloadController::class, 'download']);
$router->post('/api/newsletter', [\App\Controllers\FooterController::class, 'subscribeNewsletter']);
$router->post('/newsletter/subscribe', [\App\Controllers\FooterController::class, 'subscribeNewsletter']);
foreach (['confirm','unsubscribe'] as $operation) {
    $router->get('/newsletter/'.$operation,[\App\Controllers\NewsletterSubscriptionController::class,$operation]);
    $router->post('/newsletter/'.$operation,[\App\Controllers\NewsletterSubscriptionController::class,$operation]);
}
