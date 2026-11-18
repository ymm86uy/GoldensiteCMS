<?php use App\Core\{AdminPath,Csrf,GoldensiteIcon}; ?>
<link rel="stylesheet" href="/assets/account.css?v=20261104r2">
<section class="nx-account-page" data-core-account>
    <div class="nx-account-heading"><div><p class="nx-account-eyebrow">GOLDENSITE / MI CUENTA</p><h1>Tu cuenta. Tu seguridad.</h1><p>Gestiona el acceso a tu cuenta de GoldenSiteCMS.</p></div><?= GoldensiteIcon::svg('identity-access',40) ?></div>
    <div class="nx-account-grid">
        <section class="nx-account-card"><h2>Identidad de la cuenta</h2><dl><dt>Usuario</dt><dd data-nx-content><?= $e($user['username']??'') ?></dd><dt>Correo electrónico</dt><dd data-nx-content><?= $e($user['email']??'') ?></dd><dt>Acceso</dt><dd><?= $adminAccount?'Cuenta con acceso administrativo':'Cuenta de usuario' ?></dd></dl><?php if ($adminAccount): ?><a class="nx-account-action" href="<?= $e(AdminPath::url('/admin/dashboard')) ?>">Volver al dashboard</a><?php endif ?></section>
        <section class="nx-account-card"><h2>Perfil de comunidad</h2><p>Elige qué información y aportes aprobados quieres compartir.</p><a class="nx-account-action" href="/comunidad/mi-perfil">Editar mi perfil</a></section>
        <section class="nx-account-card"><h2>Seguridad de acceso</h2><p>Segundo factor: <strong><?= $secondFactor?'Activado':'Sin configurar' ?></strong></p><p>Protege tu inicio de sesión con una aplicación de autenticación y códigos de recuperación.</p><a class="nx-account-action" href="/account/security">Gestionar segundo factor</a></section>
        <?php if ($chatActive): ?><section class="nx-account-card"><h2>Comunidad Goldensite</h2><p>Accede a las salas que tienes autorizadas en el módulo Live Chat.</p><a class="nx-account-action secondary" href="/chat">Abrir chat de la comunidad</a></section><?php endif ?>
        <?php if ($datingActive): ?><section class="nx-account-card" data-account-module="dating"><h2>GoldensiteLove Dating</h2><p>Tu perfil y las funciones de este módulo se gestionan en su propio espacio.</p><a class="nx-account-action secondary" href="/dating">Abrir GoldensiteLove Dating</a></section><?php endif ?>
    </div>
    <div class="nx-account-footer"><a href="/">Volver al sitio</a><form method="post" action="/logout"><input type="hidden" name="csrf_token" value="<?= $e(Csrf::generate()) ?>"><button type="submit" class="nx-account-action secondary">Cerrar sesión</button></form></div>
</section>
