<link rel="stylesheet" href="/assets/admin/studio.css?v=<?= $e(\App\Core\ReleaseUpdate::version()) ?>">
<div class="nx-studio">
<header class="nx-page-head">
<div><div class="nx-kicker">GOLDENSITE / ESTUDIO DE COMPOSICIÓN</div><h1>Diseña tu portada</h1><p>Compón el contenido, comprueba el diseño y controla cuándo sale a producción.</p></div>
<div class="nx-actions"><?php if (\App\Core\ProductEdition::current()==="commercial"): ?><a class="nx-btn" href="<?= $e($base) ?>">Crear mi sitio ↗</a><?php endif ?><span class="nx-pill <?= $active?'good':'' ?>"><?= $active?'Portada compuesta activa':'Portada del tema' ?></span><a href="/" class="nx-btn" target="_blank" rel="noopener">Ver portada pública ↗</a></div>
</header>
<nav class="nx-studio-steps" aria-label="Etapas del diseño"><a href="#studio-compose">01 · Componer</a><a href="#studio-review">02 · Revisar y guardar</a><a href="#studio-history">03 · Publicar y recuperar</a></nav>
<?php if ($status): ?><p class="nx-alert" role="status"><?= $e($status) ?></p><?php endif; ?>
<div class="nx-studio-workspace" id="studio-compose">
<section class="nx-panel"><div class="nx-panel-head"><h2>Contenido y estilo</h2><?= \App\Core\GoldensiteIcon::svg('ai-builder') ?></div>
<form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="editor_mode" value="advanced"><input type="hidden" name="revision" value="<?= $e($revision) ?>">
<label>Estilo<select name="template"><?php foreach (['studio'=>'Studio · geometría','editorial'=>'Editorial · lectura','product'=>'Product · escaparate','theme'=>'Tema activo del sitio'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($spec['template']??'')===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
<label>Nombre de la marca<input name="name" maxlength="100" required value="<?= $e($spec['name']??'') ?>"></label>
<label>Texto sobre el título<input name="eyebrow" maxlength="80" value="<?= $e($spec['eyebrow']??'') ?>"></label>
<label>Título principal<input name="headline" maxlength="200" required value="<?= $e($spec['headline']??'') ?>"></label>
<label>Descripción<textarea name="description" rows="4" maxlength="1500"><?= $e($spec['description']??'') ?></textarea></label>
<div class="nx-studio-fields">
<label>Acción principal<input name="cta" maxlength="60" required value="<?= $e($spec['cta']??'') ?>"></label>
<label>Enlace principal<input name="url" maxlength="255" required value="<?= $e($spec['url']??'') ?>"></label>
</div>
<details><summary>Acción secundaria y ventajas</summary><div class="nx-stack nx-studio-extra">
<div class="nx-studio-fields"><label>Acción secundaria<input name="secondary" maxlength="60" value="<?= $e($spec['secondary']??'') ?>"></label><label>Enlace secundario<input name="secondary_url" maxlength="255" value="<?= $e($spec['secondary_url']??'') ?>"></label></div>
<label>Hasta tres ventajas, una por línea<textarea name="benefits" rows="3" maxlength="600"><?= $e($spec['benefits']??'') ?></textarea></label>
</div></details>
<button class="nx-btn primary" name="action" value="generate">Generar borrador</button>
<p class="nx-note">El generador usa composiciones locales. Generar sustituye el borrador actual.</p>
</form></section>
<section class="nx-panel nx-studio-canvas"><div class="nx-panel-head"><h2>Vista previa</h2><span class="nx-pill"><?= $changed?'Borrador distinto de la portada':'Sin cambios guardados' ?></span></div>
<div class="nx-panel-body nx-stack">
<div class="nx-actions" role="group" aria-label="Tamaño de pantalla">
<button class="nx-btn small" type="button" data-preview-width="100%" aria-pressed="true">Escritorio</button><button class="nx-btn small" type="button" data-preview-width="768px" aria-pressed="false">Tableta</button><button class="nx-btn small" type="button" data-preview-width="390px" aria-pressed="false">Móvil</button>
</div>
<p class="nx-note" id="nx-studio-preview-status" role="status">Vista aislada. Los recursos externos y scripts quedan bloqueados en este marco.</p>
<div class="nx-studio-frame"><iframe id="nx-studio-preview" title="Vista previa aislada del borrador" sandbox=""></iframe></div>
</div></section>
</div>
<section class="nx-panel" id="studio-review"><div class="nx-panel-head"><h2>Editor del borrador</h2><span class="nx-pill">HTML · UTF-8</span></div>
<form method="post" action="<?= $e($base) ?>" class="nx-panel-body nx-stack">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="editor_mode" value="advanced"><input type="hidden" name="revision" value="<?= $e($revision) ?>">
<label>Documento HTML<textarea id="nx-studio-code" class="nx-code" name="html_code" maxlength="1048576" rows="18" spellcheck="false"><?= $e($html) ?></textarea></label>
<div class="nx-actions"><button type="button" class="nx-btn" id="nx-studio-update">Actualizar vista previa</button><button class="nx-btn" name="action" value="save_draft">Guardar borrador</button></div>
<?php if ($canPublish): ?><fieldset class="nx-studio-publish" id="studio-history"><legend>Publicar portada</legend><p class="nx-note">La publicación restringida bloquea scripts incrustados y externos. Activa el modo avanzado solo para código de confianza.</p><label><input type="checkbox" name="trusted_scripts" value="1"> Permitir scripts en esta publicación (modo avanzado)</label><label>Contraseña administrativa actual<input type="password" name="current_password" maxlength="72" autocomplete="current-password"></label><button class="nx-btn primary" name="action" value="publish_layout">Publicar portada</button></fieldset><?php endif; ?>
</form></section>
<?php require __DIR__.'/partials/studio-history.php'; ?>
</div><script src="/assets/admin/studio.js?v=<?= $e(\App\Core\ReleaseUpdate::version()) ?>" defer></script>
