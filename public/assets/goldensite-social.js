'use strict';
const social = document.querySelector('[data-social]');
const admin = document.querySelector('[data-social-admin]');
function element(tag,text,className) {
    const node=document.createElement(tag);
    if(text) node.textContent=text;
    if(className) node.className=className;
    return node;
}
async function request(url,form) {
    const response=await fetch(url,form?{method:'POST',body:form}:{});
    const result=await response.json().catch(()=>({}));
    if(!response.ok) throw new Error(result.error||'No se pudo completar la solicitud.');
    return result;
}
if(admin) admin.addEventListener('submit',async event=>{
    event.preventDefault();
    try { await request(location.pathname,new FormData(event.target)); location.reload(); }
    catch(error){ admin.querySelector('[data-social-status]').textContent=error.message; }
});
if(social) {
    const status=social.querySelector('[data-social-status]');
    const feed=social.querySelector('[data-echoes]');
    const more=social.querySelector('[data-more]');
    const mapFrame=social.querySelector('[data-social-map]');
    const canvas=mapFrame.querySelector('canvas');
    const markers=mapFrame.querySelector('[data-social-markers]');
    const selection=social.querySelector('[data-map-selection]');
    const heading=selection.querySelector('[data-map-heading]');
    const choices=selection.querySelector('[data-map-choices]');
    const preview=selection.querySelector('[data-map-preview]');
    const world=request('/assets/admin/world-map.json').then(geo=>{
        const ctx=canvas.getContext('2d');
        if(ctx){
            ctx.fillStyle='#2e2b54';
            ctx.strokeStyle='#63658d';
            for(const path of geo.paths){
                const shape=new Path2D(path);
                ctx.fill(shape,'evenodd');
                ctx.stroke(shape);
            }
        }
        return geo;
    }).catch(()=>null);
    const names=new Intl.DisplayNames([document.documentElement.lang||'es'],{type:'region'});
    for(const option of social.querySelectorAll('select[name=region] option[value]:not([value=""])')) option.textContent=names.of(option.value);
    let cursor=null;
    let busy=false;
    let geo=null;
    let mapped=[];
    let mapRequest=0;
    let selected=[];
    let opened=null;
    let previewRequest=0;

    function mediaLabel(echo){
        if(Number(echo.audio)&&Number(echo.image)) return 'Audio e imagen';
        if(Number(echo.audio)) return 'Audio';
        if(Number(echo.image)) return 'Imagen';
        return 'Texto';
    }

    function echoLink(echo){
        const button=element('button',echo.title+' · '+names.of(echo.region)+' · '+mediaLabel(echo));
        button.type='button';
        button.dataset.mapEcho=echo.id;
        button.onclick=()=>choose([echo]);
        return button;
    }

    async function showEcho(id){
        const pending=++previewRequest;
        opened=id;
        preview.replaceChildren(element('p','Cargando eco…'));
        try {
            const {echo}=await request('/api/social/echoes/'+encodeURIComponent(id));
            if(pending===previewRequest) preview.replaceChildren(echoCard(echo));
        } catch(error) {
            if(pending===previewRequest) preview.replaceChildren(element('p',error.message));
        }
    }

    function choose(echoes){
        selected=echoes.map(echo=>echo.id);
        opened=null;
        previewRequest++;
        preview.replaceChildren();
        selection.hidden=false;
        heading.textContent=echoes.length===1?'Eco seleccionado':echoes.length+' ecos en esta zona del mapa';
        choices.replaceChildren(...(echoes.length>1?echoes.map(echo=>{
            const item=element('li');
            const button=echoLink(echo);
            button.onclick=()=>showEcho(echo.id);
            item.append(button);
            return item;
        }):[]));
        heading.focus();
        if(echoes.length===1) showEcho(echoes[0].id);
    }

    function drawMarkers(){
        if(!geo) return;
        const {width,height}=mapFrame.getBoundingClientRect();
        if(!width||!height) return;
        const clusters=[];
        for(const echo of mapped){
            const pos=echo.point||geo.positions[echo.region];
            if(!pos) continue;
            const x=Math.max(19,Math.min(width-19,pos[0]*width/geo.width));
            const y=Math.max(19,Math.min(height-19,pos[1]*height/geo.height));
            const cluster=clusters.find(group=>Math.hypot(x-group.x,y-group.y)<42);
            if(cluster) cluster.echoes.push(echo);
            else clusters.push({x,y,echoes:[echo]});
        }
        const focused=markers.contains(document.activeElement)?document.activeElement.dataset.mapEcho:null;
        markers.replaceChildren(...clusters.map(({x,y,echoes})=>{
            const echo=echoes[0];
            const multiple=echoes.length>1;
            const button=element('button',multiple?String(echoes.length):Number(echo.audio)?'♫':Number(echo.image)?'▧':'◌','gs-social-bubble');
            button.type='button';
            button.dataset.mapEcho=echo.id;
            button.dataset.zone=echoes.every(item=>item.point)?'approximate':echoes.some(item=>item.point)?'mixed':'country';
            button.style.left=x+'px';
            button.style.top=y+'px';
            const label=multiple?echoes.length+' ecos: '+[...new Set(echoes.map(item=>names.of(item.region)))].join(', ')
                :echo.title+' · '+names.of(echo.region)+' · '+mediaLabel(echo);
            button.title=label;
            button.setAttribute('aria-label',label);
            button.onclick=()=>choose(echoes);
            return button;
        }));
        if(focused) [...markers.children].find(button=>button.dataset.mapEcho===focused)?.focus({preventScroll:true});
    }

    async function map(){
        if(document.hidden) return;
        const pending=++mapRequest;
        const [data,cartography]=await Promise.all([request('/api/social/frequencies'),world]);
        if(pending!==mapRequest) return;
        geo=cartography;
        const {regions,echoes}=data;
        const list=social.querySelector('[data-social-regions]');
        const entries=regions.map(region=>element('li',names.of(region.region)+' · '+region.count+' participantes'));
        list.replaceChildren(...(entries.length?entries:[element('li','Todavía no hay suficiente actividad para mostrar una región.')]));
        social.querySelector('[data-map-empty]').hidden=echoes.length>0;
        const index=social.querySelector('[data-map-echoes]');
        if(JSON.stringify(mapped)!==JSON.stringify(echoes)){
            const focused=index.contains(document.activeElement)?document.activeElement.dataset.mapEcho:null;
            mapped=echoes;
            index.replaceChildren(...[...mapped].sort((a,b)=>names.of(a.region).localeCompare(names.of(b.region))).map(echo=>{
                const item=element('li');
                item.append(echoLink(echo));
                return item;
            }));
            if(focused) [...index.querySelectorAll('button')].find(button=>button.dataset.mapEcho===focused)?.focus({preventScroll:true});
            if(selected.some(id=>!mapped.some(echo=>echo.id===id))){
                const available=mapped.filter(echo=>selected.includes(echo.id));
                if(available.length) choose(available);
                else {
                    selection.hidden=true;
                    choices.replaceChildren();
                    preview.replaceChildren();
                    selected=[];
                    opened=null;
                    previewRequest++;
                }
            }
        }
        drawMarkers();
    }
    if(window.ResizeObserver) new ResizeObserver(drawMarkers).observe(mapFrame);
    else window.addEventListener('resize',drawMarkers);
    async function send(path,form){
        form.set('csrf_token',social.dataset.token);
        return request('/api/social/'+path,form);
    }
    function echoCard(echo){
        const card=element('article',null,'gs-social-echo');
        const path='echoes/'+encodeURIComponent(echo.id);
        const halo=element('div',null,'gs-social-orb');
        halo.setAttribute('aria-hidden','true');
        card.append(halo,element('p',echo.username||'Cuenta retirada','gs-social-author'),element('h3',echo.title),element('p',echo.body),element('p','Halo · '+echo.halo+' participaciones','gs-social-author'));
        if(echo.region) card.append(element('p',names.of(echo.region),'gs-social-author'));
        if(Number(echo.image)){
            const image=element('img',null,'gs-social-image');
            image.alt=echo.image_alt||'Imagen de '+echo.title;
            image.loading='lazy';
            image.src='/api/social/'+path+'/image';
            card.append(image);
        }
        const details=element('details');
        details.append(element('summary','Ondas de conversación'));
        const waves=element('ul');
        const form=element('form');
        const label=element('label','Escribe una onda');
        const field=element('textarea');field.name='body';field.maxLength=500;field.required=true;
        label.append(field);form.append(label,element('button','Enviar onda'));
        async function readWaves(){
            const data=await request('/api/social/'+path+'/waves');
            waves.replaceChildren(...data.waves.map(wave=>element('li',(wave.username||'Cuenta retirada')+' · '+wave.body)));
        }
        details.addEventListener('toggle',()=>{if(details.open) readWaves().catch(error=>{status.textContent=error.message;});});
        form.addEventListener('submit',async event=>{
            event.preventDefault();
            try {await send(path+'/waves',new FormData(form)); form.reset();await readWaves();}
            catch(error){status.textContent=error.message;}
        });
        details.append(waves,form);
        if(Number(echo.audio)) {
            const audio=element('audio');audio.controls=true;audio.preload='none';audio.src='/api/social/'+path+'/audio';card.append(audio);
        }
        const begin=element('button','Iniciar escucha consciente');
        const finish=element('button','Registrar resonancia');finish.disabled=true;
        let start=null;
        let startEpoch=0;
        begin.onclick=()=>{start=performance.now();startEpoch=visibilityEpoch;finish.disabled=false;status.textContent='Resonancia iniciada. Regístrala después de cinco segundos.';};
        finish.onclick=async()=>{
            if(start===null||startEpoch!==visibilityEpoch){
                start=null;
                finish.disabled=true;
                status.textContent='Inicia la escucha de nuevo.';
                return;
            }
            const seconds=Math.min(120,Math.floor((performance.now()-start)/1000));
            if(seconds<5){status.textContent='Espera al menos cinco segundos.';return;}
            const input=new FormData();input.set('seconds',seconds);
            try {await send(path+'/resonance',input);start=null;finish.disabled=true;status.textContent='Resonancia registrada.';}
            catch(error){status.textContent=error.message;}
        };
        const actions=element('div',null,'gs-social-actions');actions.append(begin,finish);card.append(actions,details);
        if(Number(echo.own)&&echo.region){
            const privacy=element('div',null,'gs-social-actions');
            for(const [mode,label] of [['country','Mostrar solo el país'],['none','Quitar ubicación']]){
                if(mode==='country'&&!Number(echo.approximate)) continue;
                const button=element('button',label);
                button.type='button';
                button.onclick=async()=>{
                    button.disabled=true;
                    try {
                        const form=new FormData();
                        form.set('location_mode',mode);
                        await send(path+'/location',form);
                        status.textContent='Privacidad del eco actualizada.';
                        await load(true);
                        await map();
                        if(opened===echo.id) await showEcho(echo.id);
                    } catch(error){status.textContent=error.message;}
                    finally {button.disabled=false;}
                };
                privacy.append(button);
            }
            card.append(privacy);
        }
        return card;
    }
    async function load(reset){
        if(busy) return;
        busy=true;
        try {
            const data=await request('/api/social/echoes'+(!reset&&cursor?'?before='+encodeURIComponent(cursor):''));
            if(reset) feed.replaceChildren();
            feed.append(...data.echoes.map(echoCard));
            if(!feed.childElementCount) feed.append(element('p','Aún no hay ecos publicados.'));
            cursor=data.before;more.hidden=!cursor;
        } catch(error){status.textContent=error.message;}
        finally {busy=false;}
    }
    more.onclick=()=>load(false);
    let visibilityEpoch=0;
    document.addEventListener('visibilitychange',()=>{
        if(document.hidden) visibilityEpoch++;
        else map().catch(()=>{});
    });
    const form=social.querySelector('[data-echo]');
    const mode=form.elements.location_mode;
    const country=form.elements.region;
    const locate=form.querySelector('[data-locate]');
    const locationStatus=form.querySelector('[data-location-status]');
    let zone=null;
    let locationRequest=0;
    function updateLocation(){
        zone=null;
        locationRequest++;
        locate.disabled=false;
        const share=mode.value!=='none';
        country.disabled=!share;
        country.required=share;
        form.querySelector('[data-echo-country]').hidden=!share;
        form.querySelector('[data-echo-zone]').hidden=mode.value!=='approximate';
        locationStatus.textContent='';
    }
    mode.addEventListener('change',updateLocation);
    updateLocation();
    locate.onclick=()=>{
        if(!window.isSecureContext||!navigator.geolocation){
            locationStatus.textContent='La ubicación aproximada requiere HTTPS y un navegador compatible. Puedes elegir solo país.';
            return;
        }
        zone=null;
        locate.disabled=true;
        const pending=++locationRequest;
        locationStatus.textContent='Calculando zona…';
        navigator.geolocation.getCurrentPosition(({coords})=>{
            if(pending!==locationRequest) return;
            locate.disabled=false;
            if(coords.accuracy>5000){
                locationStatus.textContent='La señal es demasiado imprecisa. Intenta de nuevo o elige solo país.';
                return;
            }
            const row=Math.min(1999,Math.floor((coords.latitude+90)*2000/180));
            const latitude=-90+(row+.5)*180/2000;
            const columns=Math.max(1,Math.round(4000*Math.cos(latitude*Math.PI/180)));
            const col=Math.min(columns-1,Math.floor((coords.longitude+180)*columns/360));
            zone={row,col};
            locationStatus.textContent='Zona calculada. Se compartirá al publicar este eco.';
        },()=>{
            if(pending!==locationRequest) return;
            locate.disabled=false;
            locationStatus.textContent='No se pudo obtener el permiso o la ubicación. Puedes elegir solo país.';
        },{enableHighAccuracy:false,timeout:8000,maximumAge:0});
    };
    form.addEventListener('submit',async event=>{
        event.preventDefault();
        const data=new FormData(form);
        if(mode.value==='approximate'){
            if(!zone){locationStatus.textContent='Calcula primero tu zona o elige otro nivel de privacidad.';locate.focus();return;}
            data.set('zone_row',zone.row);
            data.set('zone_col',zone.col);
        }
        const publish=form.querySelector('button[type=submit]');
        publish.disabled=true;
        try {
            await send('echoes',data);
            status.textContent='Eco publicado.';
            form.reset();
            updateLocation();
            await load(true);
            await map();
        } catch(error){status.textContent=error.message;}
        finally{publish.disabled=false;}
    });
    social.querySelector('[data-presence]').addEventListener('submit',async event=>{
        event.preventDefault();
        try {await send('presence',new FormData(event.target));status.textContent='Preferencia de presencia guardada.';await map();}
        catch(error){status.textContent=error.message;}
    });
    load(true);
    map().catch(error=>{status.textContent=error.message;});
    setInterval(()=>map().catch(()=>{}),30000);
}
