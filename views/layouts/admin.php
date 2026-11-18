<?php
use App\Core\{AdminPath,Auth,Csrf,OwnerAccess,ProductEdition,Settings};
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
$nxEscape = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$nxUser = Auth::user(); $nxCurrent = AdminPath::normalize(parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH));
$nxModuleId=\App\Core\ModuleGate::idForAdminPath($nxCurrent); $nxModule=$nxModuleId?\App\Core\ModuleCatalog::get($nxModuleId):null; if ($nxModule) $title=$nxModule['name'].' · GoldenSiteCMS';
$nxTimezone = Settings::get('site_timezone', 'America/Montevideo'); if (!in_array($nxTimezone, timezone_identifiers_list(), true)) $nxTimezone = 'UTC';
$nxNavigation = [
    'Operaciones' => [['/admin/dashboard','Visión general'],['/admin/messages','Bandeja de entrada'],['/admin/activity-log','Registro de actividad']],
    'Publicación' => [['/admin/pages','Páginas'],['/admin/posts','Artículos'],['/admin/menus','Navegación'],['/admin/ai-builder','Estudio de composición']],
    'Experiencia' => [['/admin/themes','Identidad visual'],['/admin/themes/customize','Personalizar estilo'],['/admin/typography','Tipografía e idiomas'],['/admin/accessibility','Accesibilidad'],['/admin/plugins','Módulos y extensiones'],['/admin/form-builder','Goldensite Form Builder']],
    'Infraestructura' => [['/admin/waf','WAF y análisis de archivos'],['/admin/settings','Configuración'],['/admin/identity-access','Goldensite Identity & Access']]
];
if (ProductEdition::current()==='owner') $nxNavigation['Infraestructura'][]=['/admin/deploy-manager','Goldensite Deploy Manager'];
if (ProductEdition::current() === 'owner' && OwnerAccess::eligible()) $nxNavigation['Infraestructura'][] = ['/admin/owner','Owner · licencias'];
if (\App\Core\IdentityAccess::superuser() && \App\Core\FrontendEngine::isModuleActive('security-audit')) {
    $nxNavigation['Infraestructura'][]=['/admin/security-audit','Auditoría de seguridad'];
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="dark"><title><?= $nxEscape($title ?? 'Consola Goldensite') ?></title><link rel="stylesheet" href="/assets/admin/console.css?v=20261104"><?= \App\Core\SiteIcon::head().\App\Core\Typography::style(true).\App\Core\AdminTheme::head() ?><script src="/assets/admin/console.js?v=20261104" defer></script><?php if (isset($forumAssets)) require $forumAssets; ?></head><body>
<div class="nx-shell"><aside class="sidebar" id="nx-sidebar"><a class="sidebar-brand" href="<?= $nxEscape(AdminPath::url('/admin/dashboard')) ?>"><span class="nx-mark">G</span> GoldenSite<span style="color:#c5ef69;font-size:11px;align-self:flex-start">CMS</span></a><div class="nx-edition">CMS / ADMINISTRACIÓN</div><nav class="sidebar-menu" aria-label="Administración">
<?php $nxIndex = 0; foreach ($nxNavigation as $nxGroup => $nxItems): ?><div class="menu-category"><?= $nxEscape($nxGroup) ?></div><?php foreach ($nxItems as [$nxPath,$nxLabel]): if (!\App\Core\IdentityAccess::allows(\App\Core\IdentityAccess::permissionFor($nxPath))) continue; $nxIndex++; $nxSelected = $nxCurrent === $nxPath || ($nxPath === '/admin/dashboard' && $nxCurrent === '/admin'); ?><a href="<?= $nxEscape(AdminPath::url($nxPath)) ?>" <?= $nxSelected ? 'class="active" aria-current="page"' : '' ?>><?= \App\Core\GoldensiteIcon::svg(basename($nxPath)) ?><?= $nxEscape($nxLabel) ?></a><?php endforeach; endforeach ?>
</nav><div class="nx-account"><span class="nx-small">SESIÓN ADMINISTRATIVA</span><strong><?= $nxEscape($nxUser['username'] ?? 'Administrador') ?></strong><form action="/logout" method="post"><input type="hidden" name="csrf_token" value="<?= $nxEscape(Csrf::generate()) ?>"><button type="submit">Cerrar sesión ↗</button></form></div></aside>
<div class="nx-workspace"><header class="nx-topbar"><div class="nx-status-line"><button class="nx-btn small nx-menu-toggle" data-toggle-sidebar aria-label="Abrir navegación" aria-controls="nx-sidebar" aria-expanded="false">☰</button><span class="nx-breadcrumb">GOLDENSITE <span style="margin:0 12px;color:#4c5561">/</span> <b><?= $nxEscape($nxModule['name']??'CONTROL CENTER') ?></b></span></div><div class="nx-status-line"><time class="nx-clock" data-console-clock data-timezone="<?= $nxEscape($nxTimezone) ?>"></time><a href="/" target="_blank" rel="noopener" class="nx-btn small">Ver sitio ↗</a></div></header><main class="content-area" id="contenido-principal"><div class="nx-legacy-module"><?= \App\Core\AdminTheme::adapt($content??'') ?></div></main></div></div>
</body></html>

<?php echo \App\Core\Locale::html(ob_get_clean(),true); ?>
