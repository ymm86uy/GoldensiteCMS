(() => {
    const frame=document.getElementById('nx-studio-preview'),code=document.getElementById('nx-studio-code'),status=document.getElementById('nx-studio-preview-status');
    if(!frame||!code||!status)return;
    function render(){
        const doc=new DOMParser().parseFromString(code.value||'<p>Tu diseño aparecerá aquí.</p>','text/html');
        doc.querySelectorAll('script,base,iframe,object,embed,meta[http-equiv],link').forEach(node=>node.remove());
        doc.querySelectorAll('a,area,form,[ping]').forEach(node=>{for(const attr of ['href','action','ping','target','formaction'])node.removeAttribute(attr)});
        const policy=doc.createElement('meta');policy.httpEquiv='Content-Security-Policy';
        policy.content="default-src 'none'; style-src 'unsafe-inline'; img-src "+location.origin+"/uploads/ data:; base-uri 'none'; form-action 'none'";
        doc.head.prepend(policy);
        doc.querySelectorAll('.gs-form-slot').forEach(slot=>{if(/^\[goldensite_form id="\d+"\]$/.test(slot.textContent.trim())){slot.textContent='Tu formulario de Form Builder aparecerá aquí al publicar.';slot.classList.add('gs-form-preview')}});
        frame.srcdoc='<!doctype html>'+doc.documentElement.outerHTML;
        status.textContent='Vista aislada: imágenes locales permitidas; scripts, enlaces y envío de formularios bloqueados.';
    }
    document.getElementById('nx-studio-update')?.addEventListener('click',render);
    code.addEventListener('input',()=>status.textContent='Cambios en el editor. Actualiza la vista previa para verlos.');
    document.addEventListener('goldensite:studio-preview',event=>{if(typeof event.detail?.html==='string'&&event.detail.html.length<=1048576){code.value=event.detail.html;render()}});
    const buttons=document.querySelectorAll('[data-preview-width]');
    buttons.forEach(button=>button.addEventListener('click',()=>{frame.style.width=button.dataset.previewWidth;buttons.forEach(item=>item.setAttribute('aria-pressed',String(item===button)))}));
    render();
})();
