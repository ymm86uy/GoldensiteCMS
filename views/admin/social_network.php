<section class="nx-panel" data-social-admin>
<div class="nx-panel-head"><h1>GoldensiteCMS Social Network</h1><a href="/social">Abrir red social</a></div>
<div class="nx-panel-body">
<form method="post" class="nx-actions"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="policy">
<label>Permiso para publicar <select name="policy"><option value="members" <?= $policy==='members'?'selected':'' ?>>Cuentas activas</option><option value="roles" <?= $policy==='roles'?'selected':'' ?>>Roles con permiso social.publish</option></select></label><button class="nx-btn">Guardar</button></form>
<p role="status" data-social-status></p>
<?php foreach (['Ecos'=>$rows,'Ondas'=>$waves] as $label=>$entries): ?>
<h2><?= $e($label) ?></h2>
<?php if (!$entries): ?><p>Aún no hay publicaciones.</p><?php endif ?>
<?php foreach ($entries as $row): ?>
<form method="post" class="nx-actions">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<span><?= $e($row['title']??$row['body']) ?></span>
<input type="hidden" name="action" value="<?= ($row['hidden_at']?'restore':'hide').($label==='Ondas'?'-wave':'') ?>">
<button class="nx-btn"><?= $row['hidden_at']?'Restaurar':'Ocultar' ?></button></form>
<?php endforeach; endforeach ?>
</div></section><script src="/assets/goldensite-social.js" defer></script>
