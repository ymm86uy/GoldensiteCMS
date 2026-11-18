(() => {
    const widgets = [...document.querySelectorAll('[data-gs-weather-widget]')];
    if (!widgets.length) return;
    const locale = document.documentElement.lang || 'es';
    const clock = new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit' });
    const zone = clock.resolvedOptions().timeZone?.replaceAll('_', ' ').replaceAll('/', ' / ') || '';
    const tick = () => {
        const now = new Date();
        for (const widget of widgets) {
            const time = widget.querySelector('[data-gs-clock]');
            time.dateTime = now.toISOString();
            time.textContent = clock.format(now);
        }
    };
    tick();
    setInterval(tick, 1000);
    for (const widget of widgets) {
        widget.querySelector('[data-gs-zone]').textContent = zone;
        const button = widget.querySelector('[data-gs-weather-request]');
        const status = widget.querySelector('[data-gs-weather-status]');
        button?.addEventListener('click', () => {
            if (!navigator.geolocation) { status.textContent = 'La ubicación requiere un navegador compatible y una conexión segura.'; return; }
            button.disabled = true;
            status.textContent = 'Consultando la ubicación…';
            navigator.geolocation.getCurrentPosition(async ({coords}) => {
                try {
                    const body = new URLSearchParams({lat: coords.latitude.toFixed(1), lon: coords.longitude.toFixed(1)});
                    const response = await fetch('/weather-time/current', {
                        method: 'POST', body, headers: {'X-CSRF-Token': button.dataset.csrf}, signal: AbortSignal.timeout(6500),
                    });
                    const data = await response.json();
                    if (!response.ok) { status.textContent = data.message || 'El clima no está disponible.'; return; }
                    const fahrenheit = widget.dataset.unit === 'f';
                    const value = fahrenheit ? data.temperature * 1.8 + 32 : data.temperature;
                    const formatted = new Intl.NumberFormat(locale, {maximumFractionDigits: 1}).format(value);
                    const validAt = data.valid_at ? new Date(data.valid_at) : null;
                    const forecast = validAt && !Number.isNaN(validAt.valueOf()) ? ` · Pronóstico para las ${clock.format(validAt)}` : '';
                    status.textContent = `${formatted} °${fahrenheit ? 'F' : 'C'} · ${data.condition}${forecast}`;
                } catch {
                    status.textContent = 'El clima no está disponible en este momento.';
                } finally {
                    button.disabled = false;
                }
            }, (error) => {
                status.textContent = error.code === 1 ? 'Permiso de ubicación denegado.' : 'No se pudo determinar la ubicación.';
                button.disabled = false;
            }, {enableHighAccuracy: false, timeout: 8000, maximumAge: 300000});
        });
    }
})();
