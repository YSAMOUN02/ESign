(function () {
    const cfg = window.EDITOR;
    const DEFAULT_SIZE = { signature: [0.24, 0.06], initials: [0.09, 0.05], date: [0.16, 0.03], text: [0.22, 0.03], name: [0.22, 0.03] };
    const STEP_X = 1 / 96;  // grid squares across the page width
    const GUIDE_PX = 6;     // how close an edge must be to line up with another field
    const state = {
        signers: cfg.setup.signers.map(s => ({ ...s })),
        fields: cfg.setup.fields.map(f => ({ ...f, required: f.required !== false })),
        activeSigner: null,
        placing: null,
        selected: null,
        snap: true,
    };
    let uid = Date.now();
    const overlays = {};
    const $ = s => document.querySelector(s);
    const signerList = $('#signer-list');

    const colorOf = key => ESIGN_COLORS[Math.max(0, state.signers.findIndex(s => s.key === key)) % ESIGN_COLORS.length];
    const clamp = (v, min, max) => Math.min(max, Math.max(min, v));

    // ---------- Grid & alignment ----------
    const ghost = document.createElement('div');
    ghost.className = 'fld ghost';

    // Pages are not square, so the height step is what keeps the grid squares square.
    const stepY = overlay => STEP_X * overlay.clientWidth / overlay.clientHeight;
    const snapping = e => state.snap && !e.altKey;

    function guideTargets(page, moving) {
        const xs = [0.5], ys = [0.5]; // page centre
        state.fields.filter(f => f.page === page && f !== moving).forEach(f => {
            xs.push(f.x, f.x + f.width / 2, f.x + f.width);
            ys.push(f.y, f.y + f.height / 2, f.y + f.height);
        });
        return { xs, ys };
    }

    // Nearest line-up with another field wins; otherwise the first edge goes to the grid.
    function snapAxis(edges, targets, step, tolerance) {
        let best = null;
        edges.forEach(edge => targets.forEach(at => {
            const shift = at - edge;
            if (Math.abs(shift) <= tolerance && (!best || Math.abs(shift) < Math.abs(best.shift))) best = { shift, at };
        }));
        return best || { shift: Math.round(edges[0] / step) * step - edges[0], at: null };
    }

    function snapShift(overlay, page, moving, xEdges, yEdges) {
        const { xs, ys } = guideTargets(page, moving);
        const sx = snapAxis(xEdges, xs, STEP_X, GUIDE_PX / overlay.clientWidth);
        const sy = snapAxis(yEdges, ys, stepY(overlay), GUIDE_PX / overlay.clientHeight);
        showGuides(overlay, sx.at, sy.at);
        return { dx: sx.shift, dy: sy.shift };
    }

    function showGuides(overlay, x = null, y = null) {
        overlay.querySelectorAll('.guide').forEach(g => g.remove());
        [['left', 'guide-v', x], ['top', 'guide-h', y]].forEach(([prop, cls, at]) => {
            if (at === null) return;
            const g = document.createElement('div');
            g.className = 'guide ' + cls;
            g.style[prop] = at * 100 + '%';
            overlay.append(g);
        });
    }

    function placementAt(e, overlay, page) {
        const rect = overlay.getBoundingClientRect();
        const [width, height] = DEFAULT_SIZE[state.placing];
        let x = clamp((e.clientX - rect.left) / rect.width - width / 2, 0, 1 - width);
        let y = clamp((e.clientY - rect.top) / rect.height - height / 2, 0, 1 - height);
        if (snapping(e)) {
            const { dx, dy } = snapShift(overlay, page, null, [x, x + width / 2, x + width], [y, y + height / 2, y + height]);
            x = clamp(x + dx, 0, 1 - width);
            y = clamp(y + dy, 0, 1 - height);
        } else {
            showGuides(overlay);
        }
        return { x, y, width, height };
    }

    function showGhost(e, overlay, page) {
        if (!state.placing || !state.activeSigner) return;
        const { x, y, width, height } = placementAt(e, overlay, page);
        ghost.textContent = ESIGN_LABELS[state.placing];
        ghost.style.setProperty('--c', colorOf(state.activeSigner));
        Object.assign(ghost.style, { left: x * 100 + '%', top: y * 100 + '%', width: width * 100 + '%', height: height * 100 + '%' });
        if (ghost.parentNode !== overlay) overlay.append(ghost);
    }

    function hideGhost(overlay) {
        ghost.remove();
        showGuides(overlay);
    }

    function nudge(f, dx, dy, fine) {
        const overlay = overlays[f.page];
        if (!overlay) return;
        const onGrid = state.snap && !fine;
        const moveAxis = (value, dir, step, pixel, max) => {
            if (!dir) return value;
            return clamp(onGrid ? (Math.round(value / step) + dir) * step : value + dir * pixel, 0, max);
        };
        f.x = moveAxis(f.x, dx, STEP_X, 1 / overlay.clientWidth, 1 - f.width);
        f.y = moveAxis(f.y, dy, stepY(overlay), 1 / overlay.clientHeight, 1 - f.height);
        renderFields();
    }

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
        let moved = false;
        el.setPointerCapture(e.pointerId);
        const move = ev => {
            // A click that wobbles a pixel must not make the field jump to the grid.
            if (!moved && Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 3) return;
            moved = true;
            const dx = (ev.clientX - start.x) / rect.width, dy = (ev.clientY - start.y) / rect.height;
            const snap = snapping(ev);
            if (!snap) showGuides(overlay);
            if (resize) {
                let right = start.fx + start.fw + dx, bottom = start.fy + start.fh + dy;
                if (snap) {
                    const s = snapShift(overlay, f.page, f, [right], [bottom]);
                    right += s.dx; bottom += s.dy;
                }
                f.width = clamp(right - f.x, 0.03, 1 - f.x);
                f.height = clamp(bottom - f.y, 0.015, 1 - f.y);
            } else {
                let x = clamp(start.fx + dx, 0, 1 - f.width), y = clamp(start.fy + dy, 0, 1 - f.height);
                if (snap) {
                    const s = snapShift(overlay, f.page, f, [x, x + f.width / 2, x + f.width], [y, y + f.height / 2, y + f.height]);
                    x += s.dx; y += s.dy;
                }
                f.x = clamp(x, 0, 1 - f.width);
                f.y = clamp(y, 0, 1 - f.height);
            }
            Object.assign(el.style, { left: f.x * 100 + '%', top: f.y * 100 + '%', width: f.width * 100 + '%', height: f.height * 100 + '%' });
        };
        const up = () => {
            showGuides(overlay);
            el.removeEventListener('pointermove', move);
            el.removeEventListener('pointerup', up);
            el.removeEventListener('pointercancel', up);
        };
        el.addEventListener('pointermove', move);
        el.addEventListener('pointerup', up);
        el.addEventListener('pointercancel', up);
    }

    function onOverlayClick(e, page, overlay) {
        if (!state.placing || !state.activeSigner) {
            if (e.target === overlay) { state.selected = null; renderFields(); }
            return;
        }
        const f = { signer: state.activeSigner, type: state.placing, page, required: true, ...placementAt(e, overlay, page) };
        hideGhost(overlay);
        state.fields.push(f);
        state.selected = f;
        if (!e.shiftKey) setPlacing(null);
        renderFields(); renderSigners();
    }

    function setPlacing(type) {
        state.placing = type;
        document.querySelectorAll('.palette button').forEach(b => b.classList.toggle('active', b.dataset.type === type));
        Object.values(overlays).forEach(o => {
            o.classList.toggle('placing', !!type);
            if (!type) hideGhost(o);
        });
    }

    function escapeHtml(s) { return s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

    document.querySelectorAll('.palette button').forEach(b => b.addEventListener('click', () => setPlacing(state.placing === b.dataset.type ? null : b.dataset.type)));
    $('#add-signer').addEventListener('click', () => addSigner());
    $('#add-me').addEventListener('click', () => addSigner(cfg.me.name, cfg.me.email));
    $('#sequential').addEventListener('change', toggleSequential);
    $('#snap').addEventListener('change', e => {
        state.snap = e.target.checked;
        Object.values(overlays).forEach(o => o.classList.toggle('show-grid', state.snap));
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') setPlacing(null);
        const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName);
        if ((e.key === 'Delete' || e.key === 'Backspace') && state.selected && !typing) {
            state.fields = state.fields.filter(x => x !== state.selected); state.selected = null; renderFields(); renderSigners();
        }
        const arrow = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
        if (arrow && state.selected && !typing) {
            e.preventDefault();
            nudge(state.selected, arrow[0], arrow[1], e.shiftKey);
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
        overlay.style.backgroundSize = `${STEP_X * 100}% ${stepY(overlay) * 100}%`;
        overlay.classList.toggle('show-grid', state.snap);
        overlay.classList.toggle('placing', !!state.placing);
        overlay.addEventListener('click', e => onOverlayClick(e, n, overlay));
        overlay.addEventListener('pointermove', e => showGhost(e, overlay, n));
        overlay.addEventListener('pointerleave', () => hideGhost(overlay));
        renderFields();
    }).catch(err => { $('#pages').innerHTML = '<div class="alert alert-error">Could not render PDF: ' + err.message + '</div>'; });
})();
