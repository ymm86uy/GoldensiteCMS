<section class="nx-panel" <?= $canPublish?'':'id="studio-history"' ?>><div class="nx-panel-head"><h2>Historial de portadas</h2><span class="nx-pill"><?= count($versions) ?> recientes</span></div>
<div class="nx-panel-body nx-stack">
<?php if ($active && $canPublish): ?><form method="post" action="<?= $e($base) ?>" class="nx-actions">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="editor_mode" value="<?= $e($editorMode) ?>"><input type="hidden" name="revision" value="<?= $e($revision) ?>">
<label>Contraseña actual<input type="password" name="current_password" maxlength="72" autocomplete="current-password" required></label>
<button class="nx-btn" name="action" value="disable_ai_frontend">Volver a la portada del tema</button>
</form><?php endif; ?>
<?php foreach ($versions as $version): ?><details><summary>Versión #<?= (int)$version['id'] ?> · <?= $e($version['created_at']) ?> · <?= $e($version['username']??'Cuenta retirada') ?></summary>
<form method="post" action="<?= $e($base) ?>" class="nx-actions nx-studio-extra">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="editor_mode" value="<?= $e($editorMode) ?>"><input type="hidden" name="revision" value="<?= $e($revision) ?>"><input type="hidden" name="version_id" value="<?= (int)$version['id'] ?>">
<label>Contraseña actual<input type="password" name="current_password" maxlength="72" autocomplete="current-password" required></label><button class="nx-btn small" name="action" value="restore_version">Recuperar como borrador</button>
</form></details><?php endforeach; ?>
<?php if (!$versions): ?><p class="nx-note">Las portadas reemplazadas aparecerán aquí.</p><?php endif; ?>
</div></section>
