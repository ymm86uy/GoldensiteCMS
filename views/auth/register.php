<?php
use App\Core\{Csrf,FrontendEngine,Locale,Settings,ThemeService,Typography};

$registrationPage = true;
$registrationTitle = $verify ? 'Activa tu cuenta' : 'Crea tu cuenta';
$registrationIntro = $verify ? 'Introduce el código de seis dígitos del correo. Caduca en 10 minutos.' : 'Confirma tu correo para activar tu cuenta.';
if (FrontendEngine::isModuleActive('login-security')) {
    require __DIR__.'/login.php';
    return;
}
$csrfToken = Csrf::generate();
$siteName = htmlspecialchars(Settings::get('site_title','GoldenSiteCMS'), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?= Locale::language() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $registrationTitle ?> · <?= $siteName ?></title>
    <link rel="stylesheet" href="/assets/goldensite-registration.css">
    <?= ThemeService::head() ?>
    <?= Typography::style() ?>
</head>
<body class="nx-public gs-register-public">
    <main class="gs-register-panel">
        <header>
            <a class="gs-register-brand" href="/" aria-label="Volver al inicio"><?= $siteName ?></a>
            <h1><?= $registrationTitle ?></h1>
            <p class="gs-register-intro"><?= $registrationIntro ?></p>
        </header>
        <?php require __DIR__.'/partials/register_form.php'; ?>
    </main>
</body>
</html>
