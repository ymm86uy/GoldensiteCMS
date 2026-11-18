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
<?php if ($report!==null): ?>
    <section class="nx-panel" style="margin-top:24px" aria-labelledby="audit-findings">
        <div class="nx-panel-head"><h2 id="audit-findings">Resultados de la auditoría</h2></div>
        <div class="nx-panel-body nx-stack">
            <p>Áreas revisadas: <?= $e(implode(', ',$report['completed']) ?: 'Ninguna') ?>.</p>
            <div class="nx-actions" aria-label="Hallazgos por severidad">
                <?php $counts=array_count_values(array_column($report['findings'],'severity')); ?>
                <?php foreach (['Crítico','Alto','Medio','Informativo'] as $severity): ?><span class="nx-pill"><?= $e($severity) ?> · <?= (int)($counts[$severity]??0) ?></span><?php endforeach ?>
            </div>
            <?php if (!$report['findings']): ?><p>No se registraron hallazgos en las comprobaciones realizadas.</p><?php endif ?>
            <?php foreach ($report['findings'] as $finding): ?>
                <article class="nx-form-field nx-stack">
                    <div><span class="nx-pill <?= in_array($finding['severity'],['Crítico','Alto'],true)?'warn':'' ?>"><?= $e($finding['severity']) ?></span>
                        <span class="nx-small"><?= $finding['observed']?'Comprobación directa':'Indicio por revisar' ?></span></div>
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
