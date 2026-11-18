<?php
$geoEscape=\App\Core\ConsoleView::escape(...);
$geoUrl=\App\Core\AdminPath::url($isWafMap?'/admin/waf':'/admin/dashboard').'?api=geo';
$geoTitle=$isWafMap?'Origen de eventos del WAF':'Usuarios activos por país';
?>
<link rel="stylesheet" href="/assets/admin/activity-map.css">
<script src="/assets/admin/activity-map.js" defer></script>
<section class="nx-panel gs-geo" aria-labelledby="gs-geo-title" data-geo-url="<?= $geoEscape($geoUrl) ?>" data-geo-mode="<?= $isWafMap?'waf':'accounts' ?>">
    <div class="nx-panel-head gs-geo-head">
        <div>
            <h2 id="gs-geo-title"><?= $geoEscape($geoTitle) ?></h2>
            <p><?= $isWafMap?'Detecciones y bloqueos por país aproximado de la IP.':'Cuentas autenticadas, agrupadas por el país aproximado de su sesión más reciente.' ?></p>
        </div>
        <span class="nx-small" data-geo-status>Cargando actividad…</span>
    </div>
    <div class="gs-geo-body">
        <div class="gs-geo-map">
            <canvas aria-hidden="true" width="900" height="365"></canvas>
            <p class="gs-geo-empty" data-geo-empty hidden>Sin ubicaciones para mostrar en el mapa.</p>
        </div>
        <div class="gs-geo-summary">
            <div class="gs-geo-counts">
                <div><strong data-geo-count="last_30">—</strong><span>Últimos 30 min</span></div>
                <div><strong data-geo-count="last_5">—</strong><span>Últimos 5 min</span></div>
            </div>
            <h3>Países</h3>
            <p class="nx-note" data-geo-unknown></p>
            <ul data-geo-countries></ul>
            <p class="nx-note"><a href="https://db-ip.com/db/lite.php">Datos de ubicación por IP de DB-IP</a> · Cartografía de Natural Earth</p>
        </div>
    </div>
    <?php if ($isWafMap): ?>
        <div class="gs-geo-events">
            <div class="nx-panel-head"><h3 data-geo-events-title>Eventos recientes</h3><button type="button" class="nx-btn small" data-geo-clear hidden>Ver todos</button></div>
            <div class="nx-table-wrap"><table class="nx-table">
                <thead><tr><th>Hora</th><th>Origen</th><th>País estimado</th><th>Solicitud</th><th>Decisión</th></tr></thead>
                <tbody data-geo-events></tbody>
            </table></div>
        </div>
    <?php endif ?>
    <noscript><p class="nx-panel-body">Activa JavaScript para cargar el mapa y actualizar los datos.</p></noscript>
</section>
