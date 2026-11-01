/* Saffron POS — shared UI behaviour */
(function () {
    'use strict';
    const APP = window.APP || {};

    /* ---------- Money ---------- */
    APP.money = function (n, withSymbol = true) {
        const v = Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: APP.decimals, maximumFractionDigits: APP.decimals });
        if (!withSymbol) return v;
        return APP.currencyPos === 'after' ? `${v} ${APP.currency}` : `${APP.currency} ${v}`;
    };
    APP.round = (n) => Math.round((Number(n) + Number.EPSILON) * 100) / 100;
    APP.esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ---------- Toasts ---------- */
    const icons = { success: 'bi-check-circle-fill text-success', danger: 'bi-x-circle-fill text-danger', warning: 'bi-exclamation-triangle-fill text-warning', info: 'bi-info-circle-fill text-primary' };
    APP.toast = function (message, type = 'success', delay = 3500) {
        const wrap = document.getElementById('toasts');
        if (!wrap) return alert(message);
        const el = document.createElement('div');
        el.className = `toast t-${type}`;
        el.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        el.innerHTML = `<div class="toast-body"><i class="bi ${icons[type] || icons.info}"></i><div class="flex-grow-1">${APP.esc(message)}</div><button type="button" class="btn-close btn-sm" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
        wrap.appendChild(el);
        const t = new bootstrap.Toast(el, { delay });
        el.addEventListener('hidden.bs.toast', () => el.remove());
        t.show();
    };

    /* ---------- AJAX ---------- */
    APP.api = async function (route, data = null, method = 'POST') {
        const url = `${APP.base}/index.php?r=${route}`;
        const opts = { method, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-Token': APP.csrf } };
        if (data instanceof FormData) {
            opts.body = data;
        } else if (data !== null) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
        let res, json;
        try {
            res = await fetch(url, opts);
            json = await res.json();
        } catch (e) {
            throw new Error('Network error — please check the connection and try again.');
        }
        if (res.status === 401) { window.location = `${APP.base}/index.php?r=login`; }
        if (!json.ok) throw new Error(json.message || 'Request failed.');
        return json;
    };
    APP.get = (route, params = {}) => APP.api(route + '&' + new URLSearchParams(params).toString(), null, 'GET');

    /* ---------- Confirm dialog ---------- */
    let pendingConfirm = null;
    APP.confirm = function (text, title = 'Are you sure?', okLabel = 'Yes, continue') {
        const modalEl = document.getElementById('confirmModal');
        if (!modalEl) return Promise.resolve(window.confirm(text));
        modalEl.querySelector('[data-confirm-title]').textContent = title;
        modalEl.querySelector('[data-confirm-text]').textContent = text;
        modalEl.querySelector('[data-confirm-ok]').textContent = okLabel;
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        return new Promise((resolve) => {
            pendingConfirm = resolve;
            modalEl.addEventListener('hidden.bs.modal', () => { if (pendingConfirm) { pendingConfirm(false); pendingConfirm = null; } }, { once: true });
            modal.show();
        });
    };
    document.addEventListener('click', (ev) => {
        if (ev.target.closest('[data-confirm-ok]')) {
            const r = pendingConfirm; pendingConfirm = null;
            bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
            if (r) r(true);
        }
    });
    document.addEventListener('submit', async (ev) => {
        const form = ev.target;
        if (form.dataset.confirm && !form.dataset.confirmed) {
            ev.preventDefault();
            if (await APP.confirm(form.dataset.confirm, form.dataset.confirmTitle || 'Are you sure?', form.dataset.confirmOk || 'Yes, continue')) {
                form.dataset.confirmed = '1';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
            return;
        }
        // Prevent double submits
        const btn = form.querySelector('button[type=submit]:not([data-no-lock]), button:not([type]):not([data-no-lock])');
        if (btn && form.method.toLowerCase() === 'post') {
            setTimeout(() => { btn.disabled = true; btn.dataset.label = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }, 0);
        }
    });

    /* ---------- Sidebar ---------- */
    document.addEventListener('click', (ev) => {
        const sb = document.getElementById('sidebar');
        if (!sb) return;
        if (ev.target.closest('[data-sidebar-toggle]')) sb.classList.toggle('open');
        if (ev.target.closest('[data-sidebar-close]')) sb.classList.remove('open');
    });

    /* ---------- Modal form filling: data-fill='{"field":"value"}' ---------- */
    document.addEventListener('show.bs.modal', (ev) => {
        const modal = ev.target;
        const trigger = ev.relatedTarget;
        const form = modal.querySelector('form[data-fill-form]');
        if (!form || !trigger) return;
        form.reset();
        form.querySelectorAll('input[type=hidden][data-reset]').forEach((i) => (i.value = i.dataset.reset));
        const data = trigger.dataset.fill ? JSON.parse(trigger.dataset.fill) : null;
        const title = modal.querySelector('[data-modal-title]');
        if (title) title.textContent = data ? (title.dataset.edit || 'Edit') : (title.dataset.create || 'Add new');
        if (!data) return;
        Object.entries(data).forEach(([k, v]) => {
            form.querySelectorAll(`[name="${k}"]`).forEach((el) => {
                if (el.type === 'checkbox') el.checked = !!Number(v) || v === true;
                else if (el.type === 'radio') el.checked = String(el.value) === String(v);
                else el.value = v ?? '';
            });
        });
    });
    document.addEventListener('shown.bs.modal', (ev) => {
        const first = ev.target.querySelector('[autofocus], input:not([type=hidden]):not([readonly]), select, textarea');
        if (first) first.focus();
    });

    /* ---------- Image preview ---------- */
    document.addEventListener('change', (ev) => {
        const input = ev.target.closest('input[type=file][data-preview]');
        if (!input || !input.files[0]) return;
        const box = document.querySelector(input.dataset.preview);
        if (!box) return;
        const reader = new FileReader();
        reader.onload = (e) => { box.innerHTML = `<img src="${e.target.result}" alt="Preview">`; };
        reader.readAsDataURL(input.files[0]);
    });

    /* ---------- Client-side table filter: <input data-table-filter="#tbl"> ---------- */
    document.addEventListener('input', (ev) => {
        const input = ev.target.closest('[data-table-filter]');
        if (!input) return;
        const q = input.value.trim().toLowerCase();
        document.querySelectorAll(`${input.dataset.tableFilter} tbody tr`).forEach((tr) => {
            tr.style.display = !q || tr.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    /* ---------- PDF export (lazy loads html2pdf) ---------- */
    let html2pdfLoading = null;
    APP.loadPdf = function () {
        if (window.html2pdf) return Promise.resolve();
        html2pdfLoading ??= new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = `${APP.base}/assets/vendor/html2pdf/html2pdf.bundle.min.js`;
            s.onload = resolve; s.onerror = () => reject(new Error('Could not load the PDF library.'));
            document.head.appendChild(s);
        });
        return html2pdfLoading;
    };
    APP.exportPdf = async function (element, filename, opts = {}) {
        await APP.loadPdf();
        element.classList.add('pdf-render');
        try {
            await html2pdf().set(Object.assign({
                margin: [10, 8, 10, 8],
                filename: `${filename}.pdf`,
                image: { type: 'jpeg', quality: 0.96 },
                html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'], avoid: ['tr', '.card'] },
            }, opts)).from(element).save();
        } finally {
            element.classList.remove('pdf-render');
        }
    };
    document.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('[data-pdf-export]');
        if (!btn) return;
        const el = document.querySelector(btn.dataset.pdfExport);
        if (!el) return;
        btn.disabled = true;
        try { await APP.exportPdf(el, btn.dataset.pdfName || 'report'); }
        catch (e) { APP.toast(e.message, 'danger'); }
        finally { btn.disabled = false; }
    });

    /* ---------- Boot ---------- */
    document.addEventListener('DOMContentLoaded', () => {
        // Modals must live directly under <body>; inside animated/transformed containers
        // they end up beneath the backdrop and look greyed out and unclickable.
        document.querySelectorAll('.modal').forEach((m) => { if (m.parentElement !== document.body) document.body.appendChild(m); });
        (APP.flashes || []).forEach((f) => APP.toast(f.message, f.type === 'error' ? 'danger' : f.type, 4500));
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
    });

    window.APP = APP;
})();
