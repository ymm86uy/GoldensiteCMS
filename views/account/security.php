<?php use App\Core\Csrf; ?>
<link rel="stylesheet" href="/assets/account.css?v=20261104r2">
<section class="nx-account-page <?= $adminContext?'nx-account-admin':'' ?>" data-account-security>
    <div class="nx-account-heading"><div><p class="nx-account-eyebrow">GOLDENSITE / SEGURIDAD DE ACCESO</p><h1>Seguridad de mi cuenta</h1><p>Autenticación de dos factores mediante una aplicación compatible con TOTP.</p></div></div>
    <div class="nx-account-card nx-security-card">
    <?php if ($status): ?><p role="alert"><?= $e($status) ?></p><?php endif ?>
    <?php if ($codes): ?><div role="status"><h2>Códigos de recuperación</h2><p>Guárdalos ahora en un lugar privado. Cada código sirve una sola vez y no se volverán a mostrar.</p><pre><?= $e(implode("\n",$codes)) ?></pre></div><?php endif ?>
    <?php if ($pending): ?><h2>Configura tu autenticador</h2><p>Introduce esta clave manualmente en tu aplicación. Usa códigos de 6 cifras, SHA-1 y un intervalo de 30 segundos.</p><code data-totp-secret><?= $e($pending['secret']) ?></code><?php endif ?>
    <p>Segundo factor: <strong><?= $user['totp_secret']?'Activado':($pending?'Configuración pendiente':'Sin configurar') ?></strong></p>
    <?php $requiredMfa=\App\Core\IdentityAccess::requiresMfa(); if ($requiredMfa): ?><p role="status">El segundo factor es obligatorio para tu acceso administrativo.</p><?php endif ?>
    <?php if (!$requiredMfa || !$user['totp_secret']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $e(Csrf::generate()) ?>"><input type="hidden" name="action" value="<?= $user['totp_secret']?'disable':($pending?'confirm':'start') ?>">
        <label>Contraseña actual<input name="current_password" type="password" required maxlength="72" autocomplete="current-password"></label>
        <?php if ($pending||$user['totp_secret']): ?><label>Código de autenticación<input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" required maxlength="6" autocomplete="one-time-code"></label><?php endif ?>
        <button class="nx-account-action" type="submit"><?= $user['totp_secret']?'Desactivar segundo factor':($pending?'Confirmar y activar':'Configurar segundo factor') ?></button>
    </form><?php endif ?>
    <a data-security-return href="<?= $e($returnUrl) ?>"><?= $adminContext?'Volver al dashboard':'Volver a mi cuenta' ?></a>
    </div>
</section>

