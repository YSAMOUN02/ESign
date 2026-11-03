(function () {
    const cfg = window.EDITOR;
    const DEFAULT_SIZE = { signature: [0.24, 0.06], initials: [0.09, 0.05], date: [0.16, 0.03], text: [0.22, 0.03], name: [0.22, 0.03] };
    const state = {
        signers: cfg.setup.signers.map(s => ({ ...s })),
        fields: cfg.setup.fields.map(f => ({ ...f, required: f.required !== false })),
        activeSigner: null,
        placing: null,
        selected: null,
    };
    let uid = Date.now();
    const overlays = {};
    const $ = s => document.querySelector(s);
    const signerList = $('#signer-list');

    const colorOf = key => ESIGN_COLORS[Math.max(0, state.signers.findIndex(s => s.key === key)) % ESIGN_COLORS.length];
    const clamp = (v, min, max) => Math.min(max, Math.max(min, v));

    function addSigner(name = '', email = '') {
        const order = state.signers.length ? Math.max(...state.signers.map(s => s.order)) + 1 : 1;
        const s = { key: 'n' + (uid++), name, email, order };
        state.signers.push(s);
        state.activeSigner = s.key;
        renderSigners();
        signerList.querySelector(`[data-key="${s.key}"] input`)?.focus();
    }

    function renderSigners() {
        if (!state.signers.find(s => s.key === state.activeSigner)) state.activeSigner = state.signers[0]?.key ?? null;
        signerList.innerHTML = '';
        if (!state.signers.length) signerList.innerHTML = '<p class="small muted">No signers yet.</p>';
        state.signers.forEach(s => {
            const el = document.createElement('div');
            el.className = 'signer-item' + (s.key === state.activeSigner ? ' active' : '');
            el.dataset.key = s.key;
            el.style.setProperty('--c', colorOf(s.key));
            const count = state.fields.filter(f => f.signer === s.key).length;
            el.innerHTML = `
                <input type="text" placeholder="Full name" data-prop="name">
                <input type="email" placeholder="Email address" data-prop="email">
                <div class="row between small">
                    <span class="seq-only">Order <input type="number" min="1" max="20" data-prop="order" style="width:58px;display:inline-block;margin:0"></span>
                    <span class="muted">${count} field(s)</span>
                    <button type="button" class="btn btn-sm btn-danger" data-remove>Remove</button>
                </div>`;
            el.querySelectorAll('input').forEach(inp => {
                inp.value = s[inp.dataset.prop];
                inp.addEventListener('input', () => { s[inp.dataset.prop] = inp.dataset.prop === 'order' ? parseInt(inp.value || 1, 10) : inp.value; renderFields(); });
            });
            el.addEventListener('click', e => {
                if (e.target.closest('[data-remove]')) {
                    if (count && !confirm('Remove this signer and their ' + count + ' field(s)?')) return;
                    state.signers = state.signers.filter(x => x !== s);
                    state.fields = state.fields.filter(f => f.signer !== s.key);
                    renderSigners(); renderFields();
                    return;
                }
                if (state.activeSigner !== s.key) { state.activeSigner = s.key; renderSigners(); }
            });
            signerList.append(el);
        });
        toggleSequential();
        $('#palette-hint').style.display = state.signers.length ? 'none' : 'block';
        document.querySelectorAll('.palette button').forEach(b => b.disabled = !state.signers.length);
    }

    function toggleSequential() {
        const on = $('#sequential').checked;
        document.querySelectorAll('.seq-only').forEach(e => e.style.visibility = on ? 'visible' : 'hidden');
    }

    function renderFields() {
        Object.values(overlays).forEach(o => o.innerHTML = '');
        state.fields.forEach(f => {
            const overlay = overlays[f.page];
            if (!overlay) return;
            const signer = state.signers.find(s => s.key === f.signer);
            const el = document.createElement('div');
            el.className = 'fld' + (state.selected === f ? ' selected' : '');
            el.style.setProperty('--c', colorOf(f.signer));
            Object.assign(el.style, { left: f.x * 100 + '%', top: f.y * 100 + '%', width: f.width * 100 + '%', height: f.height * 100 + '%' });
            el.title = (signer?.name || 'Signer') + ' — ' + f.type;
            el.innerHTML = `<span>${ESIGN_LABELS[f.type]}${f.required || ['date','name','signature'].includes(f.type) ? '' : ' (opt.)'}</span><button type="button" class="del" title="Delete">×</button><span class="rs"></span>`;
            el.querySelector('.del').addEventListener('pointerdown', e => { e.stopPropagation(); });
            el.querySelector('.del').addEventListener('click', e => {
                e.stopPropagation();
                state.fields = state.fields.filter(x => x !== f);
                state.selected = null;
                renderFields(); renderSigners();
            });
            el.addEventListener('pointerdown', e => startDrag(e, f, el, overlay, e.target.classList.contains('rs')));
            overlay.append(el);
        });
        renderFieldOptions();
    }

    function renderFieldOptions() {
        const box = $('#field-options');
        const f = state.selected;
        if (!f || !state.fields.includes(f)) { box.innerHTML = '<p class="small muted">Select a field on the page to edit it.</p>'; return; }
        box.innerHTML = `
            <div class="field"><label>Assigned to</label><select id="fo-signer">${state.signers.map(s => `<option value="${s.key}">${escapeHtml(s.name || s.email || 'Unnamed signer')}</option>`).join('')}</select></div>
            ${['text', 'initials'].includes(f.type) ? '<label class="check"><input type="checkbox" id="fo-req"> Required</label>' : ''}`;
        box.querySelector('#fo-signer').value = f.signer;
        box.querySelector('#fo-signer').addEventListener('change', e => { f.signer = e.target.value; renderFields(); renderSigners(); });
        const req = box.querySelector('#fo-req');
        if (req) { req.checked = f.required; req.addEventListener('change', () => { f.required = req.checked; renderFields(); }); }
    }

    function startDrag(e, f, el, overlay, resize) {
        if (e.button !== 0) return;
        e.preventDefault(); e.stopPropagation();
        state.selected = f;
        document.querySelectorAll('.fld.selected').forEach(x => x.classList.remove('selected'));
        el.classList.add('selected');
        renderFieldOptions();
        const rect = overlay.getBoundingClientRect();
        const start = { x: e.clientX, y: e.clientY, fx: f.x, fy: f.y, fw: f.width, fh: f.height };
        el.setPointerCapture(e.pointerId);
        const move = ev => {
            const dx = (ev.clientX - start.x) / rect.width, dy = (ev.clientY - start.y) / rect.height;
            if (resize) {
                f.width = clamp(start.fw + dx, 0.03, 1 - f.x);
                f.height = clamp(start.fh + dy, 0.015, 1 - f.y);
            } else {
                f.x = clamp(start.fx + dx, 0, 1 - f.width);
                f.y = clamp(start.fy + dy, 0, 1 - f.height);
            }
            Object.assign(el.style, { left: f.x * 100 + '%', top: f.y * 100 + '%', width: f.width * 100 + '%', height: f.height * 100 + '%' });
        };
        const up = () => { el.removeEventListener('pointermove', move); el.removeEventListener('pointerup', up); };
        el.addEventListener('pointermove', move);
        el.addEventListener('pointerup', up);
    }

    function onOverlayClick(e, page, overlay) {
        if (!state.placing || !state.activeSigner) {
            if (e.target === overlay) { state.selected = null; renderFields(); }
            return;
        }
        const rect = overlay.getBoundingClientRect();
        const [w, h] = DEFAULT_SIZE[state.placing];
        const f = {
            signer: state.activeSigner, type: state.placing, page, required: true, width: w, height: h,
            x: clamp((e.clientX - rect.left) / rect.width - w / 2, 0, 1 - w),
            y: clamp((e.clientY - rect.top) / rect.height - h / 2, 0, 1 - h),
        };
        state.fields.push(f);
        state.selected = f;
        if (!e.shiftKey) setPlacing(null);
        renderFields(); renderSigners();
    }

    function setPlacing(type) {
        state.placing = type;
        document.querySelectorAll('.palette button').forEach(b => b.classList.toggle('active', b.dataset.type === type));
        Object.values(overlays).forEach(o => o.classList.toggle('placing', !!type));
    }

    function escapeHtml(s) { return s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    document.querySelectorAll('.palette button').forEach(b => b.addEventListener('click', () => setPlacing(state.placing === b.dataset.type ? null : b.dataset.type)));
    $('#add-signer').addEventListener('click', () => addSigner());
    $('#add-me').addEventListener('click', () => addSigner(cfg.me.name, cfg.me.email));
    $('#sequential').addEventListener('change', toggleSequential);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') setPlacing(null);
        if ((e.key === 'Delete' || e.key === 'Backspace') && state.selected && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            state.fields = state.fields.filter(x => x !== state.selected); state.selected = null; renderFields(); renderSigners();
        }
    });
    $('#setup-form').addEventListener('submit', e => {
        const action = e.submitter?.value;
        if (action === 'send' && !confirm('Send this document to ' + state.signers.length + ' signer(s) now? You will not be able to edit it afterwards.')) { e.preventDefault(); return; }
        $('#setup').value = JSON.stringify({
            signers: state.signers,
            fields: state.fields.map(({ signer, type, page, x, y, width, height, required }) => ({ signer, type, page, x: +x.toFixed(6), y: +y.toFixed(6), width: +width.toFixed(6), height: +height.toFixed(6), required })),
        });
    });

    renderSigners();
    PdfViewer.render(cfg.pdfUrl, $('#pages'), (n, overlay) => {
        overlays[n] = overlay;
        overlay.addEventListener('click', e => onOverlayClick(e, n, overlay));
        renderFields();
    }).catch(err => { $('#pages').innerHTML = '<div class="alert alert-error">Could not render PDF: ' + err.message + '</div>'; });
})();
