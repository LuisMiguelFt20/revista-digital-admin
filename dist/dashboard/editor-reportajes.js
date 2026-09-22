(() => {
    'use strict';
    const root = document.getElementById('editor-reportajes');
    if (!root) return;
    const form = root.closest('form');
    const list = document.getElementById('editor-bloques');
    const status = document.getElementById('editor-estado');
    const undo = document.getElementById('editor-deshacer');
    const removed = [];
    let sequence = 0;
    function element(tag, className, text) {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text) el.textContent = text;
        return el;
    }
    function refresh() {
        [...list.children].forEach((block, index) => {
            block.querySelector('strong').textContent = `${index + 1}. ${block.dataset.tipo === 'texto' ? 'Texto' : 'Imagen'}`;
            block.querySelector('[data-move="up"]').disabled = index === 0;
            block.querySelector('[data-move="down"]').disabled = index === list.children.length - 1;
        });
        undo.disabled = !removed.length;
    }
    function add(data, initial = false) {
        if (!initial && list.children.length >= 100) { status.textContent = 'Máximo 100 bloques.'; return; }
        const key = `b${++sequence}`;
        const block = element('section', 'eb-bloque');
        block.dataset.tipo = data.tipo;
        block.dataset.clave = key;
        block.dataset.id = data.id || 0;
        const bar = element('div', 'eb-barra');
        bar.append(element('strong'));
        const buttons = element('div', 'eb-botones');
        for (const [action, title] of [['up','↑ Subir'], ['down','↓ Bajar'], ['remove','Quitar']]) {
            const button = element('button', 'btn btn-sm btn-outline-secondary', title);
            button.type = 'button'; button.dataset.move = action;
            button.addEventListener('click', () => {
                if (action === 'up' && block.previousElementSibling) list.insertBefore(block, block.previousElementSibling);
                if (action === 'down' && block.nextElementSibling) list.insertBefore(block.nextElementSibling, block);
                if (action === 'remove') { removed.push({block, index:[...list.children].indexOf(block)}); block.remove(); }
                refresh(); status.textContent = 'Orden actualizado. Guarda para aplicar los cambios.';
            });
            buttons.append(button);
        }
        bar.append(buttons); block.append(bar);
        if (data.tipo === 'texto') {
            const label = element('label', '', 'Texto del reportaje'); label.htmlFor = key;
            const area = element('textarea', 'form-control'); area.id = key; area.value = data.texto || '';
            area.placeholder = 'Escribe o pega aquí los párrafos…';
            block.append(label, area);
        } else {
            const image = element('img'); image.alt = 'Vista previa de la fotografía';
            // Las rutas de imágenes existentes provienen del servidor.
            if (data.url && data.url.startsWith('../uploads/')) image.src = data.url;
            else image.hidden = true;
            const label = element('label', '', 'Seleccionar imagen'); label.htmlFor = key;
            const file = element('input', 'form-control'); file.type = 'file'; file.id = key;
            file.name = `foto_${key}`; file.accept = 'image/jpeg,image/png,image/webp'; file.required = !Number(data.id);
            let previewUrl;
            file.addEventListener('change', () => {
                file.setCustomValidity('');
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                const chosen = file.files[0];
                if (chosen) {
                    if (chosen.size > 10 * 1024 * 1024) file.setCustomValidity('La imagen supera los 10 MB.');
                    if (!['image/jpeg','image/png','image/webp'].includes(chosen.type)) file.setCustomValidity('Selecciona JPG, PNG o WEBP.');
                    previewUrl = URL.createObjectURL(chosen); image.src = previewUrl; image.hidden = false;
                } else if (data.url) { image.src = data.url; image.hidden = false; }
                else { image.removeAttribute('src'); image.hidden = true; }
            });
            const captionLabel = element('label', '', 'Descripción debajo de la imagen'); captionLabel.htmlFor = `${key}-descripcion`;
            const caption = element('input', 'form-control'); caption.type = 'text'; caption.maxLength = 255;
            caption.id = `${key}-descripcion`; caption.value = data.descripcion || ''; caption.dataset.caption = '1';
            block.append(label, file, element('small','text-muted','JPG, PNG o WEBP. Máximo 10 MB.'), image, captionLabel, caption);
        }
        list.append(block); refresh(); return block;
    }
    document.getElementById('agregar-texto').addEventListener('click', () => add({tipo:'texto'})?.querySelector('textarea').focus());
    document.getElementById('agregar-foto').addEventListener('click', () => {
        if (list.querySelectorAll('[data-tipo="foto"]').length >= 18) { status.textContent = 'Máximo 18 imágenes por reportaje.'; return; }
        add({tipo:'foto'})?.querySelector('input[type="file"]').click();
    });
    undo.addEventListener('click', () => {
        const item = removed.pop(); if (!item) return;
        list.insertBefore(item.block, list.children[item.index] || null); refresh();
    });
    JSON.parse(document.getElementById('editor-datos').textContent).forEach(data => add(data, true));
    document.getElementById('editor-listo').value = '1';
    form.addEventListener('submit', event => {
        const blocks = [...list.children].map(block => block.dataset.tipo === 'texto'
            ? {tipo:'texto', texto:block.querySelector('textarea').value}
            : {tipo:'foto', clave:block.dataset.clave, id:Number(block.dataset.id), descripcion:block.querySelector('[data-caption]').value});
        if (!blocks.some(b => b.tipo === 'texto' && b.texto.trim())) {
            event.preventDefault(); status.textContent = 'Escribe al menos un bloque de texto.'; return;
        }
        if (blocks.some(b => b.tipo === 'texto' && /\[foto:[1-9][0-9]*\]/i.test(b.texto))) {
            event.preventDefault(); status.textContent = 'Inserta las fotos con Agregar imagen, sin escribir marcas.'; return;
        }
        document.getElementById('bloques-json').value = JSON.stringify(blocks);
        status.textContent = 'Guardando…';
    });
})();
