(()=>{const form=document.querySelector('[data-page-editor]');if(!form)return;const visual=form.querySelector('[data-page-visual]'),source=form.querySelector('[data-page-source]'),frame=document.querySelector('[data-page-preview-frame]');let mode='visual',dirty=false,savedRange;visual.addEventListener('input',()=>dirty=true);document.addEventListener('selectionchange',()=>{const s=getSelection();if(s.rangeCount&&visual.contains(s.anchorNode))savedRange=s.getRangeAt(0).cloneRange()});function sync(){if(mode==='visual'&&dirty)source.value=visual.innerHTML}function safeMarkup(html){const d=new DOMParser().parseFromString(html,'text/html');d.querySelectorAll('script,iframe,object,embed,style,link,meta,form,svg,math').forEach(n=>n.remove());d.querySelectorAll('*').forEach(n=>[...n.attributes].forEach(a=>{if(a.name.startsWith('on')||['srcdoc','style','formaction'].includes(a.name)||(['href','src'].includes(a.name)&&!/^\s*(https?:|mailto:|\/(?!\/)|#|[^:\/]+(?:\/|$))/i.test(a.value)))n.removeAttribute(a.name)}));return d.body.innerHTML}form.querySelectorAll('[data-editor-mode]').forEach(b=>b.addEventListener('click',()=>{if(mode===b.dataset.editorMode)return;sync();mode=b.dataset.editorMode;if(mode==='visual'){visual.innerHTML=safeMarkup(source.value);dirty=false}visual.hidden=mode!=='visual';source.hidden=mode!=='html'}));visual.addEventListener('paste',e=>{e.preventDefault();const s=getSelection();if(!s.rangeCount)return;const r=s.getRangeAt(0);r.deleteContents();r.insertNode(document.createTextNode(e.clipboardData.getData('text/plain')));dirty=true});visual.addEventListener('drop',e=>e.preventDefault());form.querySelectorAll('[data-format]').forEach(b=>b.addEventListener('click',()=>{if(mode!=='visual'||!savedRange||!visual.contains(savedRange.commonAncestorContainer)||savedRange.collapsed)return;visual.focus();const s=getSelection();s.removeAllRanges();s.addRange(savedRange);const r=s.getRangeAt(0);let node=document.createElement(b.dataset.format);if(b.dataset.format==='ul'){const li=document.createElement('li');li.append(r.extractContents());node.append(li)}else node.append(r.extractContents());r.insertNode(node);dirty=true}));function preview(){sync();const title=form.querySelector('[name=title]').value;const h=document.createElement('h1');h.textContent=title;frame.srcdoc='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+frame.dataset.themeHead+'<style>body{padding:32px;font-family:system-ui}.nx-editorial{max-width:960px;margin:auto}</style></head><body class="nx-public"><article class="nx-editorial">'+h.outerHTML+source.value+'</article></body></html>'}form.querySelectorAll('[data-page-block]').forEach(button=>button.addEventListener('click',()=>{
    if(mode!=='visual') form.querySelector('[data-editor-mode="visual"]').click();
    const block=document.createElement('section');
    const heading=document.createElement('h2');
    heading.textContent='Título de la sección';
    const body=document.createElement(button.dataset.pageBlock==='list'?'ul':'p');
    if(body.tagName==='UL'){
        const item=document.createElement('li');
        item.textContent='Elemento de la lista';
        body.append(item);
    }else body.textContent='Escribe el contenido de esta sección.';
    block.append(heading,body);
    visual.append(block);
    dirty=true;
    visual.focus();
    const range=document.createRange();
    range.selectNodeContents(heading);
    const selection=getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}));
document.querySelectorAll('[data-page-preview-width]').forEach(button=>button.addEventListener('click',()=>{
    frame.style.maxWidth=button.dataset.pagePreviewWidth;
    document.querySelectorAll('[data-page-preview-width]').forEach(option=>option.setAttribute('aria-pressed',String(option===button)));
}));
form.querySelector('[data-page-preview]').addEventListener('click',preview);form.addEventListener('submit',sync);preview()})();
