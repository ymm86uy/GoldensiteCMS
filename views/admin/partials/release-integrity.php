<?php
declare(strict_types=1);
$integrity=\App\Core\ReleaseIntegrity::status();
$integrityEscape=\App\Core\ConsoleView::escape(...);
$integrityLabels=['unavailable'=>'Referencia oficial no disponible','unchecked'=>'Pendiente de comprobación','matching'=>'Sin diferencias en la última comprobación','differences'=>'Diferencias detectadas','partial'=>'Comprobación parcial','stale'=>'Comprobación pendiente de renovar'];
?>
<section class="nx-panel" style="margin-bottom:22px" aria-labelledby="release-integrity-heading">
    <div class="nx-panel-head"><h2 id="release-integrity-heading">Integridad de la instalación</h2></div>
    <div class="nx-panel-body nx-stack">
        <p><strong>GoldenSiteCMS <?= $integrityEscape(\App\Core\ProductEdition::current()==='owner'?'Owner':'Comercial') ?> · <?= $integrityEscape(\App\Core\ReleaseUpdate::version()) ?></strong></p>
        <p role="status"><?= $integrityEscape($integrityLabels[$integrity['state']]??$integrityLabels['unchecked']) ?></p>
        <?php if (isset($integrity['time'])): ?>
            <p>Última comprobación: <time datetime="<?= $integrityEscape(gmdate('c',$integrity['time'])) ?>"><?= $integrityEscape(gmdate('Y-m-d H:i:s',$integrity['time'])) ?> UTC</time>.
                Archivos revisados: <?= (int)$integrity['checked'] ?>/<?= (int)$integrity['total'] ?>.
                Faltantes: <?= (int)$integrity['missing_count'] ?> · Modificados: <?= (int)$integrity['changed_count'] ?> · Omitidos: <?= (int)$integrity['skipped'] ?>.</p>
        <?php endif ?>
        <p class="nx-note">Compara los archivos oficiales con una referencia firmada de esta edición y versión. No modifica archivos ni datos. El resultado corresponde a la fecha indicada; no sustituye al antivirus ni certifica que el sitio esté libre de vulnerabilidades. Las extensiones añadidas y los archivos privados quedan fuera de esta comparación.</p>
        <?php if (\App\Core\IdentityAccess::superuser()): ?>
            <?php if (!empty($integrity['missing']) || !empty($integrity['changed'])): ?>
                <details><summary>Archivos que requieren revisión (hasta 30 por grupo)</summary>
                    <ul><?php foreach ($integrity['missing'] as $integrityPath): ?><li>Faltante: <code><?= $integrityEscape($integrityPath) ?></code></li><?php endforeach ?>
                    <?php foreach ($integrity['changed'] as $integrityPath): ?><li>Modificado: <code><?= $integrityEscape($integrityPath) ?></code></li><?php endforeach ?></ul>
                    <p>Comprueba si el cambio fue autorizado y compáralo con el instalador oficial antes de restaurarlo. Se conserva el archivo para su análisis.</p>
                </details>
            <?php endif ?>
            <?php if ($integrity['state']!=='unavailable'): ?>
                <form method="post" action="<?= $integrityEscape(\App\Core\AdminPath::url($integrityAction)) ?>" class="nx-stack">
                    <input type="hidden" name="csrf_token" value="<?= $integrityEscape(\App\Core\Csrf::generate()) ?>">
                    <input type="hidden" name="action" value="check_integrity">
                    <label>Contraseña administrativa actual <input type="password" name="current_password" required autocomplete="current-password" maxlength="72"></label>
                    <div class="nx-actions"><button class="nx-btn" type="submit">Comprobar archivos oficiales</button></div>
                </form>
            <?php else: ?><p class="nx-note">Instala un paquete oficial que incluya la referencia firmada. No se genera una referencia a partir de los archivos del servidor.</p><?php endif ?>
        <?php endif ?>
    </div>
</section>
