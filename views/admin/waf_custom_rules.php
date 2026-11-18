<section class="nx-panel" id="nx-custom-rules" style="margin-bottom:22px">
    <div class="nx-panel-head"><h2>Reglas manuales</h2><small><?= count($manualRules) ?> de 24</small></div>
    <div class="nx-panel-body nx-stack">
        <p class="nx-note">Define rutas públicas completas o prefijos por segmento. Empieza en observación y revisa el registro antes de bloquear. El panel y el acceso a cuentas conservan sus protecciones incorporadas.</p>
        <?php foreach ([['id'=>'','label'=>'','path'=>'','scope'=>'exact','method'=>'*','mode'=>'monitor','enabled'=>true],...$manualRules] as $rule): ?>
        <details <?= $rule['id']===''?'open':'' ?>>
            <summary><?= $rule['id']===''?'Crear regla':$e($rule['label'].' · '.$rule['id'].' · '.($rule['enabled']?($rule['mode']==='block'?'Bloqueo':'Observación'):'Pausada')) ?></summary>
            <form method="post" class="nx-stack">
                <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="rule_id" value="<?= $e($rule['id']) ?>">
                <div class="nx-field-grid">
                    <label>Nombre<input name="label" value="<?= $e($rule['label']) ?>" maxlength="80" required></label>
                    <label>Ruta pública<input name="path" value="<?= $e($rule['path']) ?>" placeholder="/ruta-publica" maxlength="160" required></label>
                    <label>Coincidencia<select name="scope"><option value="exact">Ruta exacta</option><option value="prefix" <?= $rule['scope']==='prefix'?'selected':'' ?>>Ruta y subrutas</option></select></label>
                    <label>Método<select name="method"><?php foreach (\App\Security\WafCustomRules::METHODS as $key=>$label): ?><option value="<?= $e($key) ?>" <?= $rule['method']===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
                    <label>Acción<select name="mode"><option value="monitor">Observar</option><option value="block" <?= $rule['mode']==='block'?'selected':'' ?>>Bloquear</option></select></label>
                </div>
                <div class="nx-actions"><button class="nx-btn primary" name="action" value="save_custom_rule">Guardar regla</button>
                <?php if ($rule['id']!==''): ?>
                    <button class="nx-btn" name="action" value="toggle_custom_rule" formnovalidate><?= $rule['enabled']?'Pausar':'Reactivar' ?></button>
                    <button class="nx-btn danger" name="action" value="remove_custom_rule" formnovalidate>Eliminar regla</button>
                    <a class="nx-btn small" href="?rule=<?= $e($rule['id']) ?>">Ver eventos</a>
                <?php endif; ?></div>
            </form>
        </details>
        <?php endforeach; ?>
    </div>
</section>
