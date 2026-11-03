(function () {
    const cfg = window.SIGNING;
    const $ = s => document.querySelector(s);
    const values = {};          // field id -> value
    const adopted = {};         // 'signature' | 'initials' -> data URL
    const els = {};             // field id -> element
    let current = null;         // field being signed
    let mode = 'draw';

    // ---------- Field rendering ----------
    function renderField(f, overlay) {
        const el = document.createElement('div');
        el.className = 'fld';
        Object.assign(el.style, { left: f.x * 100 + '%', top: f.y * 100 + '%', width: f.width * 100 + '%', height: f.height * 100 + '%' });
        els[f.id] = el;

        if (f.type === 'date' || f.type === 'name') {
            el.classList.add('done');
            el.innerHTML = `<span style="color:#1d2433;font-weight:500">${f.type === 'date' ? cfg.today : escapeHtml(cfg.name)}</span>`;
        } else if (f.type === 'text') {
            const input = document.createElement('input');
            input.type = 'text';
            input.maxLength = 255;
            input.placeholder = f.required ? 'Required' : 'Optional';
            input.addEventListener('input', () => { values[f.id] = input.value; el.classList.toggle('done', !!input.value); update(); });
            el.append(input);
            el.style.cursor = 'text';
        } else {
            el.innerHTML = `<span>${f.type === 'signature' ? '✍ Sign here' : 'Initial'}${f.required ? '' : ' (optional)'}</span>`;
            el.addEventListener('click', () => {
                if (!values[f.id] && adopted[f.type]) setImage(f, adopted[f.type]);
                else openPad(f); // first time, or click again to re-draw
            });
        }
        overlay.append(el);
    }

    function setImage(f, dataUrl) {
        values[f.id] = dataUrl;
        const el = els[f.id];
        el.classList.add('done');
        el.innerHTML = `<img src="${dataUrl}" alt="${f.type}" title="Click to change">`;
        update();
    }

    function pending() {
        return cfg.fields.filter(f => f.required && !['date', 'name'].includes(f.type) && !values[f.id]);
    }

    function update() {
        const total = cfg.fields.filter(f => f.required && !['date', 'name'].includes(f.type)).length;
        const left = pending().length;
        $('#progress-text').textContent = left ? `${total - left} of ${total} required fields completed` : 'All required fields completed — click Finish';
        $('#next-btn').style.display = left ? '' : 'none';
    }

    // ---------- Signature pad ----------
    const pad = $('#pad');
    const ctx = pad.getContext('2d');
    let drawing = false, hasInk = false, last = null;

    function resizePad() {
        const r = pad.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        pad.width = r.width * ratio; pad.height = r.height * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#14143c';
        hasInk = false;
    }
    const pos = e => { const r = pad.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; };
    pad.addEventListener('pointerdown', e => { drawing = true; last = pos(e); pad.setPointerCapture(e.pointerId); ctx.beginPath(); ctx.arc(last.x, last.y, 1, 0, Math.PI * 2); ctx.fillStyle = '#14143c'; ctx.fill(); hasInk = true; });
    pad.addEventListener('pointermove', e => { if (!drawing) return; const p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; hasInk = true; });
    ['pointerup', 'pointerleave', 'pointercancel'].forEach(t => pad.addEventListener(t, () => drawing = false));
    $('#clear-pad').addEventListener('click', e => { e.preventDefault(); resizePad(); });

    function trimmed(canvas) {
        const c = canvas.getContext('2d');
        const { width, height } = canvas;
        const data = c.getImageData(0, 0, width, height).data;
        let minX = width, minY = height, maxX = -1, maxY = -1;
        for (let y = 0; y < height; y++) for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > 0) { if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y; }
        }
        if (maxX < 0) return null;
        const pad = 6, w = maxX - minX + 1 + pad * 2, h = maxY - minY + 1 + pad * 2;
        const out = document.createElement('canvas');
        const scale = Math.min(1, 1200 / w, 600 / h);
        out.width = Math.ceil(w * scale); out.height = Math.ceil(h * scale);
        out.getContext('2d').drawImage(canvas, minX - pad, minY - pad, w, h, 0, 0, out.width, out.height);
        return out.toDataURL('image/png');
    }

    function typedImage(text) {
        const c = document.createElement('canvas');
        c.width = 1200; c.height = 300;
        const x = c.getContext('2d');
        x.font = '600 140px "Dancing Script", cursive';
        x.fillStyle = '#14143c'; x.textBaseline = 'middle';
        x.fillText(text, 20, 150, 1160);
        return trimmed(c);
    }

    function setMode(m) {
        mode = m;
        document.querySelectorAll('#sig-modal [data-mode]').forEach(a => a.classList.toggle('active', a.dataset.mode === m));
        $('#mode-draw').style.display = m === 'draw' ? '' : 'none';
        $('#mode-type').style.display = m === 'type' ? '' : 'none';
        if (m === 'draw') resizePad(); else $('#typed').focus();
    }
    document.querySelectorAll('#sig-modal [data-mode]').forEach(a => a.addEventListener('click', e => { e.preventDefault(); setMode(a.dataset.mode); }));
    $('#typed').addEventListener('input', () => $('#type-preview').textContent = $('#typed').value);

    function openPad(f) {
        current = f;
        $('#sig-title').textContent = f.type === 'signature' ? 'Adopt your signature' : 'Adopt your initials';
        $('#typed').value = f.type === 'signature' ? cfg.name : cfg.initials;
        $('#type-preview').textContent = $('#typed').value;
        $('#sig-modal').classList.add('open');
        setMode(mode);
    }

    $('#adopt').addEventListener('click', async () => {
        let url;
        if (mode === 'draw') { url = hasInk ? trimmed(pad) : null; }
        else { await document.fonts.load('600 40px "Dancing Script"').catch(() => {}); url = $('#typed').value.trim() ? typedImage($('#typed').value.trim()) : null; }
        if (!url) { alert('Please provide your ' + (current.type === 'signature' ? 'signature' : 'initials') + '.'); return; }
        adopted[current.type] = url;
        setImage(current, url);
        // Apply the adopted mark to every other field of the same type that is still empty.
        cfg.fields.filter(f => f.type === current.type && !values[f.id]).forEach(f => setImage(f, url));
        $('#sig-modal').classList.remove('open');
    });

    // ---------- Navigation & submit ----------
    $('#next-btn').addEventListener('click', () => {
        const f = pending()[0];
        if (!f || !els[f.id]) return;
        els[f.id].scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (f.type === 'text') setTimeout(() => els[f.id].querySelector('input').focus(), 400);
    });

    $('#finish-btn').addEventListener('click', () => {
        if (pending().length) { alert('Please complete all required fields first.'); $('#next-btn').click(); return; }
        const box = $('#values');
        box.innerHTML = '';
        Object.entries(values).forEach(([id, v]) => {
            if (!v) return;
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = `values[${id}]`; i.value = v;
            box.append(i);
        });
        $('#finish-modal').classList.add('open');
    });

    document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => b.closest('.modal-bg').classList.remove('open')));
    document.querySelectorAll('.modal-bg').forEach(bg => bg.addEventListener('click', e => { if (e.target === bg) bg.classList.remove('open'); }));

    function escapeHtml(s) { return s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    update();
    PdfViewer.render(cfg.pdfUrl, $('#pages'), (n, overlay) => {
        cfg.fields.filter(f => f.page === n).forEach(f => renderField(f, overlay));
    }).catch(err => { $('#pages').innerHTML = '<div class="alert alert-error">Could not render PDF: ' + err.message + '</div>'; });
})();
