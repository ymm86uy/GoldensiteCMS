<div class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / IDENTIDAD EMPRESARIAL</div><h1>SSO &amp; SCIM Enterprise</h1><p>Acceso corporativo y ciclo de vida de cuentas gestionadas.</p></div></div>
<?php if ($notice): ?><p class="nx-alert" role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if (!$enabled): ?><p class="nx-alert">Activa el módulo en <a href="<?= $e(\App\Core\AdminPath::url('/admin/plugins')) ?>">Módulos y extensiones</a> para configurar el proveedor.</p><?php return; endif; ?>
<?php if (!$origin): ?><p class="nx-alert">Configura APP_URL antes de conectar el proveedor.</p><?php endif; ?>
<div class="nx-operation-grid">
<section class="nx-panel"><div class="nx-panel-head"><h2>Inicio de sesión SAML 2.0</h2></div>
<form method="post" action="<?= $e($base.'/save') ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="saml">
<label><input type="checkbox" name="enabled" value="1" <?= ($config['enabled']??false)?'checked':'' ?>> Habilitar acceso SAML</label>
<label>Identificador del proveedor (Entity ID)<input name="issuer" maxlength="512" value="<?= $e($config['issuer']??'') ?>"></label>
<label>URL de inicio de sesión del proveedor<input name="url" type="url" maxlength="1024" value="<?= $e($config['url']??'') ?>"></label>
<label>Certificado de firma X.509 del proveedor (PEM)<textarea name="cert" rows="6" maxlength="12000"><?= $e($config['cert']??'') ?></textarea></label>
<p class="nx-note">Configura el proveedor para firmar la respuesta y la aserción, y enviar como NameID el userName exacto de la cuenta aprovisionada.</p>
<?php if ($origin): ?><p>Identificador SP y metadatos <a href="/login/saml/metadata"><?= $e($origin.'/login/saml/metadata') ?></a></p><p>URL ACS <code><?= $e($origin.'/login/saml/acs') ?></code></p><?php endif; ?>
<label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label>
<button class="nx-btn primary">Guardar SAML</button>
</form></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Aprovisionamiento SCIM 2.0</h2></div><div class="nx-panel-body nx-stack">
<p>Estado <strong><?= $scim?'Activo':'Desactivado' ?></strong></p>
<?php if ($origin): ?><p>URL base <code><?= $e($origin.'/api/scim/v2') ?></code></p><?php endif; ?>
<p>Gestiona usuarios creados por este proveedor. Las cuentas administrativas y los grupos quedan fuera de este conector.</p>
<?php if ($token): ?><label>Nuevo token de acceso<input readonly value="<?= $e($token) ?>" autocomplete="off"></label><?php endif; ?>
<form method="post" action="<?= $e($base.'/save') ?>" class="nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label>
<button class="nx-btn primary" name="action" value="scim_rotate"><?= $scim?'Rotar token':'Habilitar y generar token' ?></button>
<?php if ($scim): ?><button class="nx-btn danger" name="action" value="scim_disable">Desactivar y revocar token</button><?php endif; ?>
</form></div></section>
</div>
