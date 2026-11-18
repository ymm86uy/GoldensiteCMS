<?php $adminUrl=\App\Core\AdminPath::url(...); ?>
<div class="nx-page-head">
    <div><h1>Página de inicio</h1><p>Edita la portada del tema, publicada en <code>/</code> cuando es la portada activa.</p></div>
    <div class="nx-actions"><a class="nx-btn" href="<?= $e($adminUrl('/admin/pages')) ?>">Volver a Páginas</a><a class="nx-btn" href="/" target="_blank" rel="noopener">Ver sitio ↗</a></div>
</div>
<?php if ($status): ?><p class="nx-alert" role="status"><?= $e($status) ?></p><?php endif ?>
<?php if ($catalog): ?>
    <p class="nx-alert">El catálogo Joomla se muestra actualmente en <code>/</code>. <a href="<?= $e($adminUrl('/admin/settings/joomla-catalog')) ?>">Gestionar catálogo Joomla</a>.</p>
<?php elseif ($composed): ?>
    <p class="nx-alert">Una composición publicada se muestra actualmente en <code>/</code>. <a href="<?= $e($adminUrl('/admin/ai-builder')) ?>">Editar la composición activa</a>.</p>
<?php endif ?>
<form class="nx-grid" method="post" action="<?= $e($route) ?>" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <section class="nx-panel"><div class="nx-panel-head"><h2>Contenido de portada</h2></div>
        <div class="nx-panel-body nx-stack">
            <label>Texto sobre el título<input name="eyebrow" maxlength="90" value="<?= $e($home['eyebrow']) ?>"></label>
            <label>Título principal<input name="headline" maxlength="160" value="<?= $e($home['headline']) ?>" required></label>
            <label>Texto destacado<input name="highlight" maxlength="160" value="<?= $e($home['highlight']) ?>"></label>
            <label>Descripción<textarea name="description" maxlength="600" rows="3"><?= $e($home['description']) ?></textarea></label>
            <?php foreach (['primary'=>'principal','secondary'=>'secundario'] as $type=>$label): ?>
                <div class="nx-field-grid">
                    <label>Texto del botón <?= $label ?><input name="<?= $type ?>_label" maxlength="65" value="<?= $e($home[$type.'_label']) ?>"></label>
                    <label>Ruta interna del botón <?= $label ?><input name="<?= $type ?>_link" maxlength="200" value="<?= $e($home[$type.'_link']) ?>" placeholder="/contacto"></label>
                </div>
            <?php endforeach ?>
            <label>Título de servicios<input name="services_title" maxlength="120" value="<?= $e($home['services_title']) ?>"></label>
            <label>Introducción de servicios<textarea name="services_description" maxlength="300" rows="2"><?= $e($home['services_description']) ?></textarea></label>
        </div>
    </section>
    <section class="nx-panel"><div class="nx-panel-head"><h2>Diseño e imagen</h2></div>
        <div class="nx-panel-body nx-stack">
            <label>Distribución de portada<select name="layout"><option value="center" <?= $home['layout']==='center'?'selected':'' ?>>Centrada</option><option value="split" <?= $home['layout']==='split'?'selected':'' ?>>Texto e imagen en columnas</option></select></label>
            <?php if ($home['image']!==''): ?>
                <img src="<?= $e($home['image']) ?>" alt="<?= $e($home['image_alt']) ?>" style="width:100%;max-height:18rem;object-fit:contain;border-radius:12px">
                <label class="nx-check"><input type="checkbox" name="remove_image" value="1">Retirar imagen actual</label>
            <?php endif ?>
            <label>Imagen de portada<input type="file" name="home_image" accept="image/jpeg,image/png,image/webp"></label>
            <p class="nx-note">JPEG, PNG o WebP de hasta 4 MB. Se optimiza al guardar.</p>
            <label>Texto alternativo de la imagen<input name="image_alt" maxlength="200" value="<?= $e($home['image_alt']) ?>"></label>
            <div class="nx-actions"><button class="nx-btn primary" type="submit">Guardar portada</button><a class="nx-btn" href="<?= $e($adminUrl('/admin/themes/customize')) ?>">Personalizar el tema</a></div>
        </div>
    </section>
</form>
