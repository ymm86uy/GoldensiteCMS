<link rel="stylesheet" href="/assets/goldensite-social.css?v=2">
<section class="gs-social" data-social data-token="<?= $e($csrf) ?>">
<header class="gs-social-hero"><div><h1>GoldensiteCMS Social Network</h1><p>Comparte un eco y explora las conversaciones de la comunidad.</p></div><a href="#gs-create">Crear eco</a></header>
<div class="gs-social-grid">
<section class="gs-social-panel gs-social-map-panel"><h2>Mapa de frecuencias</h2><p>Abre las burbujas para ver los ecos recientes. Las burbujas agrupadas muestran cuántos ecos contienen.</p>
<div class="gs-social-map" data-social-map><canvas width="900" height="365" aria-hidden="true"></canvas><div class="gs-social-markers" data-social-markers></div></div>
<p class="gs-social-legend"><span>● Solo país</span> <span>● Zona aproximada</span></p>
<p data-map-empty hidden>Todavía no hay ecos que compartan ubicación.</p>
<details data-map-index><summary>Ecos del mapa por país</summary><ul data-map-echoes></ul></details>
<div data-map-selection hidden aria-label="Ecos seleccionados en el mapa"><h3 tabindex="-1" data-map-heading></h3><ul data-map-choices></ul><div data-map-preview></div></div>
<details><summary>Presencia temporal de miembros</summary><p>Se muestra por país a partir de tres participantes y caduca a los 15 minutos.</p><ul data-social-regions></ul>
<form data-presence method="post"><label>Compartir presencia <select name="region"><option value="">No compartir</option><?php foreach ($regions as $region): ?><option value="<?= $e($region) ?>"><?= $e($region) ?></option><?php endforeach ?></select></label><button>Aplicar</button></form>
</details>
</section>
<section id="gs-create" class="gs-social-panel"><h2>Crear eco</h2>
<form data-echo method="post" enctype="multipart/form-data"><label>Título <input name="title" maxlength="100" required></label><label>Mensaje <textarea name="body" rows="4" maxlength="2000" required></textarea></label>
<label>Ubicación visible para miembros <select name="location_mode"><option value="none">Sin ubicación</option><option value="country">Solo país</option><option value="approximate">Zona aproximada</option></select></label>
<label data-echo-country hidden>País del eco <select name="region" disabled><option value="">Selecciona tu país</option><?php foreach ($regions as $region): ?><option value="<?= $e($region) ?>"><?= $e($region) ?></option><?php endforeach ?></select></label>
<div data-echo-zone hidden><p>La zona se calcula en tu navegador en una cuadrícula de unos 10 km de ancho. Puedes quitarla después de publicar.</p>
<button type="button" data-locate>Calcular mi zona aproximada</button><p role="status" data-location-status></p></div>
<label>Audio opcional (hasta 8 MB) <input type="file" name="audio" accept="audio/mpeg,audio/ogg,audio/wav"></label>
<label>Imagen opcional (hasta 4 MB) <input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
<label>Descripción de la imagen <input name="image_alt" maxlength="160"></label><button type="submit">Publicar eco</button></form>
</section></div>
<p role="status" data-social-status></p>
<h2>Ecos recientes</h2><div class="gs-social-feed" data-echoes></div><button type="button" data-more hidden>Ver más ecos</button>
<noscript>Activa JavaScript para cargar los ecos y el mapa.</noscript>
</section><script src="/assets/goldensite-social.js?v=2" defer></script>
