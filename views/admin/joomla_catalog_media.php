<?php $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>
<header class="nx-page-head"><div><div class="nx-kicker">GOLDENSITE / BIBLIOTECA</div><h1><?= $section==='library'?'Catálogo, permisos y aportes':'Biblioteca y migración Joomla' ?></h1><p>Prepara la biblioteca y verifica el acceso a los archivos antes de publicarlos.</p></div></header>
<p><a class="nx-btn" href="/admin/import-export">Import / Export</a> <a class="nx-btn" href="/admin/import-export/joomla">Asistente Joomla</a> <a class="nx-btn" href="/admin/downloads/catalog">Permisos y aportes</a></p>
<?php if ($message): ?><p class="nx-alert" role="status"><?= $e($message) ?></p><?php endif ?>
<?php if ($section!=='library'): ?>
<section class="nx-panel">
<div class="nx-panel-head"><h2>Importar artículos de un Joomla en línea</h2><span class="nx-pill">API HTTPS</span></div>
<div class="nx-panel-body nx-stack">
<p>Conecta con la API de Joomla y revisa los artículos antes de copiarlos como borradores. Usa un token de API o un usuario y contraseña si el origen tiene habilitada la autenticación básica.</p>
<form method="post" class="nx-stack" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="online_scan">
<div class="nx-form-grid">
<label>URL base del sitio Joomla <input name="joomla_url" type="url" required maxlength="512" placeholder="https://misitio.com"></label>
<label>Método de autenticación <select name="joomla_auth"><option value="token">Token de API de Joomla (recomendado)</option><option value="basic">Usuario y contraseña de Joomla</option></select></label>
<label>Token de API de Joomla <input name="joomla_token" type="password" maxlength="512" autocomplete="off"></label>
<label>Usuario de Joomla <input name="joomla_user" maxlength="150" autocomplete="off"></label>
<label>Contraseña de Joomla <input name="joomla_password" type="password" maxlength="1024" autocomplete="off"></label>
</div>
<p class="nx-note">Completa solo los campos del método elegido. La cuenta de origen necesita acceso a los servicios web y permiso de lectura de artículos. Las credenciales se usan para el análisis y se descartan.</p>
<button class="nx-btn primary">Analizar sitio en línea</button>
</form>
<?php if ($onlinePlan && $onlineToken!==''): ?>
<div class="nx-form-field nx-stack"><h3><?= count($onlinePlan['items']) ?> artículos encontrados</h3><p>Origen: <?= $e($onlinePlan['origin']) ?>. Se importarán como borradores después del respaldo previo.</p>
<ul><?php foreach (array_slice($onlinePlan['items'],0,12) as $item): ?><li><?= $e($item['title']) ?><?php if ($item['category']): ?> · <?= $e($item['category']) ?><?php endif ?></li><?php endforeach ?></ul>
<form method="post" class="nx-stack"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="online_import"><input type="hidden" name="plan_id" value="<?= $e($onlineToken) ?>">
<label>Escribe IMPORTAR <input name="confirm" required pattern="IMPORTAR"></label>
<label>Tu contraseña administrativa de GoldenSiteCMS <input name="password" type="password" required maxlength="72" autocomplete="current-password"></label>
<button class="nx-btn primary">Importar borradores con respaldo previo</button></form>
</div>
<?php endif ?>
<p class="nx-note">Las cuentas, los menús, la plantilla, las imágenes y los archivos de jDownloads requieren el flujo de respaldo. Hasta 2.000 artículos y 8 MiB de contenido por análisis.</p>
</div></section>
<?php endif ?>
<section class="nx-panel"><div class="nx-panel-body nx-stack">
<?php if ($section!=='library'): ?><h2>Analizar respaldo Joomla</h2>
<p>Extrae la copia de Akeeba fuera del sitio público. Coloca los SQL de Joomla y jDownloads como <code>site.sql</code> y <code>jdownloads.sql</code> en <code>storage/joomla-media-inbox</code>. Usa el importador de contenido para artículos y cuentas.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="source_scan"><label>Prefijo Joomla <input name="prefix" required maxlength="32" placeholder="awgt2_"></label> <button>Analizar respaldo</button></form>
<?php if ($sourcePlan): ?><p><?= (int)$sourcePlan['entries'] ?> fichas y <?= (int)$sourcePlan['categories'] ?> categorías detectadas.</p>
<p>Archivos referenciados: <?= (int)$sourcePlan['inventory']['candidate'] ?> coincidencias por nombre · <?= (int)$sourcePlan['inventory']['missing'] ?> ausentes · <?= (int)$sourcePlan['inventory']['ambiguous'] ?> ambiguos. El tipo y la integridad se verifican al preparar la vinculación.</p>
<?php if ($sourcePlan['entries'] && $sourcePlan['categories']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="source_import"><label>Escribe IMPORTAR <input name="confirm" required pattern="IMPORTAR"></label> <label>Contraseña <input name="password" type="password" required autocomplete="current-password"></label> <button>Importar catálogo con respaldo previo</button></form><?php endif ?>
<?php endif ?>
<?php endif ?>
<?php if (!$enabled && $section==='library'): ?><p>El catálogo todavía no tiene datos en esta instalación. Importa el respaldo de Joomla para gestionar sus fichas, permisos y aportes.</p><p><a class="nx-btn primary" href="/admin/import-export/joomla">Preparar catálogo</a></p><?php endif ?>
<?php if ($enabled): ?><p><a href="/biblioteca">Ver biblioteca</a></p>
<h2>Vincular archivos del respaldo</h2>
<p>Coloca los MIDI, KAR y muestras MP3, WAV u Ogg extraídos en <code>storage/joomla-media-inbox</code>. El análisis busca nombres únicos, valida el tipo de archivo y señala los ausentes o ambiguos.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="scan"><label>Continuar después del ID <input name="after" type="number" min="0" value="<?= (int)($plan['after']??0) ?>"></label> <button>Analizar</button></form>
<?php if ($plan): ?>
<p><?= (int)$plan['stats']['ready'] ?> candidatos · <?= (int)$plan['stats']['missing'] ?> ausentes · <?= (int)$plan['stats']['ambiguous'] ?> ambiguos · <?= (int)$plan['stats']['invalid'] ?> inválidos · <?= (int)$plan['stats']['linked'] ?> vinculados. Último ID analizado <?= (int)$plan['next'] ?>.</p>
<?php if ($plan['matches']): ?><ul><?php foreach ($plan['matches'] as $item): ?><li>#<?= $item['entry'] ?> · <?= $e($item['name']) ?> · <?= $e($item['kind']==='preview'?'Muestra':'Original') ?></li><?php endforeach ?></ul>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="link"><label>Escribe VINCULAR <input name="confirm" required pattern="VINCULAR"></label> <label>Contraseña <input name="password" type="password" required autocomplete="current-password"></label> <button>Vincular con respaldo previo</button></form><p>Se vinculan hasta 25 archivos por vez. Repite el análisis del mismo rango hasta agotarlo.</p><?php endif ?>
<?php endif ?>
<?php if ($section!=='migration'): ?><h2>Permisos por ficha</h2>
<form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="policy"><label>ID de ficha <input name="entry" type="number" min="1" required></label>
<?php foreach (['view_mode'=>'Ver ficha','preview_mode'=>'Escuchar','download_mode'=>'Descargar'] as $field=>$label): ?><label><?= $label ?> <select name="<?= $field ?>"><option value="off">Desactivado</option><option value="member">Cuenta activa</option><option value="public">Público</option></select></label> <?php endforeach ?>
<label>Contraseña <input name="password" type="password" required autocomplete="current-password"></label> <button>Guardar permisos</button></form>
<h2>Aportes pendientes</h2>
<?php if (!$queue): ?><p>Sin aportes pendientes.</p><?php endif ?>
<?php foreach ($queue as $row): ?><article><h3><?= $e($row['title']) ?></h3><p><?= $e($row['category']) ?> · <?= $e($row['description']) ?></p><form method="post"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="submission" value="<?= (int)$row['id'] ?>"><button name="action" value="approve">Aprobar</button> <button name="action" value="reject">Rechazar</button></form></article><?php endforeach ?>
<?php endif ?>
<?php endif ?>
</div></section>
