const mapSections = document.querySelectorAll('[data-geo-url]');
if (mapSections.length) {
    const cartography = fetch('/assets/admin/world-map.json').then(response => {
        if (!response.ok) throw Error('Cartography unavailable');
        return response.json();
    }).then(world => ({...world, paths: world.paths.map(path => new Path2D(path))})).catch(() => null);

    for (const panel of mapSections) startActivityMap(panel, cartography);
}

function startActivityMap(panel, cartography) {
    const canvas = panel.querySelector('canvas');
    const context = canvas.getContext('2d');
    const list = panel.querySelector('[data-geo-countries]');
    const events = panel.querySelector('[data-geo-events]');
    const status = panel.querySelector('[data-geo-status]');
    const empty = panel.querySelector('[data-geo-empty]');
    const clear = panel.querySelector('[data-geo-clear]');
    const isWaf = panel.dataset.geoMode === 'waf';
    const number = new Intl.NumberFormat(document.documentElement.lang || 'es');
    let world;
    let data;
    let selected = '';
    let markers = [];
    let busy = false;

    function draw() {
        if (!world || !context) return;
        const size = canvas.getBoundingClientRect();
        if (!size.width) return;
        const pixelRatio = Math.min(devicePixelRatio || 1,2);
        canvas.width = Math.round(size.width*pixelRatio);
        canvas.height = Math.round(size.height*pixelRatio);
        context.setTransform(canvas.width/world.width,0,0,canvas.height/world.height,0,0);
        context.fillStyle = '#101923';
        context.fillRect(0,0,world.width,world.height);
        context.fillStyle = '#26394a';
        context.strokeStyle = '#4b6273';
        context.lineWidth = .65;
        for (const shape of world.paths) {
            context.fill(shape,'evenodd');
            context.stroke(shape);
        }
        markers = [];
        for (const country of data?.countries ?? []) {
            if (!Object.hasOwn(world.positions,country.code)) continue;
            const [x,y] = world.positions[country.code];
            const radius = 5 + Math.min(19,Math.sqrt(country.count)*3);
            markers.push({x,y,radius,code:country.code});
            context.beginPath();
            context.arc(x,y,radius,0,Math.PI*2);
            context.fillStyle = selected===country.code ? '#ffdc92' : isWaf ? '#ff856cbf' : '#62d6c5bf';
            context.fill();
            context.strokeStyle = '#f1f8fb';
            context.stroke();
        }
        canvas.toggleAttribute('data-interactive',markers.length>0);
        empty.hidden = markers.length>0;
    }

    function choose(code) {
        selected = selected===code ? '' : code;
        for (const button of list.querySelectorAll('button')) {
            button.setAttribute('aria-pressed',String(button.dataset.country===selected));
        }
        draw();
        renderEvents();
    }

    function renderEvents() {
        if (!events || !data) return;
        const country = data.countries.find(entry => entry.code===selected);
        panel.querySelector('[data-geo-events-title]').textContent = country ? 'Eventos recientes · '+country.name : 'Eventos recientes';
        clear.hidden = !country;
        const visible = country ? data.events.filter(event => event.country?.code===selected) : data.events;
        const rows = visible.map(event => {
            const row = document.createElement('tr');
            const decision = event.disposition==='BLOCK' ? 'Bloqueada' : 'Observada';
            for (const value of [event.created_at,event.ip_address,event.country?.name ?? 'Sin ubicar',
                event.method+' '+event.target_url,decision+(event.rule_ids ? ' · '+event.rule_ids : '')]) {
                row.insertCell().textContent = value;
            }
            return row;
        });
        if (!rows.length) {
            const row = document.createElement('tr');
            const message = row.insertCell();
            message.colSpan = 5;
            message.textContent = country ? 'No hay eventos de este país entre los 12 más recientes.' : 'Sin eventos en los últimos 30 minutos.';
            rows.push(row);
        }
        events.replaceChildren(...rows);
    }

    function render(next) {
        data = next;
        if (!data.countries.some(country => country.code===selected)) selected='';
        for (const key of ['last_30','last_5']) {
            const limited = key==='last_5' ? data.last_5_limited : data.limited;
            panel.querySelector('[data-geo-count="'+key+'"]').textContent = number.format(data[key]) + (limited ? '+' : '');
        }
        panel.querySelector('[data-geo-unknown]').textContent = !data.available ? 'Base de geolocalización no disponible.'
            : data.unknown ? number.format(data.unknown)+' sin ubicación estimada.' : '';
        const focusedCode = list.contains(document.activeElement) ? document.activeElement.dataset.country : '';
        list.replaceChildren(...data.countries.map(country => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.country = country.code;
            button.setAttribute('aria-pressed',String(country.code===selected));
            const count = document.createElement('strong');
            count.textContent = number.format(country.count);
            button.append(country.name,count);
            item.append(button);
            return item;
        }));
        if (focusedCode) [...list.querySelectorAll('button')].find(button => button.dataset.country===focusedCode)?.focus({preventScroll:true});
        draw();
        renderEvents();
    }

    async function refresh() {
        if (document.hidden || busy) return;
        busy = true;
        try {
            const response = await fetch(panel.dataset.geoUrl,{headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok) throw Error('Activity unavailable');
            render(await response.json());
            status.textContent = 'Actualizado '+new Date().toLocaleTimeString();
        } catch {
            status.textContent = 'No se pudo actualizar la actividad';
        } finally {
            busy = false;
        }
    }

    list.addEventListener('click',event => {
        const button = event.target.closest('[data-country]');
        if (button) choose(button.dataset.country);
    });
    clear?.addEventListener('click',() => choose(selected));
    canvas.addEventListener('click',event => {
        if (!world) return;
        const rect = canvas.getBoundingClientRect();
        const x = (event.clientX-rect.left)*world.width/rect.width;
        const y = (event.clientY-rect.top)*world.height/rect.height;
        const marker = markers.findLast(point => Math.hypot(point.x-x,point.y-y)<=point.radius);
        if (marker) choose(marker.code);
    });
    cartography.then(result => {
        world = result;
        if (!world) {
            empty.textContent = 'Cartografía no disponible. Consulta la lista de países.';
            empty.hidden = false;
        }
        draw();
    });
    new ResizeObserver(draw).observe(canvas.parentElement);
    document.addEventListener('visibilitychange',refresh);
    refresh();
    setInterval(refresh,15000);
}
