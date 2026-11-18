'use strict';
const kind = document.querySelector('[data-forum-kind]');
if (kind) {
    const update = () => { document.querySelector('[data-forum-poll]').hidden = kind.value !== 'poll'; };
    kind.addEventListener('change', update);
    update();
}
for (const editor of document.querySelectorAll('[data-forum-editor]')) {
    const textarea = editor.querySelector('textarea');
    const output = editor.querySelector('[data-preview-output]');
    editor.querySelector('[data-forum-tools]').hidden = false;
    for (const button of editor.querySelectorAll('[data-format]')) {
        button.addEventListener('click', () => {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selected = textarea.value.slice(start, end);
            const wrappers = { bold: '**', code: String.fromCharCode(96) };
            const marker = wrappers[button.dataset.format];
            textarea.setRangeText(marker ? marker + (selected || 'texto') + marker : '🙂', start, end, 'end');
            textarea.focus();
        });
    }
    editor.querySelector('[data-preview]').addEventListener('click', async () => {
        output.hidden = false;
        try {
            const csrf = editor.closest('form').elements.csrf_token.value;
            const response = await fetch('/forum/preview', {
                method: 'POST', body: new URLSearchParams({body:textarea.value, csrf_token:csrf}),
                headers: {'Accept':'application/json'}
            });
            if (!response.ok) throw new Error();
            const preview = await response.json();
            output.innerHTML = preview.html; // Same-origin server renderer escapes all user text.
        } catch {
            output.textContent = 'No se pudo generar la vista previa.';
        }
    });
    const drop = editor.querySelector('[data-forum-drop]');
    drop.addEventListener('dragover', event => event.preventDefault());
    drop.addEventListener('drop', event => {
        event.preventDefault();
        if (event.dataTransfer.files.length === 1) drop.querySelector('input').files = event.dataTransfer.files;
    });
}
const tags = document.querySelector('[data-forum-tags]');
if (tags) {
    let timer;
    tags.addEventListener('input', () => {
        clearTimeout(timer);
        const prefix = tags.value;
        const segments = prefix.split(',');
        const term = segments.pop().trim();
        timer = setTimeout(async () => {
            const list = tags.list;
            if (term.length < 2) { list.replaceChildren(); return; }
            try {
                const result = await fetch('/forum/tags?q=' + encodeURIComponent(term));
                if (!result.ok) return;
                const entries = await result.json();
                if (prefix === tags.value) list.replaceChildren(...entries.map(value => new Option([...segments.map(s => s.trim()), value].join(', '))));
            } catch {}
        }, 220);
    });
}
const unread = document.querySelector('[data-forum-unread]');
if (unread) {
    const refresh = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch('/forum/notifications/unread');
            if (!response.ok) return;
            const {unread:count} = await response.json();
            const label = count > 0 ? ' (' + count + ')' : '';
            if (unread.textContent !== label) unread.textContent = label;
        } catch {}
    };
    refresh();
    setInterval(refresh, 30000);
}
