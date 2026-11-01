/* Saffron POS — point of sale screen */
(function () {
    'use strict';
    const C = window.POS;
    const APP = window.APP;
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const money = (n) => APP.money(n);
    // Same precision as OrderService::money() on the server
    const prec = Math.pow(10, Math.min(2, APP.decimals));
    const round = (n) => Math.round((Number(n) + 1e-7) * prec) / prec;
    const esc = APP.esc;
    const storeKey = (k) => `saffron_${k}_${C.userId}`;
    const store = {
        get(k, d) { try { return JSON.parse(localStorage.getItem(storeKey(k))) ?? d; } catch { return d; } },
        set(k, v) { try { localStorage.setItem(storeKey(k), JSON.stringify(v)); } catch { /* storage unavailable */ } },
    };

    let products = C.products;
    let byId = new Map(products.map((p) => [p.id, p]));

    const fresh = () => ({
        cart: [], type: C.defaultType, customer: null, table: '', address: '', deliveryFee: C.deliveryFee,
        discount: { type: 'percent', value: 0 }, note: '', redeem: 0,
    });
    let S = Object.assign(fresh(), store.get('cart', {}));
    let ui = { category: 0, search: '', method: 'cash', lastOrder: null, pm: null, busy: false };

    const persist = () => store.set('cart', S);

    /* ================= Totals (mirrors OrderService::price) ================= */
    function calc() {
        const subtotal = round(S.cart.reduce((a, l) => a + round(l.unit * l.qty), 0));
        let discount = 0;
        const dv = Math.max(0, Number(S.discount.value) || 0);
        if (dv > 0) discount = S.discount.type === 'percent' ? subtotal * Math.min(dv, 100) / 100 : Math.min(dv, subtotal);
        discount = round(discount);
        let loyalty = 0;
        if (S.redeem > 0 && S.customer && C.loyalty.enabled) loyalty = round(Math.min(S.redeem * C.loyalty.pointValue, subtotal - discount));
        const taxable = Math.max(0, subtotal - discount - loyalty);
        const r = C.taxRate;
        const tax = C.taxInclusive ? round(taxable - taxable / (1 + r / 100)) : round(taxable * r / 100);
        const after = C.taxInclusive ? taxable : taxable + tax;
        const delivery = S.type === 'delivery' ? Math.max(0, Number(S.deliveryFee) || 0) : 0;
        const total = round(after + delivery);
        const items = S.cart.reduce((a, l) => a + l.qty, 0);
        return { subtotal, discount, loyalty, tax, delivery, total, items };
    }

    /* ================= Product grid ================= */
    function filtered() {
        const q = ui.search.trim().toLowerCase();
        return products.filter((p) => {
            if (ui.category && p.category_id !== ui.category && !q) return false;
            if (!q) return true;
            return p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || p.barcode.toLowerCase() === q || p.barcode.includes(q);
        });
    }

    function inCart(pid) { return S.cart.filter((l) => l.product_id === pid).reduce((a, l) => a + l.qty, 0); }

    function renderGrid() {
        const list = filtered();
        const grid = $('#posGrid');
        if (!list.length) {
            grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon"><i class="bi bi-search"></i></div><p class="mt-2 mb-0">No products match “${esc(ui.search)}”.</p></div>`;
            return;
        }
        grid.innerHTML = list.map((p, i) => {
            const q = inCart(p.id);
            const out = p.track_stock && p.stock <= 0;
            const low = p.track_stock && !out && p.stock <= 5;
            const from = p.variants.length ? Math.min(...p.variants.map((v) => v.price)) : p.price;
            return `<button class="p-card ${out ? 'out' : ''}" data-pid="${p.id}" style="animation-delay:${Math.min(i, 20) * 12}ms;--c:${esc(p.color)}" aria-label="${esc(p.name)}, ${money(from)}">
                <div class="p-img">${p.image ? `<img src="${esc(p.image)}" alt="" loading="lazy">` : `<div class="p-fallback"><i class="bi ${esc(p.icon)}"></i></div>`}
                    ${out ? '<span class="p-badge">Out of stock</span>' : low ? `<span class="p-badge low">${p.stock} left</span>` : p.variants.length ? `<span class="p-badge">${p.variants.length} sizes</span>` : ''}
                    ${q ? `<span class="p-qty-badge">${q}</span>` : ''}
                </div>
                <div class="p-body">${p.sku ? `<span class="p-sku"><i class="bi bi-upc"></i> ${esc(p.sku)}</span>` : ''}<span class="p-name">${esc(p.name)}</span><span class="p-price">${p.variants.length ? '<small class="text-muted fw-normal" style="font-size:.7rem">from </small>' : ''}${money(from)}</span></div>
            </button>`;
        }).join('');
    }

    /* ================= Cart ================= */
    function lineKey(l) { return [l.product_id, l.variant_id || 0, [...l.addon_ids].sort().join('.'), l.note || ''].join('|'); }

    function buildLine(p, variantId, addonIds, qty, note) {
        const v = p.variants.find((x) => x.id === variantId) || null;
        const addons = p.addons.filter((a) => addonIds.includes(a.id));
        const unit = round((v ? v.price : p.price) + addons.reduce((a, x) => a + x.price, 0));
        const line = { product_id: p.id, variant_id: v ? v.id : null, addon_ids: addons.map((a) => a.id), name: p.name, variant_name: v ? v.name : '', addon_names: addons.map((a) => a.name), unit, qty, note: note || '' };
        line.key = lineKey(line);
        return line;
    }

    function addLine(line, replaceIndex = null) {
        if (replaceIndex !== null) S.cart.splice(replaceIndex, 1);
        const existing = S.cart.find((l) => l.key === line.key);
        if (existing) existing.qty = Math.min(999, existing.qty + line.qty);
        else S.cart.push(line);
        refresh();
    }

    function addProduct(p) {
        if (p.track_stock && p.stock <= inCart(p.id)) {
            APP.toast(p.stock <= 0 ? `${p.name} is out of stock.` : `Only ${p.stock} ${p.name} in stock.`, 'warning');
            return;
        }
        if (p.variants.length || p.addons.length) return openProductModal(p);
        addLine(buildLine(p, null, [], 1, ''));
    }

    function renderCart() {
        const t = calc();
        const box = $('#cartItems');
        if (!S.cart.length) {
            box.innerHTML = `<div class="cart-empty"><i class="bi bi-basket2"></i><p class="fw-semibold mb-1 mt-2 text-body">No items yet</p><p class="small mb-0">Tap a product or scan a barcode to start an order.</p></div>`;
        } else {
            box.innerHTML = S.cart.map((l, i) => {
                const opts = [l.variant_name, ...l.addon_names].filter(Boolean).join(' · ');
                return `<div class="c-line">
                    <div><div class="c-name">${esc(l.name)}</div>${opts ? `<div class="c-opts">${esc(opts)}</div>` : ''}${l.note ? `<div class="c-note"><i class="bi bi-chat-left-dots"></i> ${esc(l.note)}</div>` : ''}</div>
                    <div><div class="c-price">${money(l.unit * l.qty)}</div><div class="c-unit">${money(l.unit)} each</div></div>
                    <div class="c-actions">
                        <div class="qty-control">
                            <button data-line-dec="${i}" aria-label="Decrease quantity"><i class="bi bi-dash"></i></button>
                            <input type="number" min="1" max="999" value="${l.qty}" data-line-qty="${i}" aria-label="Quantity">
                            <button data-line-inc="${i}" aria-label="Increase quantity"><i class="bi bi-plus"></i></button>
                        </div>
                        <div>
                            <button class="c-edit" data-line-edit="${i}"><i class="bi bi-pencil"></i> Edit</button>
                            <button class="c-remove" data-line-remove="${i}" aria-label="Remove ${esc(l.name)}"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }
        $('#tSubtotal').textContent = money(t.subtotal);
        $('#tDiscount').textContent = '− ' + money(t.discount);
        $('.t-discount').hidden = !t.discount;
        $('#tLoyalty').textContent = '− ' + money(t.loyalty);
        $('.t-loyalty').hidden = !t.loyalty;
        $('#tTaxLabel').textContent = `${C.taxName} ${C.taxRate}%${C.taxInclusive ? ' (incl.)' : ''}`;
        $('#tTax').textContent = money(t.tax);
        $('.t-tax').hidden = !C.taxRate;
        $('#tDelivery').textContent = money(t.delivery);
        $('.t-delivery').hidden = S.type !== 'delivery';
        $('#tTotal').textContent = money(t.total);
        $('[data-action="pay"]').disabled = !S.cart.length;
        $('#mobileCount').textContent = t.items;
        $('#mobileTotal').textContent = money(t.total);

        const d = S.discount;
        $('#discountLabel').textContent = Number(d.value) > 0 ? (d.type === 'percent' ? `${d.value}% off` : `${money(d.value)} off`) : 'Discount';
        $('#discountLabel').parentElement.classList.toggle('on', Number(d.value) > 0);
        $('#noteLabel').textContent = S.note ? 'Note added' : 'Order note';
        $('#noteLabel').parentElement.classList.toggle('on', !!S.note);
    }

    function renderMeta() {
        $$('.order-types button').forEach((b) => { const on = b.dataset.type === S.type; b.classList.toggle('active', on); b.setAttribute('aria-checked', on); });
        $$('.type-field').forEach((f) => f.classList.toggle('show', f.dataset.for === S.type));
        $('#tableNo').value = S.table;
        $('#deliveryAddress').value = S.address;
        $('#deliveryFee').value = S.deliveryFee;
        const picker = $('#customerPicker');
        picker.classList.toggle('has-customer', !!S.customer);
        $('#customerSearch').value = S.customer ? `${S.customer.name}${S.customer.phone ? ' · ' + S.customer.phone : ''}` : '';
        $('#clearCustomer').hidden = !S.customer;
        const chip = $('#loyaltyChip');
        chip.hidden = !(S.customer && C.loyalty.enabled);
        if (S.customer) chip.innerHTML = `<i class="bi bi-star-fill"></i> ${Number(S.customer.loyalty_points)} loyalty points available`;
        $('#heldCount').textContent = store.get('held', []).length;
        $('#heldCount').hidden = !store.get('held', []).length;
    }

    function refresh() { persist(); renderCart(); renderGrid(); renderMeta(); }

    /* ================= Product options modal ================= */
    const productModal = new bootstrap.Modal('#productModal');
    function openProductModal(p, editIndex = null) {
        const line = editIndex !== null ? S.cart[editIndex] : null;
        ui.pm = { p, editIndex, qty: line ? line.qty : 1 };
        $('#pmName').textContent = p.name;
        $('#pmDesc').textContent = p.description || '';
        $('#pmVariants').innerHTML = p.variants.length ? `<div class="opt-title">Choose size / option</div><div class="opt-grid">${p.variants.map((v, i) => `
            <div class="opt"><input type="radio" name="pmVariant" id="pv${v.id}" value="${v.id}" ${(line ? line.variant_id === v.id : i === 0) ? 'checked' : ''}><label for="pv${v.id}"><strong>${esc(v.name)}</strong><small>${money(v.price)}</small></label></div>`).join('')}</div>` : '';
        $('#pmAddons').innerHTML = p.addons.length ? `<div class="opt-title">Add-ons</div><div class="opt-grid">${p.addons.map((a) => `
            <div class="opt"><input type="checkbox" id="pa${a.id}" value="${a.id}" ${line && line.addon_ids.includes(a.id) ? 'checked' : ''}><label for="pa${a.id}"><strong>${esc(a.name)}</strong><small>+ ${money(a.price)}</small></label></div>`).join('')}</div>` : '';
        $('#pmNote').value = line ? line.note : '';
        $('#pmAdd').firstChild.textContent = line ? 'Update · ' : 'Add · ';
        updatePm();
        productModal.show();
    }
    function pmLine() {
        const { p } = ui.pm;
        const vid = Number(($('input[name=pmVariant]:checked') || {}).value || 0) || null;
        const addons = $$('#pmAddons input:checked').map((i) => Number(i.value));
        return buildLine(p, vid, addons, ui.pm.qty, $('#pmNote').value.trim());
    }
    function updatePm() {
        $('#pmQty').textContent = ui.pm.qty;
        const l = pmLine();
        $('#pmTotal').textContent = money(l.unit * l.qty);
    }
    $('#productModal').addEventListener('change', updatePm);
    $('#productModal').addEventListener('click', (e) => {
        const b = e.target.closest('[data-pm-qty]');
        if (b) { ui.pm.qty = Math.max(1, Math.min(999, ui.pm.qty + Number(b.dataset.pmQty))); updatePm(); }
    });
    $('#pmAdd').addEventListener('click', () => {
        const l = pmLine();
        const p = ui.pm.p;
        const otherQty = inCart(p.id) - (ui.pm.editIndex !== null ? S.cart[ui.pm.editIndex].qty : 0);
        if (p.track_stock && otherQty + l.qty > p.stock) { APP.toast(`Only ${p.stock} ${p.name} in stock.`, 'warning'); return; }
        addLine(l, ui.pm.editIndex);
        productModal.hide();
    });
    $('#pmNote').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('#pmAdd').click(); } });

    /* ================= Grid & cart interactions ================= */
    $('#posGrid').addEventListener('click', (e) => {
        const card = e.target.closest('[data-pid]');
        if (card) addProduct(byId.get(Number(card.dataset.pid)));
    });
    $('#posCats').addEventListener('click', (e) => {
        const b = e.target.closest('[data-cat]');
        if (!b) return;
        ui.category = Number(b.dataset.cat);
        $$('.pos-cat').forEach((x) => { x.classList.toggle('active', x === b); x.setAttribute('aria-selected', x === b); });
        renderGrid();
    });
    $('#cartItems').addEventListener('click', (e) => {
        const t = e.target.closest('button');
        if (!t) return;
        const d = t.dataset;
        if (d.lineInc !== undefined) {
            const l = S.cart[d.lineInc]; const p = byId.get(l.product_id);
            if (p && p.track_stock && inCart(p.id) + 1 > p.stock) { APP.toast(`Only ${p.stock} ${p.name} in stock.`, 'warning'); return; }
            l.qty = Math.min(999, l.qty + 1);
        }
        if (d.lineDec !== undefined) { const l = S.cart[d.lineDec]; l.qty > 1 ? l.qty-- : S.cart.splice(d.lineDec, 1); }
        if (d.lineRemove !== undefined) S.cart.splice(d.lineRemove, 1);
        if (d.lineEdit !== undefined) { const p = byId.get(S.cart[d.lineEdit].product_id); if (p) openProductModal(p, Number(d.lineEdit)); return; }
        refresh();
    });
    $('#cartItems').addEventListener('change', (e) => {
        const inp = e.target.closest('[data-line-qty]');
        if (!inp) return;
        const l = S.cart[inp.dataset.lineQty];
        const q = Math.max(0, Math.min(999, parseInt(inp.value, 10) || 0));
        const p = byId.get(l.product_id);
        if (p && p.track_stock && inCart(p.id) - l.qty + q > p.stock) { APP.toast(`Only ${p.stock} ${p.name} in stock.`, 'warning'); renderCart(); return; }
        q ? (l.qty = q) : S.cart.splice(inp.dataset.lineQty, 1);
        refresh();
    });

    $$('.order-types button').forEach((b) => b.addEventListener('click', () => {
        S.type = b.dataset.type;
        if (S.type === 'delivery' && !S.address && S.customer && S.customer.address) S.address = S.customer.address;
        refresh();
        if (S.type === 'dine_in') $('#tableNo').focus();
        if (S.type === 'delivery' && !S.address) $('#deliveryAddress').focus();
    }));
    $('#tableNo').addEventListener('input', (e) => { S.table = e.target.value; persist(); });
    $('#deliveryAddress').addEventListener('input', (e) => { S.address = e.target.value; persist(); });
    $('#deliveryFee').addEventListener('input', (e) => { S.deliveryFee = Math.max(0, Number(e.target.value) || 0); persist(); renderCart(); });

    /* ================= Search & barcode ================= */
    const search = $('#posSearch');
    search.addEventListener('input', () => { ui.search = search.value; renderGrid(); });
    function findExact(code) {
        const c = code.trim().toLowerCase();
        if (!c) return null;
        return products.find((p) => (p.barcode && p.barcode.toLowerCase() === c) || (p.sku && p.sku.toLowerCase() === c)) || null;
    }
    function scanned(code) {
        const p = findExact(code);
        if (p) {
            addProduct(p);
            const ind = $('.scan-indicator');
            ind.classList.add('flash');
            setTimeout(() => ind.classList.remove('flash'), 500);
            return true;
        }
        return false;
    }
    search.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const val = search.value;
        if (scanned(val)) { search.value = ''; ui.search = ''; renderGrid(); return; }
        const list = filtered();
        if (list.length === 1) { addProduct(list[0]); search.value = ''; ui.search = ''; renderGrid(); }
        else if (!list.length) APP.toast(`No product found for “${val}”.`, 'warning');
    });
    // Hardware scanners type quickly and finish with Enter; catch scans even when the search box isn't focused.
    let scanBuf = '', scanLast = 0;
    document.addEventListener('keydown', (e) => {
        const tag = (e.target.tagName || '').toLowerCase();
        if (['input', 'textarea', 'select'].includes(tag) || document.querySelector('.modal.show')) return;
        const now = Date.now();
        if (now - scanLast > 60) scanBuf = '';
        scanLast = now;
        if (e.key === 'Enter' && scanBuf.length >= 3) {
            if (!scanned(scanBuf)) APP.toast(`Unknown barcode: ${scanBuf}`, 'warning');
            scanBuf = '';
            e.preventDefault();
        } else if (e.key.length === 1) {
            scanBuf += e.key;
        }
    });

    /* ================= Camera scanner (barcode + QR) ================= */
    let qr = null, qrLoading = null, lastCode = '', lastAt = 0;
    const scanStatus = (html, cls = '') => { const s = $('#scanStatus'); s.className = `scan-status ${cls}`; s.innerHTML = html; s.hidden = !html; };
    function loadScanner() {
        if (window.Html5Qrcode) return Promise.resolve();
        qrLoading ??= new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = `${APP.base}/assets/vendor/html5-qrcode/html5-qrcode.min.js`;
            s.onload = resolve; s.onerror = () => reject(new Error('Could not load the scanner.'));
            document.head.appendChild(s);
        });
        return qrLoading;
    }
    function onScan(code) {
        const now = Date.now();
        if (code === lastCode && now - lastAt < 2000) return; // camera reads the same code many times per second
        lastCode = code; lastAt = now;
        const p = findExact(code);
        const box = $('#scanLast');
        box.hidden = false;
        if (p) {
            if (navigator.vibrate && navigator.userActivation?.hasBeenActive) navigator.vibrate(60);
            box.className = 'scan-last ok';
            box.innerHTML = `<i class="bi bi-check-circle-fill"></i> <span><strong>${esc(p.name)}</strong> — ${esc(code)}</span>`;
            if (p.variants.length || p.addons.length) {
                bootstrap.Modal.getInstance('#scanModal').hide();
                setTimeout(() => addProduct(p), 350);
            } else {
                addProduct(p);
                APP.toast(`${p.name} added`, 'success', 1500);
            }
        } else {
            box.className = 'scan-last bad';
            box.innerHTML = `<i class="bi bi-x-circle-fill"></i> <span>No product with code <strong>${esc(code)}</strong></span>`;
        }
    }
    async function startCamera(cameraId) {
        try {
            if (qr && qr.isScanning) await qr.stop();
            qr ??= new Html5Qrcode('qrReader', { verbose: false });
            scanStatus('<span class="spinner-border spinner-border-sm me-2"></span>Starting camera…');
            await qr.start(cameraId ? { deviceId: { exact: cameraId } } : { facingMode: 'environment' },
                { fps: 12, qrbox: (w, h) => ({ width: Math.min(300, w * 0.8), height: Math.min(200, h * 0.6) }), aspectRatio: 1.333 },
                (text) => onScan(text.trim()), () => {});
            scanStatus('');
        } catch (e) {
            const msg = String(e && (e.message || e)).toLowerCase();
            scanStatus(`<i class="bi bi-camera-video-off fs-3 d-block mb-2"></i>${msg.includes('permission') || msg.includes('notallowed')
                ? 'Camera access was blocked. Allow the camera in the browser address bar, then reopen the scanner.'
                : 'No camera found. You can still type the code below or use a USB barcode scanner.'}`, 'error');
        }
    }
    async function openScanner() {
        bootstrap.Modal.getOrCreateInstance('#scanModal').show();
        $('#scanLast').hidden = true;
        $('#manualCode').value = '';
        try {
            await loadScanner();
            const cams = await Html5Qrcode.getCameras().catch(() => []);
            const pick = $('#cameraPick');
            $('#cameraPickWrap').hidden = cams.length < 2;
            pick.innerHTML = cams.map((c, i) => `<option value="${esc(c.id)}">${esc(c.label || `Camera ${i + 1}`)}</option>`).join('');
            const back = cams.find((c) => /back|rear|environment/i.test(c.label));
            if (back) pick.value = back.id;
            await startCamera(cams.length ? pick.value : null);
        } catch (e) { scanStatus(esc(e.message), 'error'); }
    }
    $('#cameraPick').addEventListener('change', (e) => startCamera(e.target.value));
    $('#scanModal').addEventListener('hidden.bs.modal', async () => { if (qr && qr.isScanning) { try { await qr.stop(); } catch { /* already stopped */ } } search.focus(); });
    const manualAdd = () => { const v = $('#manualCode').value.trim(); if (v) { lastCode = ''; onScan(v); $('#manualCode').value = ''; $('#manualCode').focus(); } };
    $('#manualAdd').addEventListener('click', manualAdd);
    $('#manualCode').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); manualAdd(); } });

    /* ================= Customers ================= */
    const cs = $('#customerSearch');
    const results = $('#customerResults');
    let csTimer = null, csList = [], csFocus = -1;
    function showCustomers(list) {
        csList = list; csFocus = -1;
        results.hidden = false;
        results.innerHTML = list.length ? list.map((c, i) => `<button type="button" data-ci="${i}"><span class="avatar" style="width:30px;height:30px;font-size:.68rem">${esc(c.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase())}</span><span class="flex-grow-1"><strong class="d-block small">${esc(c.name)}</strong><small class="text-muted">${esc(c.phone || c.email || '')}</small></span>${C.loyalty.enabled ? `<small class="text-success fw-semibold">${c.loyalty_points} pts</small>` : ''}</button>`).join('')
            : `<div class="p-3 small text-muted">No customers found. <a href="#" data-bs-toggle="modal" data-bs-target="#customerModal">Add new</a></div>`;
    }
    function pickCustomer(c) {
        S.customer = c; S.redeem = 0;
        if (S.type === 'delivery' && !S.address && c.address) S.address = c.address;
        results.hidden = true;
        refresh();
    }
    cs.addEventListener('focus', () => { if (S.customer) cs.select(); });
    cs.addEventListener('input', () => {
        clearTimeout(csTimer);
        const q = cs.value.trim();
        if (S.customer) { S.customer = null; S.redeem = 0; persist(); renderCart(); $('#customerPicker').classList.remove('has-customer'); $('#clearCustomer').hidden = true; $('#loyaltyChip').hidden = true; }
        if (q.length < 1) { results.hidden = true; return; }
        csTimer = setTimeout(async () => {
            try { const r = await APP.get('pos/customers', { q }); showCustomers(r.customers); } catch (e) { APP.toast(e.message, 'danger'); }
        }, 220);
    });
    cs.addEventListener('keydown', (e) => {
        if (results.hidden || !csList.length) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            csFocus = (csFocus + (e.key === 'ArrowDown' ? 1 : -1) + csList.length) % csList.length;
            $$('button', results).forEach((b, i) => b.classList.toggle('focus', i === csFocus));
        } else if (e.key === 'Enter') { e.preventDefault(); pickCustomer(csList[Math.max(0, csFocus)]); }
        else if (e.key === 'Escape') results.hidden = true;
    });
    results.addEventListener('click', (e) => { const b = e.target.closest('[data-ci]'); if (b) pickCustomer(csList[b.dataset.ci]); });
    document.addEventListener('click', (e) => { if (!e.target.closest('#customerPicker')) results.hidden = true; });
    $('#clearCustomer').addEventListener('click', () => { S.customer = null; S.redeem = 0; refresh(); });
    $('#customerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.submitter || $('#customerForm button[type=submit]');
        btn.disabled = true;
        try {
            const r = await APP.api('pos/customer-save', new FormData(e.target));
            bootstrap.Modal.getInstance('#customerModal').hide();
            e.target.reset();
            pickCustomer(r.customer);
            APP.toast(`${r.customer.name} added.`);
        } catch (err) { APP.toast(err.message, 'danger'); }
        finally { btn.disabled = false; }
    });
    $('#customerModal').addEventListener('show.bs.modal', () => {
        const q = cs.value.trim();
        if (q && !S.customer) { /^[\d+\s-]+$/.test(q) ? ($('#cPhone').value = q) : ($('#cName').value = q); }
    });

    /* ================= Discount & note ================= */
    let dType = 'percent';
    $('#discountModal').addEventListener('show.bs.modal', () => {
        dType = S.discount.type;
        $$('[data-dtype]').forEach((b) => b.classList.toggle('active', b.dataset.dtype === dType));
        $('#discountValue').value = Number(S.discount.value) || '';
        $('#discountPresets').hidden = dType !== 'percent';
    });
    $$('[data-dtype]').forEach((b) => b.addEventListener('click', () => {
        dType = b.dataset.dtype;
        $$('[data-dtype]').forEach((x) => x.classList.toggle('active', x === b));
        $('#discountPresets').hidden = dType !== 'percent';
        $('#discountValue').focus();
    }));
    $$('[data-dpreset]').forEach((b) => b.addEventListener('click', () => { $('#discountValue').value = b.dataset.dpreset; applyDiscount(); }));
    function applyDiscount() {
        const v = Math.max(0, Number($('#discountValue').value) || 0);
        const sub = calc().subtotal;
        const pct = dType === 'percent' ? v : (sub ? v / sub * 100 : 0);
        if (dType === 'percent' && v > 100) { APP.toast('Percentage cannot exceed 100%.', 'warning'); return; }
        if (pct > C.maxDiscount + 0.001) { APP.toast(`Your discount limit is ${C.maxDiscount}% of the subtotal.`, 'warning'); return; }
        S.discount = { type: dType, value: v };
        bootstrap.Modal.getInstance('#discountModal').hide();
        refresh();
    }
    $('#discountApply').addEventListener('click', applyDiscount);
    $('#discountValue').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyDiscount(); } });
    $('#discountRemove').addEventListener('click', () => { S.discount = { type: 'percent', value: 0 }; bootstrap.Modal.getInstance('#discountModal').hide(); refresh(); });
    $('#noteModal').addEventListener('show.bs.modal', () => { $('#orderNote').value = S.note; });
    $('#noteSave').addEventListener('click', () => { S.note = $('#orderNote').value.trim(); refresh(); });

    /* ================= Payment ================= */
    const payModal = new bootstrap.Modal('#payModal');
    function setMethod(m) {
        ui.method = m;
        $$('.pay-methods button').forEach((b) => { const on = b.dataset.method === m; b.classList.toggle('active', on); b.setAttribute('aria-checked', on); });
        $('#cashPane').hidden = m !== 'cash';
        $('#refPane').hidden = m === 'cash';
        updatePay();
    }
    function quickAmounts(total) {
        const set = new Set([total]);
        [10, 50, 100, 500, 1000, 5000].forEach((step) => { const v = Math.ceil(total / step) * step; if (v > total) set.add(v); });
        return [...set].sort((a, b) => a - b).slice(0, 4);
    }
    function updatePay() {
        const t = calc();
        $('#payDue').textContent = money(t.total);
        const rows = [['Items', t.items], ['Subtotal', money(t.subtotal)]];
        if (t.discount) rows.push(['Discount', '− ' + money(t.discount)]);
        if (t.loyalty) rows.push(['Loyalty', '− ' + money(t.loyalty)]);
        if (C.taxRate) rows.push([`${C.taxName}${C.taxInclusive ? ' (incl.)' : ''}`, money(t.tax)]);
        if (t.delivery) rows.push(['Delivery', money(t.delivery)]);
        rows.push(['Order type', { dine_in: 'Dine-in', takeaway: 'Takeaway', delivery: 'Delivery' }[S.type] + (S.type === 'dine_in' && S.table ? ` · Table ${esc(S.table)}` : '')]);
        if (S.customer) rows.push(['Customer', esc(S.customer.name)]);
        $('#paySummary').innerHTML = rows.map(([a, b]) => `<div class="stat-line"><span class="text-muted">${a}</span><span class="fw-semibold">${b}</span></div>`).join('');

        $('#quickCash').innerHTML = quickAmounts(t.total).map((v, i) => `<button type="button" data-cash="${v}">${i === 0 ? 'Exact' : money(v)}</button>`).join('');
        const tendered = Number($('#tendered').value) || 0;
        const change = round(tendered - t.total);
        const box = $('.change-box');
        box.classList.toggle('short', change < 0);
        box.firstElementChild.textContent = change < 0 ? 'Still due' : 'Change due';
        $('#changeDue').textContent = money(Math.abs(change));

        const redeemBox = $('#redeemBox');
        const pts = S.customer ? Number(S.customer.loyalty_points) : 0;
        redeemBox.hidden = !(C.loyalty.enabled && S.customer && pts > 0);
        if (!redeemBox.hidden) {
            $('#redeemHint').textContent = `${pts} points available · 1 point = ${money(C.loyalty.pointValue)}${C.loyalty.minRedeem ? ` · min ${C.loyalty.minRedeem}` : ''}`;
        }
    }
    function openPay() {
        if (!S.cart.length) return;
        if (C.requireRegister && !C.hasRegister) { bootstrap.Modal.getOrCreateInstance('#registerModal').show(); return; }
        if (S.type === 'delivery' && !S.address.trim()) { APP.toast('Enter a delivery address first.', 'warning'); $('#deliveryAddress').focus(); return; }
        $('#redeemPoints').value = S.redeem || 0;
        $('#payRef').value = '';
        setMethod('cash');
        $('#tendered').value = calc().total.toFixed(2);
        updatePay();
        payModal.show();
    }
    $('#payModal').addEventListener('shown.bs.modal', () => { if (ui.method === 'cash') { $('#tendered').focus(); $('#tendered').select(); } });
    $$('.pay-methods button').forEach((b) => b.addEventListener('click', () => setMethod(b.dataset.method)));
    $('#tendered').addEventListener('input', updatePay);
    $('#quickCash').addEventListener('click', (e) => { const b = e.target.closest('[data-cash]'); if (b) { $('#tendered').value = Number(b.dataset.cash).toFixed(2); updatePay(); } });
    $('#redeemPoints').addEventListener('input', () => {
        const pts = S.customer ? Number(S.customer.loyalty_points) : 0;
        S.redeem = Math.max(0, Math.min(pts, parseInt($('#redeemPoints').value, 10) || 0));
        persist(); renderCart();
        $('#tendered').value = calc().total.toFixed(2);
        updatePay();
    });
    $('#redeemMax').addEventListener('click', () => {
        const pts = S.customer ? Number(S.customer.loyalty_points) : 0;
        const before = calc();
        const maxUseful = Math.ceil((before.subtotal - before.discount) / C.loyalty.pointValue);
        $('#redeemPoints').value = Math.min(pts, maxUseful);
        $('#redeemPoints').dispatchEvent(new Event('input'));
    });
    $('#payModal').addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.id !== 'payRef') { e.preventDefault(); completeSale(); } });

    async function completeSale() {
        if (ui.busy) return;
        const t = calc();
        const tendered = ui.method === 'cash' ? Number($('#tendered').value) || 0 : t.total;
        if (ui.method === 'cash' && tendered + 0.001 < t.total) { APP.toast('Cash received is less than the amount due.', 'warning'); $('#tendered').focus(); return; }
        ui.busy = true;
        const btn = $('#completeSale');
        btn.disabled = true;
        const label = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';
        try {
            const r = await APP.api('pos/checkout', {
                items: S.cart.map((l) => ({ product_id: l.product_id, variant_id: l.variant_id, addon_ids: l.addon_ids, qty: l.qty, note: l.note })),
                order_type: S.type, table_no: S.table, delivery_address: S.address, delivery_fee: S.deliveryFee,
                customer_id: S.customer ? S.customer.id : null,
                discount_type: Number(S.discount.value) > 0 ? S.discount.type : null, discount_value: S.discount.value,
                redeem_points: S.redeem, payment_method: ui.method, tendered, payment_ref: $('#payRef').value, notes: S.note,
            });
            ui.lastOrder = r.order;
            ui.lastOrder.receipt = r.receipt;
            payModal.hide();
            showSuccess(r.order);
            const keepType = S.type;
            S = fresh(); S.type = keepType;
            refresh();
            reloadCatalog();
            if (C.autoPrint) printReceipt(C.receiptSize);
        } catch (e) {
            APP.toast(e.message, 'danger', 6000);
        } finally {
            ui.busy = false; btn.disabled = false; btn.innerHTML = label;
        }
    }
    $('#completeSale').addEventListener('click', completeSale);

    const successModal = new bootstrap.Modal('#successModal');
    function showSuccess(o) {
        $('#sOrderNo').textContent = o.order_no;
        $('#sTotal').textContent = money(o.total);
        $('#sTendered').textContent = money(o.tendered);
        $('#sChange').textContent = money(o.change);
        $('#sPoints').hidden = !o.points;
        $('#sPoints').innerHTML = `<i class="bi bi-star-fill"></i> Customer earned ${o.points} loyalty points`;
        successModal.show();
    }
    $('#successModal').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); successModal.hide(); } });
    $('#successModal').addEventListener('hidden.bs.modal', () => search.focus());
    $('#successModal').addEventListener('click', (e) => {
        const b = e.target.closest('[data-print]');
        if (b) printReceipt(b.dataset.print);
        if (e.target.closest('[data-action="new-order"]')) successModal.hide();
    });

    function printReceipt(size, url) {
        const base = url || (ui.lastOrder && ui.lastOrder.receipt);
        if (!base) return;
        $('#printFrame').src = `${base}&size=${encodeURIComponent(size)}&autoprint=1&embed=1`;
    }

    async function reloadCatalog() {
        try {
            const r = await APP.get('pos/catalog');
            products = r.products;
            byId = new Map(products.map((p) => [p.id, p]));
            renderGrid();
        } catch { /* keep current catalogue */ }
    }

    /* ================= Hold / resume ================= */
    function holdOrder() {
        if (!S.cart.length) { APP.toast('Nothing to hold — the cart is empty.', 'info'); return; }
        const held = store.get('held', []);
        const t = calc();
        const label = S.customer ? S.customer.name : S.table ? `Table ${S.table}` : `Order #${held.length + 1}`;
        held.push({ id: Date.now(), label, at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), total: t.total, items: t.items, state: S });
        store.set('held', held);
        const keepType = S.type;
        S = fresh(); S.type = keepType;
        refresh();
        APP.toast(`Order held as “${label}”.`, 'info');
    }
    function renderHeld() {
        const held = store.get('held', []);
        $('#heldList').innerHTML = held.length ? held.map((h) => `<div class="list-row">
            <span class="kpi-icon tone-brand" style="width:38px;height:38px"><i class="bi bi-pause-fill"></i></span>
            <div class="flex-grow-1"><div class="cell-title">${esc(h.label)}</div><div class="cell-sub">${h.items} items · held at ${esc(h.at)}</div></div>
            <strong class="me-2">${money(h.total)}</strong>
            <button class="btn btn-sm btn-primary" data-resume="${h.id}">Resume</button>
            <button class="btn-icon btn-sm danger" data-drop="${h.id}" aria-label="Discard"><i class="bi bi-trash3"></i></button>
        </div>`).join('') : '<div class="empty-state"><div class="empty-icon"><i class="bi bi-pause-circle"></i></div><p class="mt-2 mb-0">No held orders.</p></div>';
    }
    $('#heldList').addEventListener('click', async (e) => {
        const r = e.target.closest('[data-resume]');
        const d = e.target.closest('[data-drop]');
        let held = store.get('held', []);
        if (r) {
            const h = held.find((x) => x.id === Number(r.dataset.resume));
            if (S.cart.length && !(await APP.confirm('The current cart will be held so you can resume this order.', 'Swap orders?', 'Swap'))) return;
            held = held.filter((x) => x.id !== h.id);
            if (S.cart.length) {
                const t = calc();
                held.push({ id: Date.now(), label: S.customer ? S.customer.name : S.table ? `Table ${S.table}` : 'Swapped order', at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), total: t.total, items: t.items, state: S });
            }
            store.set('held', held);
            S = Object.assign(fresh(), h.state);
            refresh();
            bootstrap.Modal.getInstance('#heldModal').hide();
        }
        if (d) { store.set('held', held.filter((x) => x.id !== Number(d.dataset.drop))); renderHeld(); renderMeta(); }
    });

    /* ================= Recent orders ================= */
    async function openRecent() {
        const box = $('#recentList');
        box.innerHTML = '<div class="p-4 text-center"><span class="spinner-border spinner-border-sm"></span></div>';
        bootstrap.Modal.getOrCreateInstance('#recentModal').show();
        try {
            const r = await APP.get('pos/recent');
            box.innerHTML = r.orders.length ? `<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Order</th><th>Type</th><th>Customer</th><th class="text-end">Total</th><th class="text-end">Print</th></tr></thead><tbody>${r.orders.map((o) => `
                <tr><td><div class="cell-title">${esc(o.order_no)}</div><div class="cell-sub">${esc(o.time)} · ${esc(o.payment_method)}</div></td>
                <td>${esc(o.type_label)}${o.table_no ? ` · T${esc(o.table_no)}` : ''}</td><td>${esc(o.customer || 'Walk-in')}</td>
                <td class="text-end fw-semibold">${esc(o.total_fmt)}${o.status !== 'completed' ? '<br><span class="badge badge-soft-danger">refund</span>' : ''}</td>
                <td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-light border" data-reprint="${esc(o.receipt)}" data-size="80">80</button><button class="btn btn-light border" data-reprint="${esc(o.receipt)}" data-size="58">58</button><button class="btn btn-light border" data-reprint="${esc(o.receipt)}" data-size="a4">A4</button></div></td></tr>`).join('')}</tbody></table></div>`
                : '<div class="empty-state"><div class="empty-icon"><i class="bi bi-receipt"></i></div><p class="mt-2 mb-0">No orders today yet.</p></div>';
        } catch (e) { box.innerHTML = `<div class="p-4 text-danger">${esc(e.message)}</div>`; }
    }
    $('#recentList').addEventListener('click', (e) => { const b = e.target.closest('[data-reprint]'); if (b) printReceipt(b.dataset.size, b.dataset.reprint); });

    /* ================= Global actions & shortcuts ================= */
    document.addEventListener('click', async (e) => {
        const a = e.target.closest('[data-action]');
        if (!a) return;
        switch (a.dataset.action) {
            case 'pay': openPay(); break;
            case 'camera-scan': openScanner(); break;
            case 'hold': holdOrder(); break;
            case 'held': renderHeld(); bootstrap.Modal.getOrCreateInstance('#heldModal').show(); break;
            case 'recent': openRecent(); break;
            case 'clear':
                if (S.cart.length && await APP.confirm('Remove all items from this order?', 'Clear cart?', 'Clear')) { const k = S.type; S = fresh(); S.type = k; refresh(); }
                break;
            case 'open-cart': $('#posCart').classList.add('open'); break;
            case 'close-cart': $('#posCart').classList.remove('open'); break;
            case 'fullscreen':
                if (!document.fullscreenElement) document.documentElement.requestFullscreen?.(); else document.exitFullscreen?.();
                break;
        }
    });
    document.addEventListener('keydown', (e) => {
        const modalOpen = document.querySelector('.modal.show');
        if (e.key === 'F2' || (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName) && !modalOpen)) { e.preventDefault(); search.focus(); search.select(); }
        if ((e.key === 'F9' || e.key === 'F4') && !modalOpen) { e.preventDefault(); openPay(); }
        if (e.key === 'F8' && !modalOpen) { e.preventDefault(); holdOrder(); }
        if (e.key === 'Escape' && document.activeElement === search && search.value) { search.value = ''; ui.search = ''; renderGrid(); }
    });

    /* ================= Boot ================= */
    refresh();
    if (C.requireRegister && !C.hasRegister) {
        bootstrap.Modal.getOrCreateInstance('#registerModal').show();
    } else {
        search.focus();
    }
})();
