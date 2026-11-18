<p><strong>Actualización del canal Comercial</strong></p>
<p>Origen <?= $e($selectedCommercial['from']) ?> · Destino <?= $e($selectedCommercial['version']) ?></p>
<div class="nx-actions">
    <?php if ($selectedCommercial['available']): ?><a class="nx-btn" href="<?= $e($selectedCommercial['url']) ?>">Descargar ZIP</a>
    <?php else: ?><p class="nx-note">El ZIP de esta publicación falta o requiere revisión.</p><?php endif ?>
    <a class="nx-btn" href="<?= $e(\App\Core\CommercialUpdateChannel::BASE.$selectedCommercial['from'].'.nfx') ?>" download>Descargar aviso firmado</a>
</div>
<?php if ($canPublishUpdate): ?>
    <form method="post" class="nx-stack">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <input type="hidden" name="action" value="verify-update">
        <input type="hidden" name="commercial_from" value="<?= $e($selectedCommercial['from']) ?>">
        <input type="hidden" name="notice_fingerprint" value="<?= $e($selectedCommercial['fingerprint']) ?>">
        <p>Comprueba el aviso firmado y la huella del ZIP que entrega el canal público.</p>
        <label>Tu contraseña administrativa actual <input name="current_password" type="password" required maxlength="72" autocomplete="current-password"></label>
        <div><button class="nx-btn" type="submit">Verificar publicación en el canal público</button></div>
    </form>
    <details class="nx-form-field">
        <summary>Eliminar del canal</summary>
        <form method="post" class="nx-stack" action="<?= $e(\App\Core\AdminPath::url('/admin/deploy-manager')) ?>?commercial=<?= rawurlencode($selectedCommercial['from']) ?>">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="action" value="retire-update">
            <input type="hidden" name="commercial_from" value="<?= $e($selectedCommercial['from']) ?>">
            <input type="hidden" name="notice_fingerprint" value="<?= $e($selectedCommercial['fingerprint']) ?>">
            <p>El aviso y el ZIP saldrán del canal público y quedarán en un respaldo privado. Las instalaciones que ya aplicaron la versión conservarán sus cambios.</p>
            <label>Escribe ELIMINAR <input name="confirmation" required pattern="ELIMINAR" autocomplete="off"></label>
            <label>Tu contraseña administrativa actual <input name="current_password" type="password" required maxlength="72" autocomplete="current-password"></label>
            <div><button class="nx-btn danger">Confirmar eliminación del canal</button></div>
        </form>
    </details>
<?php else: ?>
    <p><a href="<?= $e(\App\Core\AdminPath::url('/admin/owner')) ?>">Desbloquea Owner</a> para eliminar esta publicación.</p>
<?php endif ?>
