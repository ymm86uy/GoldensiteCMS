(()=>{
    const form=document.querySelector('[data-guided-studio]');if(!form)return;
    const status=document.querySelector('[data-guided-status]'),uploadStatus=document.querySelector('[data-upload-status]');let dirty=false,timer,controller,sequence=0,uploading=false;
    const token=form.elements.namedItem('csrf_token').value;
    const message=text=>{status.textContent=text};
    async function preview(){
        const serial=++sequence;controller?.abort();controller=new AbortController();
        const data=new FormData(form);data.delete('current_password');data.set('action','preview_guided');
        message('Actualizando vista previa…');
        try{const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});const result=await response.json().catch(()=>{throw new Error("No se pudo completar la solicitud. Revisa la conexión y el WAF.")});
            if(serial!==sequence)return;if(!response.ok||!result.html)throw new Error(result.error||'No se pudo actualizar la vista previa.');
            document.dispatchEvent(new CustomEvent('goldensite:studio-preview',{detail:{html:result.html}}));message('Cambios sin guardar · vista previa actualizada');
        }catch(error){if(error.name!=='AbortError'&&serial===sequence)message(error.message||'No se pudo actualizar la vista previa.')}
    }
    function changed(){dirty=true;message('Cambios sin guardar');clearTimeout(timer);timer=setTimeout(preview,850)}
    form.addEventListener('input',event=>{if(event.target.name!=='current_password'&&event.target.name!=='replace_draft')changed()});
    form.addEventListener('change',event=>{if(event.target.name!=='current_password'&&event.target.name!=='replace_draft')changed()});
    form.querySelectorAll('[data-move-section]').forEach(button=>button.addEventListener('click',()=>{const section=button.closest('[data-studio-section]'),parent=section.parentElement;if(button.dataset.moveSection==='up'&&section.previousElementSibling)parent.insertBefore(section,section.previousElementSibling);if(button.dataset.moveSection==='down'&&section.nextElementSibling)parent.insertBefore(section.nextElementSibling,section);button.focus();changed()}));
    document.querySelector('[data-studio-upload-button]').addEventListener('click',async()=>{
        const input=document.querySelector('[data-studio-upload]'),file=input.files[0];
        if(!file){uploadStatus.textContent='Selecciona primero una imagen.';return}if(file.size>5242880){uploadStatus.textContent='Elige una imagen de hasta 5 MB.';return}
        if(uploading)return;uploading=true;uploadStatus.textContent='Validando y subiendo imagen…';
        try{const data=new FormData();data.set('csrf_token',token);data.set('action','upload_image');data.set('studio_image',file);
            const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});const result=await response.json().catch(()=>{throw new Error("No se pudo completar la solicitud. Revisa la conexión y el WAF.")});
            if(!response.ok||!result.url)throw new Error(result.error||'La protección de cargas no permitió subir la imagen. Revisa el WAF.');
            const target=document.querySelector('[data-studio-upload-target]').value;
            for(const key of ['image','logo']){const select=form.elements.namedItem(key),option=document.createElement('option');option.value=result.url;option.textContent=file.name;select.append(option);if(key===target)select.value=result.url}
            uploadStatus.textContent=target==='image'?'Imagen guardada. Añade una descripción para mostrarla en el diseño.':'Logotipo guardado en tu biblioteca.';input.value='';form.elements.namedItem(target==='image'?'image_alt':'name').focus();changed();
        }catch(error){uploadStatus.textContent=error.message||'No se pudo subir la imagen.'}finally{uploading=false}
    });
    form.addEventListener('submit',event=>{
        if(uploading){event.preventDefault();message('Espera a que termine la subida.');return}
        if(event.submitter?.value==='publish_guided'&&!form.elements.namedItem('current_password').value){event.preventDefault();form.elements.namedItem('current_password').focus();message('Introduce tu contraseña actual para publicar.');return}
        dirty=false;clearTimeout(timer);controller?.abort();
    });
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue=''}});
})();
