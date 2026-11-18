<?php $e=\App\Core\ConsoleView::escape(...); $title=$reset?'Elige una contraseña nueva':'Recupera tu cuenta'; ?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($title) ?></title>
<link rel="stylesheet" href="/assets/goldensite-registration.css"><?= \App\Core\ThemeService::head().\App\Core\Typography::style() ?></head>
<body class="nx-public gs-register-public"><main class="gs-register-panel"><h1><?= $e($title) ?></h1>
<?php require __DIR__.'/partials/login_error.php'; ?>
<?php if (!$reset || $token!==''): ?>
    <form method="post" action="<?= $e($route) ?>" class="gs-register-actions">
        <input type="hidden" name="csrf_token" value="<?= $e(\App\Core\Csrf::generate()) ?>">
        <?php if ($reset): ?>
        <div class="input-group"><label for="new_password">Nueva contraseña</label><input id="new_password" name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
        <div class="input-group"><label for="confirm_password">Repite la contraseña</label><input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
        <?php else: ?>
        <div class="input-group"><label for="email">Correo de tu cuenta</label><input id="email" name="email" type="email" maxlength="150" autocomplete="email" required></div>
        <?php endif; ?>
        <button class="btn-submit" type="submit"><?= $reset?'Guardar contraseña':'Enviar enlace' ?></button>
    </form>
<?php else: ?><p>Abre el enlace que recibiste por correo o <a href="/login/forgot">solicita uno nuevo</a>.</p><?php endif; ?>
<p class="gs-register-link"><a href="/login">Volver al inicio de sesión</a></p></main></body></html>
