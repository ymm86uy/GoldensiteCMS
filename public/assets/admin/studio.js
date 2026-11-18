(() => {
    const frame = document.getElementById('nx-studio-preview');
    const code = document.getElementById('nx-studio-code');
    const status = document.getElementById('nx-studio-preview-status');
    if (!frame || !code || !status) return;

    const buttons = document.querySelectorAll('[data-preview-width]');
    const render = () => {
        const html = code.value;
        const template = document.createElement('template');
        template.innerHTML = html;
        template.content.querySelectorAll('script,base,iframe,object,embed,meta[http-equiv],link').forEach(node => node.remove());
        template.content.querySelectorAll('a,area,form,[ping]').forEach(node => {
            for (const attribute of ['href', 'action', 'ping', 'target']) node.removeAttribute(attribute);
        });
        const bodyClass = html.match(/<body\b[^>]*\bclass\s*=\s*["']([a-zA-Z0-9_ -]{0,120})["']/i)?.[1] ?? '';
        const policy = "<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'none'; style-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'\">";
        frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
            policy + '</head><body class="' + bodyClass + '">' + (template.innerHTML || '<p>Genera un borrador para ver la composición.</p>') + '</body></html>';
        status.textContent = 'Vista previa actualizada. Los scripts y recursos externos están bloqueados.';
    };

    document.getElementById('nx-studio-update').addEventListener('click', render);
    code.addEventListener('input', () => {
        status.textContent = 'Cambios en el editor. Actualiza la vista previa para verlos.';
    });
    buttons.forEach(button => button.addEventListener('click', () => {
        frame.style.width = button.dataset.previewWidth;
        buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    }));
    render();
})();
