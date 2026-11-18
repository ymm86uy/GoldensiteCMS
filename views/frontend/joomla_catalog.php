<?php
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$library = '/index.php/midis';
$searchUrl = '/index.php/buscador/search';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?> · <?= $e($brand) ?></title>
<?php if ($screen==='entry'): ?><link rel="canonical" href="/biblioteca/ficha/<?= (int)$entry['source_id'] ?>"><?php endif ?>
<link rel="stylesheet" href="/assets/joomla-catalog.css">
</head>
<body>
<a class="skip" href="#contenido">Saltar al contenido</a>
<header class="site-header">
<div class="wrap masthead"><a href="/" class="brand"><span aria-hidden="true"><?= $e(mb_substr($brand,0,2)) ?></span> <?= $e($brand) ?></a><a class="account" href="<?= \App\Core\Auth::check()?'/comunidad/mi-perfil':'/login' ?>"><?= \App\Core\Auth::check()?'Mi perfil':'Iniciar sesión' ?></a></div>
<nav class="wrap nav" aria-label="Principal">
<?php foreach ($nav as $item): ?>
<?php $url = $item['shop'] ? 'https://tiendamusicasampler.com/' : ($item['path']==='home' ? '/' : ($item['path']==='buscador' ? $searchUrl : '/index.php/'.$item['path'])); ?>
<a href="<?= $e($url) ?>"><?= $e($item['label']) ?></a>
<?php endforeach ?>
</nav>
</header>
<main id="contenido" class="wrap">
<?php if ($screen==='home'): ?>
<section class="hero"><div class="eyebrow"><?= $e($brand) ?> · Biblioteca musical</div><h1>El sonido y las secuencias que buscas</h1><p>Explora la biblioteca de MIDI, karaoke y recursos para músicos. Encuentra secuencias por género y revisa las últimas incorporaciones de la comunidad.</p><div class="actions"><a class="button" href="<?= $library ?>">Entrar a la biblioteca MIDI</a><a class="button alternate" href="/index.php/novedades">Ver novedades</a></div></section>
<div class="tiles home-tiles">
<article class="card"><span class="eyebrow">Repertorio</span><h2>Géneros y regiones</h2><p>Explora ritmos latinos, colecciones por país y subcategorías.</p></article>
<article class="card"><span class="eyebrow">Comunidad</span><h2>Aportes musicales</h2><p>Descubre las incorporaciones de músicos y productores.</p></article>
<article class="card"><span class="eyebrow">Biblioteca</span><h2>Secuencias y recursos</h2><p>Consulta fichas con versión, tamaño y descargas registradas.</p></article>
</div>
<?php else: ?>
<div class="breadcrumbs"><a href="/">Inicio</a><?php if ($screen==='entry'||!empty($category)): ?> / <a href="<?= $library ?>">Biblioteca MIDI</a><?php endif ?></div>
<h1><?= $e($title) ?></h1>
<?php endif ?>
<?php if ($flash): ?><p class="notice" role="status"><?= $e($flash) ?></p><?php endif ?>
<?php if ($screen==='catalog'): ?>
<?php if (!empty($category['description'])): ?><p class="description"><?= $e($category['description']) ?></p><?php endif ?>
<form action="<?= $searchUrl ?>" method="get" class="search"><label for="q">Buscar en la biblioteca</label><div><input id="q" name="q" value="<?= $e($query) ?>" maxlength="120" required placeholder="Título o versión"><button class="button">Buscar</button></div><?php if ($search): ?><div class="filters"><label>Categoría <select name="catid"><option value="0">Todas</option><?php foreach ($searchCategories as $option): ?><option value="<?= (int)$option['source_id'] ?>"<?= (int)$option['source_id']===$categoryId?' selected':'' ?>><?= $e($option['title']) ?></option><?php endforeach ?></select></label><label>Coincidencia <select name="match"><?php foreach (['all'=>'Todas las palabras','any'=>'Cualquier palabra','exact'=>'Frase exacta'] as $value=>$label): ?><option value="<?= $e($value) ?>"<?= $matchMode===$value?' selected':'' ?>><?= $e($label) ?></option><?php endforeach ?></select></label><label>Orden <select name="sort"><?php foreach (['recent'=>'Más recientes','popular'=>'Más descargados','title'=>'Título'] as $value=>$label): ?><option value="<?= $e($value) ?>"<?= $sort===$value?' selected':'' ?>><?= $e($label) ?></option><?php endforeach ?></select></label></div><?php endif ?></form>
<?php if ($categories): ?><section aria-label="Categorías" class="tiles">
<?php foreach ($categories as $cat): ?><article class="card"><h2><a href="<?= $library ?>?catid=<?= (int)$cat['source_id'] ?>&amp;view=category"><?= $e($cat['title']) ?></a></h2><p><?= $e(mb_strimwidth($cat['description'],0,230,'…')) ?></p></article><?php endforeach ?>
</section><?php endif ?>
<?php if ($category || $search || !$categories): ?>
<section aria-label="Resultados" class="results"><h2><?= number_format($total,0,',','.') ?> resultados</h2>
<?php foreach ($items as $item): ?>
<article class="card item"><div><h3><a href="/biblioteca/ficha/<?= (int)$item['source_id'] ?>"><?= $e($item['title']) ?></a></h3><?php if ($item['description']): ?><p><?= $e(mb_strimwidth($item['description'],0,240,'…')) ?></p><?php endif ?></div>
<dl><div><dt>Versión</dt><dd><?= $e($item['version_name'] ?: '—') ?></dd></div><div><dt>Tamaño</dt><dd><?= $e($item['size_label'] ?: '—') ?></dd></div><div><dt>Descargas históricas</dt><dd><?= number_format((int)$item['downloads'],0,',','.') ?></dd></div></dl></article>
<?php endforeach ?>
<?php if ($offset || $offset+$perPage<$total): ?><nav class="pagination" aria-label="Paginación"><?php if ($offset): ?><a class="button alternate" href="<?= $e($pageUrl(max(0,$offset-$perPage))) ?>">Anterior</a><?php endif ?><?php if ($offset+$perPage<$total): ?><a class="button" href="<?= $e($pageUrl($offset+$perPage)) ?>">Siguiente</a><?php endif ?></nav><?php endif ?>
</section>
<?php endif ?>
<?php if ($canSubmit && $category): ?><section class="card"><h2>Aportar un MIDI</h2><form method="post" enctype="multipart/form-data" action="/biblioteca/aportar"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="category" value="<?= (int)$category['source_id'] ?>"><label>Título <input name="title" maxlength="200" required></label> <label>Descripción <input name="description" maxlength="1000"></label> <label>Archivo <input type="file" name="midi" accept=".mid,.midi,.kar" required></label> <button class="button">Enviar a revisión</button></form></section><?php endif ?>
<?php elseif ($screen==='entry'): ?>
<article class="card"><p><a href="<?= $library ?>?catid=<?= (int)$entry['category_id'] ?>&amp;view=category"><?= $e($entry['category']) ?></a></p><p class="description"><?= $e($entry['description']) ?></p><dl><div><dt>Versión</dt><dd><?= $e($entry['version_name'] ?: '—') ?></dd></div><div><dt>Tamaño</dt><dd><?= $e($entry['size_label'] ?: '—') ?></dd></div><div><dt>Descargas históricas</dt><dd><?= number_format((int)$entry['downloads'],0,',','.') ?></dd></div></dl><?php $listen=$downloadModule && $entry['preview_id'] && \App\Core\CatalogMedia::allowed($entry['preview_mode']); $download=$downloadModule && $entry['original_id'] && \App\Core\CatalogMedia::allowed($entry['download_mode']); ?>
<?php if ($listen): ?><audio controls preload="none" aria-label="Muestra de audio" src="/biblioteca/ficha/<?= (int)$entry['source_id'] ?>/preview"></audio><?php endif ?>
<?php if ($download): ?><p><a class="button" href="/biblioteca/ficha/<?= (int)$entry['source_id'] ?>/download">Descargar archivo original</a></p><?php endif ?>
<?php if (!$listen && !$download): ?><p class="notice">La muestra y la descarga dependen de los archivos vinculados y sus permisos.</p><?php endif ?></article>
<?php elseif ($screen==='editorial'): ?>
<div class="tiles"><?php foreach ($articles as $article): ?><article class="card"><span class="eyebrow"><?= $e($article['category_title']) ?></span><h2><a href="<?= $e($editorialPath.'/'.rawurlencode($article['slug'])) ?>"><?= $e($article['title']) ?></a></h2></article><?php endforeach ?></div>
<?php if (!$articles): ?><p class="notice">Esta sección no tiene artículos públicos en la copia disponible.</p><?php endif ?>
<?php elseif ($screen==='article'): ?>
<article class="card editorial"><p class="eyebrow"><?= $e($article['category_title']) ?></p><?= $article['content'] ?></article>
<?php elseif ($screen==='tags'): ?>
<ul class="tags"><?php foreach ($tags as $tag): ?><li><a href="<?= $e($searchUrl.'?'.http_build_query(['tag'=>$tag['source_id']])) ?>"><?= $e($tag['title']) ?></a></li><?php endforeach ?></ul>
<p class="notice">Cada etiqueta abre las fichas vinculadas en el respaldo de Joomla.</p>
<?php elseif ($screen==='unavailable'): ?>
<p class="notice">Esta función del sitio original requiere restaurar su extensión o sus archivos y validar sus permisos.</p>
<?php elseif ($screen==='missing'): ?>
<p>La ficha o categoría solicitada no está disponible.</p><a class="button" href="<?= $library ?>">Volver a la biblioteca</a>
<?php endif ?>
</main>
<footer><div class="wrap"><span><?= $e($brand) ?> · Biblioteca</span><a href="/contacto">Contacto</a></div></footer>
</body></html>
