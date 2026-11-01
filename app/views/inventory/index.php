<?php
$reasonBadge = ['sale' => 'info', 'purchase' => 'success', 'return' => 'primary', 'adjustment' => 'secondary', 'wastage' => 'danger', 'purchase_delete' => 'warning'];
$units = ['pcs', 'kg', 'g', 'L', 'ml', 'pack', 'dozen', 'box', 'bottle', 'can'];
?>
<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card kpi"><div class="d-flex align-items-center gap-3"><span class="kpi-icon tone-brand"><i class="bi bi-basket"></i></span><div><div class="kpi-label">Ingredients</div><div class="kpi-value" style="font-size:1.3rem;margin:0"><?= count($allIngredients) ?></div></div></div></div></div>
    <div class="col-sm-4"><div class="card kpi"><div class="d-flex align-items-center gap-3"><span class="kpi-icon tone-success"><i class="bi bi-safe"></i></span><div><div class="kpi-label">Stock value (at cost)</div><div class="kpi-value" style="font-size:1.3rem;margin:0"><?= money($valuation) ?></div></div></div></div></div>
    <div class="col-sm-4"><a href="<?= url('inventory', ['tab' => 'low']) ?>" class="card kpi d-block text-reset"><div class="d-flex align-items-center gap-3"><span class="kpi-icon tone-<?= $lowCount ? 'danger' : 'success' ?>"><i class="bi bi-exclamation-triangle"></i></span><div><div class="kpi-label">Low stock items</div><div class="kpi-value" style="font-size:1.3rem;margin:0"><?= $lowCount ?></div></div></div></a></div>
</div>

