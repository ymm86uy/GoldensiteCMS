<section class="nx-panel-body nx-stack" id="editorial-history" aria-labelledby="editorial-history-title">
    <h3 id="editorial-history-title">Historial editorial</h3>
    <?php if ($recovery): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <input type="hidden" name="page_id" value="<?= (int)$selected['id'] ?>">
            <button class="nx-btn small" name="action" value="discard_recovery">Descartar borrador recuperado</button>
        </form>
    <?php endif; ?>
    <?php if (!$history): ?><p class="nx-note">El historial empieza al guardar cambios en el editor.</p><?php else: ?>
        <p class="nx-note">Se conservan hasta <?= \App\Core\EditorialHistory::LIMIT ?> versiones por documento.</p>
        <div class="nx-table-wrap"><table class="nx-table">
            <thead><tr><th scope="col">Versión</th><th scope="col">Autor y fecha</th><th scope="col">Acción</th></tr></thead>
            <tbody><?php foreach ($history as $entry): ?>
                <tr><td>#<?= (int)$entry['id'] ?> · <?= $e($entry['title']) ?></td>
                <td><?= $e($entry['author_name'] ?? 'Autor sin registrar') ?><small><?= $e($entry['created_at'] ?? 'Fecha sin registrar') ?></small></td>
                <td><a class="nx-btn small" href="?id=<?= (int)$selected['id'] ?>&amp;compare=<?= (int)$entry['id'] ?>#editorial-comparison">Comparar #<?= (int)$entry['id'] ?></a></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
    <?php if ($compare): $snapshot=\App\Core\EditorialHistory::snapshot($compare); ?>
        <section class="nx-stack" id="editorial-comparison" aria-labelledby="editorial-comparison-title">
            <h4 id="editorial-comparison-title">Versión #<?= (int)$compare['id'] ?> frente a la vigente</h4>
            <?php $changed=false; foreach (\App\Core\EditorialHistory::FIELDS as $key=>$label):
                $previous=(string)($snapshot[$key]??''); $current=(string)($selected[$key]??'');
                if ($previous===$current) continue;
                $changed=true;
            ?>
                <details class="nx-form-field" <?= $key==='content'?'':'open' ?>>
                    <summary><?= $e($label) ?></summary>
                    <div class="nx-form-grid">
                        <div><strong>Versión anterior</strong><pre style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:18rem;overflow:auto"><?= $e($previous) ?></pre></div>
                        <div><strong>Versión vigente</strong><pre style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:18rem;overflow:auto"><?= $e($current) ?></pre></div>
                    </div>
                </details>
            <?php endforeach; ?>
            <?php if (!$changed): ?><p class="nx-note">Esta versión coincide con el contenido vigente.</p><?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$selected['id'] ?>">
                    <input type="hidden" name="history_id" value="<?= (int)$compare['id'] ?>">
                    <button class="nx-btn primary" name="action" value="recover">Recuperar como borrador de trabajo</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
