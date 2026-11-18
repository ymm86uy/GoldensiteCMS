<header class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / MIGRACIÓN</div><h1>Importar y exportar</h1><p>Elige el origen, analiza el contenido y confirma la migración después de revisar el resultado.</p></div></header>
<?php if ($notice): ?><p class="nx-alert" role="status"><?= $e($notice) ?></p><?php endif ?>
<div class="nx-operation-grid">
<section class="nx-panel"><div class="nx-panel-head"><h2>Joomla y jDownloads</h2><span class="nx-pill"><?= $catalogReady?'Catálogo importado':'Pendiente de importar' ?></span></div>
<div class="nx-panel-body nx-stack"><p>Conecta con Joomla en línea para leer artículos. Si tienes un respaldo de Akeeba, usa los SQL y archivos extraídos para preparar también el catálogo de jDownloads.</p><a class="nx-btn primary" href="/admin/import-export/joomla">Abrir asistente Joomla</a></div></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Hostinger</h2><span class="nx-pill">Hosting o creador</span></div>
<div class="nx-panel-body nx-stack"><p><strong>WordPress o Joomla alojado en Hostinger.</strong> Descarga los respaldos de archivos y base de datos desde hPanel. Usa el SQL.GZ para analizar contenido compatible. Conserva el TAR.GZ para recuperar los archivos multimedia por separado.</p>
<p><strong>Creador de sitios.</strong> Si tu versión ofrece exportar a WordPress, completa la exportación en una instalación de WordPress y genera después el XML WXR. El diseño y la tienda se reconstruyen aparte.</p><a class="nx-btn" href="/admin/settings#external-import">Analizar base SQL o SQL.GZ</a><a class="nx-btn" href="#wordpress-wxr">Analizar XML de WordPress</a></div></section>
</div>
<section class="nx-panel" id="wordpress-wxr"><div class="nx-panel-head"><h2>WordPress · XML de contenido</h2><span class="nx-pill">WXR</span></div><div class="nx-panel-body nx-stack">
<p>En WordPress, abre <strong>Herramientas → Exportar</strong>. Sube el XML para recuperar páginas y artículos como borradores. Los adjuntos aparecen en el análisis como referencias. Los archivos, cuentas, temas y extensiones van por separado.</p>
<form method="post" enctype="multipart/form-data" class="nx-stack"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="wxr_scan"><label>Exportación WXR en UTF-8, hasta 8 MiB <input type="file" name="wxr_file" accept=".xml,text/xml,application/xml" required></label><button class="nx-btn primary">Analizar XML de WordPress</button></form>
<?php if ($wxrPlan && $wxrToken!==''): ?>
<?php $types=array_count_values(array_column($wxrPlan['items'],'kind')); ?>
<div class="nx-form-field nx-stack"><h3>Resultado del análisis</h3><p><?= (int)($types['page']??0) ?> páginas · <?= (int)($types['post']??0) ?> artículos · <?= (int)$wxrPlan['attachments'] ?> referencias a adjuntos · <?= (int)$wxrPlan['skipped'] ?> registros omitidos.</p>
<ul><?php foreach (array_slice($wxrPlan['items'],0,12) as $item): ?><li><?= $e($item['title']) ?></li><?php endforeach ?></ul>
<form method="post" class="nx-stack"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="wxr_import"><input type="hidden" name="plan_id" value="<?= $e($wxrToken) ?>"><label>Escribe IMPORTAR <input name="confirm" required pattern="IMPORTAR"></label><label>Contraseña administrativa <input name="current_password" type="password" autocomplete="current-password" maxlength="72" required></label><button class="nx-btn primary">Importar borradores con respaldo previo</button></form>
</div><?php endif ?>
</div></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Respaldos y bases de datos</h2></div><div class="nx-panel-body nx-stack"><p>Convierte contenido de un volcado WordPress o Joomla, o abre el gestor de copias de GoldenSiteCMS.</p><a class="nx-btn" href="/admin/settings#external-import">Convertir contenido SQL</a><a class="nx-btn" href="/admin/backups">Release &amp; Backup</a></div></section>
<section class="nx-panel"><div class="nx-panel-head"><h2>Configuración básica en JSON</h2></div><div class="nx-panel-body nx-stack">
<p>Transfiere el título, la descripción, las palabras clave y los datos de contacto.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><button class="nx-btn" name="action" value="export">Descargar configuración JSON</button></form>
<form method="post" class="nx-stack"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><label>Pega el JSON exportado<textarea name="config_json" rows="8" maxlength="64000" required></textarea></label><button class="nx-btn primary" name="action" value="import">Importar configuración básica</button></form>
</div></section>