<div class="card table-card">
    <div class="card-header pb-0 border-0">
        <ul class="nav nav-tabs-clean w-100">
            <li class="nav-item"><a class="nav-link <?= $tab === 'ingredients' ? 'active' : '' ?>" href="<?= url('inventory') ?>">Ingredients</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab === 'products' ? 'active' : '' ?>" href="<?= url('inventory', ['tab' => 'products']) ?>">Product stock</a></li>
            <li class="nav-item"><a class="nav-link <?= $tab === 'low' ? 'active' : '' ?>" href="<?= url('inventory', ['tab' => 'low']) ?>">Low stock <?= $lowCount ? '<span class="badge badge-soft-danger ms-1">' . $lowCount . '</span>' : '' ?></a></li>
            <li class="nav-item"><a class="nav-link <?= $tab === 'movements' ? 'active' : '' ?>" href="<?= url('inventory', ['tab' => 'movements']) ?>">Movement history</a></li>
            <li class="ms-auto d-flex gap-2 pb-2">
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#adjustModal"><i class="bi bi-sliders me-1"></i>Adjust stock</button>
                <?php if ($tab === 'ingredients'): ?><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#ingModal"><i class="bi bi-plus-lg me-1"></i>Add ingredient</button><?php endif; ?>
            </li>
        </ul>
    </div>

    <div class="table-responsive">
    <?php if ($tab === 'ingredients'): ?>
        <table class="table table-hover">
            <thead><tr><th>Ingredient</th><th>Supplier</th><th class="text-end">Cost / unit</th><th class="text-center">In stock</th><th class="text-center">Reorder at</th><th class="text-end">Value</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($ingredients as $i): $low = (float)$i['stock_qty'] <= (float)$i['low_stock_level']; ?>
                <tr>
                    <td><div class="cell-title"><?= e($i['name']) ?></div><div class="cell-sub">Used in <?= (int)$i['used_in'] ?> recipe(s)</div></td>
                    <td><?= e($i['supplier_name'] ?? '—') ?></td>
                    <td class="text-end"><?= money($i['cost_per_unit']) ?> <span class="cell-sub">/ <?= e($i['unit']) ?></span></td>
                    <td class="text-center"><span class="badge badge-soft-<?= (float)$i['stock_qty'] <= 0 ? 'danger' : ($low ? 'warning' : 'success') ?>"><?= qty($i['stock_qty']) ?> <?= e($i['unit']) ?></span></td>
                    <td class="text-center"><?= qty($i['low_stock_level']) ?> <?= e($i['unit']) ?></td>
                    <td class="text-end"><?= money(max(0, (float)$i['stock_qty']) * (float)$i['cost_per_unit']) ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#adjustModal" data-item="ingredient:<?= $i['id'] ?>" title="Adjust stock" aria-label="Adjust stock"><i class="bi bi-sliders"></i></button>
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#ingModal" data-fill='<?= e(json_encode(['id' => $i['id'], 'name' => $i['name'], 'unit' => $i['unit'], 'cost_per_unit' => (float)$i['cost_per_unit'], 'low_stock_level' => (float)$i['low_stock_level'], 'supplier_id' => $i['supplier_id']])) ?>' aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= url('inventory/delete', ['id' => $i['id']]) ?>" data-confirm="Delete “<?= e($i['name']) ?>”? It will be removed from all recipes." data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$ingredients): ?><tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="bi bi-basket"></i></div><p class="mt-2 mb-0">No ingredients yet. Add buns, patties, oil, cheese…</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($tab === 'products'): ?>
        <table class="table table-hover">
            <thead><tr><th>Product</th><th>Category</th><th class="text-center">In stock</th><th class="text-center">Alert at</th><th class="text-end">Value</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $pr): $low = (float)$pr['stock_qty'] <= (float)$pr['low_stock_level']; ?>
                <tr>
                    <td><a href="<?= url('products/edit', ['id' => $pr['id']]) ?>" class="cell-title"><?= e($pr['name']) ?></a><div class="cell-sub"><?= e($pr['sku'] ?: '') ?></div></td>
                    <td><?= e($pr['category_name'] ?? '—') ?></td>
                    <td class="text-center"><span class="badge badge-soft-<?= (float)$pr['stock_qty'] <= 0 ? 'danger' : ($low ? 'warning' : 'success') ?>"><?= qty($pr['stock_qty']) ?></span></td>
                    <td class="text-center"><?= qty($pr['low_stock_level']) ?></td>
                    <td class="text-end"><?= money(max(0, (float)$pr['stock_qty']) * (float)$pr['cost']) ?></td>
                    <td><div class="actions"><button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#adjustModal" data-item="product:<?= $pr['id'] ?>" aria-label="Adjust stock"><i class="bi bi-sliders"></i></button></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="bi bi-box-seam"></i></div><p class="mt-2 mb-0">No products have stock tracking turned on.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($tab === 'low'): ?>
        <table class="table table-hover">
            <thead><tr><th>Item</th><th>Type</th><th class="text-center">In stock</th><th class="text-center">Reorder at</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($low as $l): ?>
                <tr>
                    <td class="cell-title"><?= e($l['name']) ?></td>
                    <td><?= ucfirst($l['type']) ?></td>
                    <td class="text-center"><span class="badge badge-soft-<?= (float)$l['stock_qty'] <= 0 ? 'danger' : 'warning' ?>"><?= qty($l['stock_qty']) ?> <?= e($l['unit']) ?></span></td>
                    <td class="text-center"><?= qty($l['low_stock_level']) ?> <?= e($l['unit']) ?></td>
                    <td><div class="actions">
                        <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#adjustModal" data-item="<?= $l['type'] ?>:<?= $l['id'] ?>" aria-label="Adjust stock"><i class="bi bi-sliders"></i></button>
                        <a href="<?= url('purchases/create') ?>" class="btn btn-sm btn-light">Reorder</a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$low): ?><tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><i class="bi bi-check2-circle"></i></div><p class="mt-2 mb-0">Everything is above its reorder level.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <form method="get" class="d-flex gap-2 p-3 border-bottom flex-wrap">
            <input type="hidden" name="r" value="inventory"><input type="hidden" name="tab" value="movements">
            <select name="type" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()" aria-label="Item type"><option value="">All items</option><option value="product" <?= selected(input('type'), 'product') ?>>Products</option><option value="ingredient" <?= selected(input('type'), 'ingredient') ?>>Ingredients</option></select>
            <select name="reason" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()" aria-label="Reason"><option value="">All reasons</option><?php foreach (array_keys($reasonBadge) as $r): ?><option value="<?= $r ?>" <?= selected(input('reason'), $r) ?>><?= ucfirst(str_replace('_', ' ', $r)) ?></option><?php endforeach; ?></select>
        </form>
        <table class="table table-hover">
            <thead><tr><th>Date</th><th>Item</th><th>Reason</th><th>Reference</th><th class="text-end">Change</th><th class="text-end">Balance</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
                <tr>
                    <td class="small"><?= fmt_datetime($m['created_at']) ?></td>
                    <td><div class="cell-title"><?= e($m['item_name'] ?? '(deleted)') ?></div><div class="cell-sub"><?= ucfirst($m['item_type']) ?><?= $m['note'] ? ' · ' . e($m['note']) : '' ?></div></td>
                    <td><span class="badge badge-soft-<?= $reasonBadge[$m['reason']] ?? 'secondary' ?>"><?= ucfirst(str_replace('_', ' ', $m['reason'])) ?></span></td>
                    <td class="small"><?= e($m['reference'] ?? '—') ?></td>
                    <td class="text-end fw-semibold <?= (float)$m['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= (float)$m['change_qty'] > 0 ? '+' : '' ?><?= qty($m['change_qty']) ?> <?= e($m['unit']) ?></td>
                    <td class="text-end"><?= qty($m['balance_after']) ?></td>
                    <td class="small"><?= e($m['user_name'] ?? 'System') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$movements): ?><tr><td colspan="7"><div class="empty-state">No stock movements recorded.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
    <?php if ($tab === 'movements') View::partial('pagination', ['p' => $p]); ?>
