<?php if (!$ingredients && !$products): ?>
<div class="card"><div class="empty-state"><div class="empty-icon"><i class="bi bi-basket"></i></div><p class="mt-2">Add ingredients or enable stock tracking on a product before recording purchases.</p><a href="<?= url('inventory') ?>" class="btn btn-primary btn-sm">Go to inventory</a></div></div>
<?php return; endif; ?>
<form method="post" action="<?= url('purchases/save') ?>" id="purchaseForm" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h2>Items received</h2><button type="button" class="btn btn-sm btn-light" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add line</button></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th style="min-width:220px">Item</th><th style="width:130px">Quantity</th><th style="width:150px">Unit cost</th><th class="text-end" style="width:130px">Line total</th><th style="width:50px"></th></tr></thead>
                        <tbody id="lines"></tbody>
                        <tfoot><tr><td colspan="3" class="text-end">Total</td><td class="text-end" id="grandTotal"><?= money(0) ?></td><td></td></tr></tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2>Bill details</h2></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label" for="supplier">Supplier</label>
                        <select class="form-select" name="supplier_id" id="supplier"><option value="">— None —</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= selected(old('supplier_id', input('supplier')), $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label required" for="pdate">Date</label><input type="date" class="form-control" id="pdate" name="purchase_date" value="<?= e(old('purchase_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required></div>
                    <div class="mb-3"><label class="form-label" for="ref">Supplier invoice / reference</label><input class="form-control" id="ref" name="reference" maxlength="60" value="<?= e(old('reference')) ?>"></div>
                    <div class="mb-3">
                        <label class="form-label" for="paid">Amount paid now</label>
                        <div class="input-group"><input type="number" class="form-control" id="paid" name="paid" min="0" step="0.01" value="<?= e(old('paid', '0')) ?>"><button class="btn btn-light border" type="button" id="payFull">Paid in full</button></div>
                    </div>
                    <div><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="2" maxlength="500"><?= e(old('notes')) ?></textarea></div>
                </div>
            </div>
            <div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>Saving adds quantities to stock and updates each item’s cost to the price paid.</div>
            <div class="d-grid"><button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2 me-1"></i>Save purchase</button></div>
        </div>
    </div>
</form>
<template id="lineTpl">
    <tr>
        <td><select class="form-select form-select-sm" name="items[__i__][item]" required aria-label="Item">
            <option value="">Choose…</option>
            <?php if ($ingredients): ?><optgroup label="Ingredients"><?php foreach ($ingredients as $i): ?><option value="ingredient:<?= $i['id'] ?>" data-cost="<?= e($i['cost']) ?>" data-unit="<?= e($i['unit']) ?>"><?= e($i['name']) ?> (<?= e($i['unit']) ?>)</option><?php endforeach; ?></optgroup><?php endif; ?>
            <?php if ($products): ?><optgroup label="Products"><?php foreach ($products as $pr): ?><option value="product:<?= $pr['id'] ?>" data-cost="<?= e($pr['cost']) ?>" data-unit="pcs"><?= e($pr['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
        </select></td>
        <td><input type="number" class="form-control form-control-sm" name="items[__i__][qty]" min="0" step="0.001" value="1" aria-label="Quantity"></td>
        <td><input type="number" class="form-control form-control-sm" name="items[__i__][unit_cost]" min="0" step="0.0001" value="0" aria-label="Unit cost"></td>
        <td class="text-end fw-semibold" data-line-total><?= money(0) ?></td>
        <td class="text-end"><button type="button" class="btn-icon btn-sm danger" data-remove aria-label="Remove line"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
<script>
(function () {
    let n = 0;
    const lines = document.getElementById('lines');
    const add = () => { lines.insertAdjacentHTML('beforeend', document.getElementById('lineTpl').innerHTML.replaceAll('__i__', n++)); calc(); };
    function calc() {
        let total = 0;
        lines.querySelectorAll('tr').forEach(tr => {
            const q = Number(tr.querySelector('[name$="[qty]"]').value) || 0;
            const c = Number(tr.querySelector('[name$="[unit_cost]"]').value) || 0;
            const t = APP.round(q * c); total += t;
            tr.querySelector('[data-line-total]').textContent = APP.money(t);
        });
        document.getElementById('grandTotal').textContent = APP.money(total);
        return APP.round(total);
    }
    document.getElementById('addLine').addEventListener('click', add);
    lines.addEventListener('click', e => { if (e.target.closest('[data-remove]')) { e.target.closest('tr').remove(); calc(); } });
    lines.addEventListener('change', e => {
        if (e.target.matches('select')) {
            const opt = e.target.selectedOptions[0];
            const cost = e.target.closest('tr').querySelector('[name$="[unit_cost]"]');
            if (opt && opt.dataset.cost && !Number(cost.value)) cost.value = Number(opt.dataset.cost);
        }
        calc();
    });
    lines.addEventListener('input', calc);
    document.getElementById('payFull').addEventListener('click', () => { document.getElementById('paid').value = calc().toFixed(2); });
    add(); add();
})();
</script>
