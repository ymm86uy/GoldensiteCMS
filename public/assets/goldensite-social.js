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
    const canvas=social.querySelector('canvas');
    const world=request('/assets/admin/world-map.json').catch(()=>null);
    const names=new Intl.DisplayNames([document.documentElement.lang||'es'],{type:'region'});
    for(const option of social.querySelectorAll('select[name=region] option[value]:not([value=""])')) option.textContent=names.of(option.value);
    let cursor=null;
    let busy=false;
    async function map(){
        if(document.hidden) return;
        const [{regions},geo]=await Promise.all([request('/api/social/frequencies'),world]);
        const list=social.querySelector('[data-social-regions]');
        const entries=regions.map(region=>element('li',names.of(region.region)+' · '+region.count+' participantes'));
        list.replaceChildren(...(entries.length?entries:[element('li','Todavía no hay suficiente actividad para mostrar una región.')]));
        if(!geo) return;
        const ctx=canvas.getContext('2d');
        if(!ctx) return;
        ctx.clearRect(0,0,900,365);
        ctx.fillStyle='#25244c';
        ctx.strokeStyle='#535482';
        for(const path of geo.paths){ const shape=new Path2D(path);ctx.fill(shape,'evenodd');ctx.stroke(shape); }
        for(const region of regions){
            const pos=geo.positions[region.region];
            if(!pos) continue;
            const [x,y]=pos;
            const glow=ctx.createRadialGradient(x,y,1,x,y,26);
            glow.addColorStop(0,'#a2f9ff');
            glow.addColorStop(.35,'#7960fd');
            glow.addColorStop(1,'#7960fd00');
            ctx.fillStyle=glow;ctx.fillRect(x-26,y-26,52,52);
        }
    }
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
        begin.onclick=()=>{start=performance.now();finish.disabled=false;status.textContent='Resonancia iniciada. Regístrala después de cinco segundos.';};
        document.addEventListener('visibilitychange',()=>{if(document.hidden){start=null;finish.disabled=true;}});
        finish.onclick=async()=>{
            if(start===null) return;
            const seconds=Math.min(120,Math.floor((performance.now()-start)/1000));
            if(seconds<5){status.textContent='Espera al menos cinco segundos.';return;}
            const input=new FormData();input.set('seconds',seconds);
            try {await send(path+'/resonance',input);start=null;finish.disabled=true;status.textContent='Resonancia registrada.';}
            catch(error){status.textContent=error.message;}
        };
        const actions=element('div',null,'gs-social-actions');actions.append(begin,finish);card.append(actions,details);
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
    for(const [selector,path,success] of [['[data-presence]','presence','Preferencia de presencia guardada.'],['[data-echo]','echoes','Eco publicado.']]){
        social.querySelector(selector).addEventListener('submit',async event=>{
            event.preventDefault();
            try {await send(path,new FormData(event.target));status.textContent=success;if(path==='echoes'){event.target.reset();await load(true);}else await map();}
            catch(error){status.textContent=error.message;}
        });
    }
    load(true);
    map().catch(error=>{status.textContent=error.message;});
    setInterval(()=>map().catch(()=>{}),30000);
}
