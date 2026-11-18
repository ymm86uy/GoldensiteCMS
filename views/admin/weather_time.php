<div class="nx-stack">
    <header class="nx-console-hero"><h1>Goldensite Weather &amp; Time</h1><p>Publica un reloj local y deja que cada visitante consulte el clima de su zona.</p></header>
    <section class="nx-panel">
        <div class="nx-panel-head"><h2>Ubicación del módulo</h2><a href="<?= $e(\App\Core\AdminPath::url('/admin/plugins')) ?>">Módulos y extensiones</a></div>
        <?php if (!$active): ?><p class="nx-panel-body">Activa este módulo gratuito para configurarlo.</p><?php else: ?>
        <form method="post" class="nx-panel-body nx-stack">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
            <?php if ($notice !== ''): ?><p role="status"><?= $e($notice) ?></p><?php endif; ?>
            <label>Posición<select name="position"><?php foreach (\App\Core\WeatherTime::POSITIONS as $key=>$label): ?><option value="<?= $e($key) ?>" <?= $position===$key?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label>
            <label>Temperatura<select name="unit"><option value="c" <?= $unit==='c'?'selected':'' ?>>Celsius (°C)</option><option value="f" <?= $unit==='f'?'selected':'' ?>>Fahrenheit (°F)</option></select></label>
            <p class="nx-note">Para colocarlo en una página concreta, escribe <code>[goldensite_weather_time]</code> en un párrafo separado del editor visual.</p>
            <fieldset class="nx-stack">
                <legend>Pronóstico sin clave</legend>
                <input type="hidden" name="provider_settings" value="1">
                <label>Página pública de contacto<input type="url" name="met_contact" maxlength="240" value="<?= $e($contact) ?>" placeholder="https://tusitio.com/contacto"></label>
                <p class="nx-note">MET Norway usa esta dirección para identificar al sitio. Revisa sus <a href="https://api.met.no/doc/TermsOfService">condiciones de uso</a> antes de activar el servicio.</p>
                <label><input type="checkbox" name="met_enabled" value="1" <?= $provider==='met'?'checked':'' ?>> He leído y acepto las condiciones de MET Norway. Activar el pronóstico.</label>
            </fieldset>
            <button class="nx-btn primary">Guardar configuración</button>
        </form>
        <?php endif; ?>
    </section>
    <section class="nx-panel"><div class="nx-panel-head"><h2>Servicio meteorológico</h2></div><div class="nx-panel-body">
        <p><?= match ($provider) { 'met'=>'MET Norway activo. Puedes probar el pronóstico desde el sitio.', 'openweather'=>'OpenWeather activo con la clave que ya está configurada.', default=>'El reloj está disponible. Activa MET Norway arriba para habilitar el pronóstico sin clave.' } ?></p>
        <p class="nx-note">El navegador pide permiso al pulsar el botón. Con MET Norway, el servidor comparte coordenadas aproximadas y reutiliza el pronóstico por zona sin vincularlo con cuentas.</p>
    </div></section>
</div>