</div>

<div class="modal fade" id="ingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('inventory/save') ?>" data-fill-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0" data-reset="0">
            <div class="modal-header"><h5 class="modal-title" data-modal-title data-create="Add ingredient" data-edit="Edit ingredient">Add ingredient</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-8"><label class="form-label required" for="iName">Name</label><input class="form-control" id="iName" name="name" maxlength="100" required></div>
                    <div class="col-4"><label class="form-label required" for="iUnit">Unit</label><input class="form-control" id="iUnit" name="unit" list="unitList" value="pcs" maxlength="20" required><datalist id="unitList"><?php foreach ($units as $u): ?><option value="<?= $u ?>"><?php endforeach; ?></datalist></div>
                    <div class="col-6"><label class="form-label" for="iCost">Cost per unit</label><input type="number" class="form-control" id="iCost" name="cost_per_unit" min="0" step="0.0001" value="0"></div>
                    <div class="col-6"><label class="form-label" for="iLow">Reorder level</label><input type="number" class="form-control" id="iLow" name="low_stock_level" min="0" step="0.001" value="0"></div>
                    <div class="col-6"><label class="form-label" for="iSupplier">Supplier</label><select class="form-select" id="iSupplier" name="supplier_id"><option value="">— None —</option><?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6" data-new-only><label class="form-label" for="iStock">Opening stock</label><input type="number" class="form-control" id="iStock" name="stock_qty" min="0" step="0.001" value="0"></div>
                    <div class="col-12"><p class="form-text mb-0">Stock for existing ingredients changes through purchases and adjustments so every movement is recorded.</p></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save ingredient</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="adjustModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('inventory/adjust') ?>">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Adjust stock</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="adjItem">Item</label>
                        <select class="form-select" id="adjItem" name="item" required>
                            <option value="">Choose an item…</option>
                            <?php if ($allIngredients): ?><optgroup label="Ingredients"><?php foreach ($allIngredients as $i): ?><option value="ingredient:<?= $i['id'] ?>"><?= e($i['name']) ?> (<?= e($i['unit']) ?>)</option><?php endforeach; ?></optgroup><?php endif; ?>
                            <?php if ($allProducts): ?><optgroup label="Products"><?php foreach ($allProducts as $pr): ?><option value="product:<?= $pr['id'] ?>"><?= e($pr['name']) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="segmented w-100" role="radiogroup">
                            <?php foreach (['add' => 'Add stock', 'remove' => 'Remove stock', 'set' => 'Set exact count'] as $k => $l): ?>
                                <input type="radio" class="btn-check" name="direction" value="<?= $k ?>" id="dir<?= $k ?>" <?= $k === 'add' ? 'checked' : '' ?>><label class="btn btn-sm flex-fill border-0" for="dir<?= $k ?>"><?= $l ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-6"><label class="form-label required" for="adjQty">Quantity</label><input type="number" class="form-control" id="adjQty" name="qty" min="0" step="0.001" required></div>
                    <div class="col-6"><label class="form-label" for="adjReason">Reason (for removals)</label><select class="form-select" id="adjReason" name="reason"><option value="adjustment">Count correction</option><option value="wastage">Wastage / spoilage</option></select></div>
                    <div class="col-12"><label class="form-label" for="adjNote">Note</label><input class="form-control" id="adjNote" name="note" maxlength="255" placeholder="Optional"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save adjustment</button></div>
        </form>
    </div>
</div>
<style>.segmented .btn-check:checked + .btn { background: var(--surface); color: var(--brand-600); box-shadow: var(--shadow-sm); }</style>
<script>
document.getElementById('adjustModal').addEventListener('show.bs.modal', (e) => {
    const item = e.relatedTarget && e.relatedTarget.dataset.item;
    document.getElementById('adjItem').value = item || '';
});
document.getElementById('ingModal').addEventListener('show.bs.modal', (e) => {
    const editing = e.relatedTarget && e.relatedTarget.dataset.fill;
    document.querySelectorAll('#ingModal [data-new-only]').forEach(el => el.hidden = !!editing);
});
</script>
