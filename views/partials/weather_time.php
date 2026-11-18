<?php $weatherEscape = \App\Core\ConsoleView::escape(...); ?>
<aside class="gs-weather" aria-label="Hora local y clima" data-gs-weather-widget data-unit="<?= \App\Core\Settings::get('weather_time_unit')==='f'?'f':'c' ?>">
    <div class="gs-weather-clock"><span>Hora local</span><time data-gs-clock>—</time><small data-gs-zone></small></div>
    <div class="gs-weather-conditions">
        <p data-gs-weather-status role="status"><?= match ($provider) { 'met'=>'Consulta el pronóstico local.', 'openweather'=>'Consulta las condiciones actuales.', default=>'Clima sin configurar.' } ?></p>
        <?php if ($provider !== null): ?>
            <button type="button" data-gs-weather-request data-csrf="<?= $weatherEscape(\App\Core\Csrf::generate()) ?>">Ver clima de mi zona</button>
            <?php if ($provider === 'met'): ?>
                <small>Si das permiso, se comparte una ubicación aproximada con MET Norway.</small>
                <small>Pronóstico de <a href="https://api.met.no/weatherapi/locationforecast/2.0/documentation">MET Norway</a> (<a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>). Condiciones traducidas.</small>
            <?php else: ?>
                <small>Si das permiso, se comparte una ubicación aproximada con <a href="https://openweathermap.org/">OpenWeather</a>.</small>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</aside>
