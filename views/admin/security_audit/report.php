<div class="nx-page-head">
    <div><h1>Auditoría de seguridad</h1><p>Revisa archivos, PHP, cuentas y cabeceras de esta instalación.</p></div>
    <a class="nx-btn" href="<?= $e(\App\Core\AdminPath::url('/admin/plugins')) ?>">Módulos y extensiones</a>
</div>
<?php if ($notice): ?><p class="nx-alert" role="status"><?= $e($notice) ?></p><?php endif ?>
<?php if (\App\Core\FrontendEngine::isModuleActive('security-audit')): ?>
    <form method="post" class="nx-panel nx-panel-body nx-stack">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <p>La auditoría es de lectura y puede tardar varios segundos.</p>
        <div><button type="submit" class="nx-btn primary">Ejecutar auditoría</button></div>
    </form>
<?php endif ?>
<?php if ($history): ?>
<section class="nx-panel" style="margin-top:24px" aria-labelledby="audit-history-title">
    <div class="nx-panel-head"><h2 id="audit-history-title">Historial de auditorías</h2></div>
    <div class="nx-panel-body nx-stack"><p>Se conservan los últimos 30 informes privados. Abrir un informe no ejecuta una auditoría nueva.</p>
        <?php foreach ($history as $entry): ?><a class="nx-btn small" href="<?= $e(\App\Core\AdminPath::url('/admin/security-audit').'?audit='.(int)$entry['id']) ?>" <?= (int)($selectedAudit['id']??0)===(int)$entry['id']?'aria-current="page"':'' ?>><?= $e($entry['created_at'].' UTC · '.$entry['edition'].' '.$entry['cms_version'].' · #'.$entry['id']) ?></a><?php endforeach ?>
    </div>
</section>
<?php endif ?>
<?php if ($report!==null): ?>
    <?php $comparison=$report['comparison']??null; ?>
    <?php if ($selectedAudit): ?><p class="nx-note">Informe #<?= (int)$selectedAudit['id'] ?> · <?= $e($selectedAudit['created_at'].' UTC · '.$selectedAudit['edition'].' '.$selectedAudit['cms_version']) ?></p><?php endif ?>
    <?php if ($comparison): ?>
    <section class="nx-panel" style="margin-top:24px" aria-labelledby="audit-changes-title"><div class="nx-panel-head"><h2 id="audit-changes-title">Cambios entre auditorías</h2></div><div class="nx-panel-body nx-stack">
        <p><?= $comparison['first']?'Primera ejecución guardada: establece la referencia inicial.':'Comparación con el informe #'.(int)$comparison['baseline_id'].'. Un hallazgo ausente solo se marca como resuelto cuando se pudo comprobar su área.' ?></p>
        <div class="nx-actions"><?php foreach (['new'=>$comparison['first']?'Iniciales':'Nuevos','persistent'=>'Persistentes','resolved'=>'Resueltos','unverified'=>'Sin comprobar'] as $kind=>$label): ?><span class="nx-pill"><?= $e($label) ?> · <?= count($comparison[$kind]) ?></span><?php endforeach ?></div>
        <?php foreach (['resolved'=>'Resueltos en esta ejecución','unverified'=>'Pendientes de volver a comprobar'] as $kind=>$heading): if (!$comparison[$kind]) continue; ?>
        <h3><?= $e($heading) ?></h3><ul><?php foreach ($comparison[$kind] as $old): ?><li><?= $e($old['title']) ?> · <code><?= $e($old['location']) ?></code></li><?php endforeach ?></ul>
        <?php endforeach ?>
    </div></section>
    <?php endif ?>
    <section class="nx-panel" style="margin-top:24px" aria-labelledby="audit-findings">
        <div class="nx-panel-head"><h2 id="audit-findings">Resultados de la auditoría</h2></div>
        <div class="nx-panel-body nx-stack">
            <p>Áreas revisadas: <?= $e(implode(', ',$report['completed']) ?: 'Ninguna') ?>.</p>
            <div class="nx-actions" aria-label="Hallazgos por severidad">
                <?php $counts=array_count_values(array_column($report['findings'],'severity')); ?>
                <?php foreach (['Crítico','Alto','Medio','Informativo'] as $severity): ?><span class="nx-pill"><?= $e($severity) ?> · <?= (int)($counts[$severity]??0) ?></span><?php endforeach ?>
            </div>
            <?php if (!$report['findings']): ?><p>No se registraron hallazgos en las comprobaciones realizadas.</p><?php endif ?>
            <?php $states=[]; if ($comparison) foreach (['new'=>$comparison['first']?'Inicial':'Nuevo','persistent'=>'Persistente'] as $kind=>$label) foreach ($comparison[$kind] as $item) $states[\Modules\GoldensiteSecurityAudit\AuditHistory::fingerprint($item)]=$label; ?>
            <?php foreach ($report['findings'] as $finding): ?>
                <article class="nx-form-field nx-stack">
                    <div><span class="nx-pill <?= in_array($finding['severity'],['Crítico','Alto'],true)?'warn':'' ?>"><?= $e($finding['severity']) ?></span>
                        <span class="nx-small"><?= $finding['observed']?'Comprobación directa':'Indicio por revisar' ?></span></div>
                    <?php $state=$states[\Modules\GoldensiteSecurityAudit\AuditHistory::fingerprint($finding)]??null; if ($state): ?><span class="nx-pill"><?= $e($state) ?></span><?php endif ?>
                    <h3><?= $e($finding['title']) ?></h3>
                    <code><?= $e($finding['location']) ?></code>
                    <p><?= $e($finding['risk']) ?></p>
                    <p><strong>Qué hacer</strong> <?= $e($finding['fix']) ?></p>
                </article>
            <?php endforeach ?>
        </div>
    </section>
    <?php if ($report['limits']): ?>
        <section class="nx-panel" style="margin-top:24px" aria-labelledby="audit-coverage">
            <div class="nx-panel-head"><h2 id="audit-coverage">Alcance de esta ejecución</h2></div>
            <ul class="nx-panel-body"><?php foreach ($report['limits'] as $limit): ?><li><?= $e($limit) ?></li><?php endforeach ?></ul>
        </section>
    <?php endif ?>
<?php endif ?>
