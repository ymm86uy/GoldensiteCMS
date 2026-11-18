<link rel="stylesheet" href="/assets/goldensite-social.css">
<section class="gs-social" data-social data-token="<?= $e($csrf) ?>">
<header class="gs-social-hero"><div><h1>GoldensiteCMS Social Network</h1><p>Comparte un eco y explora las conversaciones de la comunidad.</p></div><a href="#gs-create">Crear eco</a></header>
<div class="gs-social-grid">
<section class="gs-social-panel"><h2>Mapa de frecuencias</h2><p>Actividad por país con al menos tres participantes. La presencia caduca a los 15 minutos.</p>
<canvas width="900" height="365" aria-hidden="true" data-social-map></canvas><ul aria-live="polite" data-social-regions></ul>
<form data-presence method="post"><label>Compartir presencia <select name="region"><option value="">No compartir</option><?php foreach ($regions as $region): ?><option value="<?= $e($region) ?>"><?= $e($region) ?></option><?php endforeach ?></select></label><button>Aplicar</button></form>
</section>
<section id="gs-create" class="gs-social-panel"><h2>Crear eco</h2>
<form data-echo method="post" enctype="multipart/form-data"><label>Título <input name="title" maxlength="100" required></label><label>Mensaje <textarea name="body" rows="4" maxlength="2000" required></textarea></label>
<label>País del eco (opcional y visible a los miembros) <select name="region"><option value="">Sin país</option><?php foreach ($regions as $region): ?><option value="<?= $e($region) ?>"><?= $e($region) ?></option><?php endforeach ?></select></label>
<label>Audio opcional (hasta 8 MB) <input type="file" name="audio" accept="audio/mpeg,audio/ogg,audio/wav"></label><button>Publicar eco</button></form>
</section></div>
<p role="status" data-social-status></p>
<h2>Ecos recientes</h2><div class="gs-social-feed" data-echoes></div><button type="button" data-more hidden>Ver más ecos</button>
<noscript>Activa JavaScript para cargar los ecos y el mapa.</noscript>
</section><script src="/assets/goldensite-social.js" defer></script>
