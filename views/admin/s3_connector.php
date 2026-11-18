<div class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / ALMACENAMIENTO</div><h1>S3 Connector</h1><p>Copias remotas cifradas para las bibliotecas Downloads y DAM.</p></div></div>
<?php if (!$active): ?><p class="nx-alert">Activa el módulo desde Módulos y extensiones.</p><?php return; endif; ?>
<?php if ($notice): ?><p class="nx-alert" role="status"><?= $e($notice) ?></p><?php endif; ?>
<?php if (!$configured): ?><p class="nx-alert">Configura <code>GOLDENSITE_S3_BUCKET</code>, <code>GOLDENSITE_S3_REGION</code>, <code>GOLDENSITE_S3_ACCESS_KEY_ID</code> y <code>GOLDENSITE_S3_SECRET_ACCESS_KEY</code> en el entorno privado del servidor.</p><?php else: ?>
<section class="nx-panel"><div class="nx-panel-head"><h2>Copiar un recurso</h2></div><form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
<p>Destino <?= $e($bucket) ?> · <?= $e($region) ?>. Máximo 20 MB por archivo.</p>
<label>Biblioteca<select name="library"><option value="downloads" <?= $library==='downloads'?'selected':'' ?>>Downloads</option><option value="dam" <?= $library==='dam'?'selected':'' ?>>DAM</option></select></label>
<label>ID de descarga o ruta relativa de DAM<input name="reference" maxlength="255" required value="<?= $e($reference) ?>"></label>
<label>Contraseña actual<input name="current_password" type="password" required autocomplete="current-password"></label>
<button class="nx-btn primary" name="action" value="copy">Crear copia cifrada</button>
<p class="nx-note">La biblioteca sigue sirviendo los archivos locales con sus permisos actuales. Usa el bucket con el bloqueo de acceso público habilitado y conserva la clave de la instalación para recuperar las copias.</p>
</form></section>
<?php endif; ?>
<section class="nx-panel" style="margin-top:24px"><div class="nx-panel-head"><h2>Copias registradas</h2></div><div class="nx-table-wrap"><table class="nx-table"><thead><tr><th>Recurso</th><th>Tamaño</th><th>Fecha</th><th>Recuperación</th></tr></thead><tbody>
<?php foreach ($replicas as $row): ?><tr><td><?= $e($row['library'].' · '.$row['resource_ref']) ?></td><td><?= number_format((int)$row['bytes']/1024,1) ?> KB</td><td><?= $e($row['created_at']) ?></td><td>
<?php if ($configured && $bucket===$row['bucket'] && $region===$row['region']): ?><form method="post" action="<?= $e($base) ?>" class="nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="replica" value="<?= (int)$row['id'] ?>">
<label>Contraseña actual<input name="current_password" type="password" required autocomplete="current-password"></label>
<button class="nx-btn small" name="action" value="restore">Restaurar si falta el archivo</button>
</form><?php else: ?>Bucket o región distintos<?php endif; ?></td></tr><?php endforeach; ?>
<?php if (!$replicas): ?><tr><td colspan="4">Todavía no hay copias registradas.</td></tr><?php endif; ?>
</tbody></table></div></section>
