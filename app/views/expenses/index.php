<?php
$extra = '<select name="category" class="form-select form-select-sm" style="width:auto" aria-label="Category"><option value="">All categories</option>'
    . implode('', array_map(fn($c) => '<option value="' . $c['id'] . '" ' . selected(input('category'), $c['id']) . '>' . e($c['name']) . '</option>', $categories)) . '</select>'
    . '<input type="search" name="q" value="' . e(input('q', '')) . '" class="form-control form-control-sm" style="width:170px" placeholder="Search description">';
View::partial('date_filter', compact('from', 'to', 'preset', 'extra'));
$top = $byCategory[0] ?? null;
$max = max(1, ...array_map(fn($c) => (float)$c['total'], $byCategory ?: [['total' => 1]]));
?>
<!-- Performance cards -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card kpi accent h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">Total expenses</div>
                <span class="kpi-icon tone-dark"><i class="bi bi-wallet2"></i></span>
            </div>
            <div class="kpi-value"><?= money($total) ?></div>
            <div class="kpi-foot"><?= (int)$p['total'] ?> entries · <?= e(range_label($from, $to)) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-6 col-xl-3">
        <div class="card kpi h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">Daily average</div>
                <span class="kpi-icon tone-info"><i class="bi bi-calendar3"></i></span>
            </div>
            <div class="kpi-value" style="font-size:1.35rem"><?= money($total / max(1, $days)) ?></div>
            <div class="kpi-foot">Over <?= $days ?> day<?= $days === 1 ? '' : 's' ?> · Today <?= money($today) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-6 col-xl-3">
        <div class="card kpi h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">Top category</div>
                <span class="kpi-icon tone-warning"><i class="bi bi-pie-chart"></i></span>
            </div>
            <div class="kpi-value text-truncate" style="font-size:1.35rem"><?= e($top['name'] ?? '—') ?></div>
            <div class="kpi-foot"><?= $top ? money($top['total']) . ' · ' . ($total > 0 ? num($top['total'] / $total * 100, 0) : 0) . '% of spend' : 'No expenses yet' ?></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card kpi h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div class="kpi-label">Largest expense</div>
                <span class="kpi-icon tone-danger"><i class="bi bi-arrow-up-right-circle"></i></span>
            </div>
            <div class="kpi-value" style="font-size:1.35rem"><?= $largest ? money($largest['amount']) : '—' ?></div>
            <div class="kpi-foot text-truncate"><?= $largest ? e($largest['description']) . ' · ' . fmt_date($largest['expense_date']) : 'No expenses yet' ?></div>
        </div>
    </div>
</div>

<!-- Category breakdown -->
<div class="card mb-3">
    <div class="card-header">
        <div><h2>Spending by category</h2><p class="card-sub">Share of total expenses in this period</p></div>
        <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#catListModal"><i class="bi bi-gear me-1"></i>Manage categories</button>
    </div>
    <div class="card-body">
        <?php if ($byCategory): ?>
            <div class="progress mb-3" style="height:12px;border-radius:999px" role="img" aria-label="Expense share by category">
                <?php foreach ($byCategory as $c): ?>
                    <div class="progress-bar" style="width:<?= $total > 0 ? $c['total'] / $total * 100 : 0 ?>%;background:<?= e($c['color']) ?>" title="<?= e($c['name']) ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3">
                <?php foreach ($byCategory as $c): ?>
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="p-3 rounded-3 h-100" style="background:var(--surface-2);border:1px solid var(--border)">
                            <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                                <span class="small fw-semibold text-truncate"><span class="color-dot me-1" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?></span>
                                <span class="badge badge-soft-secondary"><?= $total > 0 ? num($c['total'] / $total * 100, 0) : 0 ?>%</span>
                            </div>
                            <div class="fw-bold font-display fs-5"><?= money($c['total']) ?></div>
                            <div class="cell-sub"><?= (int)$c['cnt'] ?> <?= (int)$c['cnt'] === 1 ? 'entry' : 'entries' ?></div>
                            <div class="progress progress-thin mt-2"><div class="progress-bar" style="width:<?= round($c['total'] / $max * 100) ?>%;background:<?= e($c['color']) ?>"></div></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted small mb-0">No expenses in this period.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Full-width entries table, newest first -->
<div class="card table-card">
    <div class="card-header">
        <div><h2>Expense entries</h2><p class="card-sub">Newest first</p></div>
        <div class="d-flex gap-2">
            <a href="<?= e(query_with(['r' => 'expenses/export', 'page' => null])) ?>" class="btn btn-sm btn-light"><i class="bi bi-download me-1"></i>CSV</a>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-plus-lg me-1"></i>Record expense</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Paid by</th><th>Reference</th><th>Recorded by</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($expenses as $x): ?>
                <tr>
                    <td class="text-nowrap"><div class="cell-title"><?= fmt_date($x['expense_date']) ?></div><div class="cell-sub"><?= date('h:i A', strtotime($x['created_at'])) ?></div></td>
                    <td><div class="cell-title"><?= e($x['description']) ?></div><?= $x['register_id'] ? '<div class="cell-sub"><i class="bi bi-cash-coin"></i> Paid from drawer</div>' : '' ?></td>
                    <td><span class="badge badge-soft-secondary"><span class="color-dot me-1" style="background:<?= e($x['category_color'] ?? '#9CA3AF') ?>;width:8px;height:8px"></span><?= e($x['category_name'] ?? 'Uncategorised') ?></span></td>
                    <td class="text-nowrap"><?= payment_label($x['payment_method']) ?></td>
                    <td class="small"><?= e($x['reference'] ?: '—') ?></td>
                    <td class="small text-nowrap"><?= e($x['user_name']) ?></td>
                    <td class="text-end fw-semibold"><?= money($x['amount']) ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#expenseModal" data-fill='<?= e(json_encode(array_intersect_key($x, array_flip(['id', 'category_id', 'expense_date', 'amount', 'payment_method', 'reference', 'description'])))) ?>' aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= url('expenses/delete', ['id' => $x['id']]) ?>" data-confirm="Delete this expense of <?= e(money($x['amount'])) ?>?" data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$expenses): ?><tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="bi bi-wallet2"></i></div><p class="mt-2 mb-0">No expenses recorded for this period.</p></div></td></tr><?php endif; ?>
            </tbody>
            <?php if ($expenses): ?><tfoot><tr><td colspan="6">Total for this period</td><td class="text-end"><?= money($total) ?></td><td></td></tr></tfoot><?php endif; ?>
        </table>
    </div>
    <?php View::partial('pagination', ['p' => $p]); ?>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('expenses/save') ?>" data-fill-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0" data-reset="0">
            <div class="modal-header"><h5 class="modal-title" data-modal-title data-create="Record expense" data-edit="Edit expense">Record expense</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label required" for="exDesc">Description</label><input class="form-control" id="exDesc" name="description" maxlength="255" required placeholder="e.g. Electricity bill – October"></div>
                    <div class="col-6"><label class="form-label required" for="exCat">Category</label><select class="form-select" id="exCat" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6"><label class="form-label required" for="exAmt">Amount</label><input type="number" class="form-control" id="exAmt" name="amount" min="0.01" step="0.01" required></div>
                    <div class="col-6"><label class="form-label required" for="exDate">Date</label><input type="date" class="form-control" id="exDate" name="expense_date" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="col-6"><label class="form-label" for="exMethod">Paid by</label><select class="form-select" id="exMethod" name="payment_method"><?php foreach (['cash', 'card', 'online', 'bank'] as $m): ?><option value="<?= $m ?>"><?= payment_label($m) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label" for="exRef">Reference / receipt no.</label><input class="form-control" id="exRef" name="reference" maxlength="60"></div>
                    <div class="col-12" data-new-only>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="from_drawer" value="1" id="exDrawer" <?= $register ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="exDrawer">Paid in cash from my register drawer</label>
                        </div>
                        <div class="form-text"><?= $register ? 'Deducted from the expected cash when you close the register.' : 'Open a cash register to use this option.' ?></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save expense</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="catListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Expense categories</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body p-0">
                <?php foreach ($categories as $c): ?>
                    <div class="list-row">
                        <form method="post" action="<?= url('expenses/category-save') ?>" class="d-flex gap-2 align-items-center flex-grow-1">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <input type="color" name="color" value="<?= e($c['color']) ?>" class="form-control form-control-color form-control-sm" aria-label="Colour">
                            <input name="name" value="<?= e($c['name']) ?>" class="form-control form-control-sm" maxlength="80" aria-label="Name">
                            <button class="btn btn-sm btn-light" title="Save"><i class="bi bi-check2"></i></button>
                        </form>
                        <form method="post" action="<?= url('expenses/category-delete', ['id' => $c['id']]) ?>" data-confirm="Delete “<?= e($c['name']) ?>”? <?= (int)$c['used'] ?> expense(s) will become uncategorised." data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button></form>
                    </div>
                <?php endforeach; ?>
            </div>
            <form class="modal-footer flex-nowrap" method="post" action="<?= url('expenses/category-save') ?>">
                <?= csrf_field() ?>
                <input type="color" name="color" value="#6B7280" class="form-control form-control-color" aria-label="Colour">
                <input name="name" class="form-control" placeholder="New category name" maxlength="80" required aria-label="New category name">
                <button class="btn btn-primary text-nowrap">Add</button>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('expenseModal').addEventListener('show.bs.modal', (e) => {
    const editing = e.relatedTarget && e.relatedTarget.dataset.fill;
    document.querySelectorAll('#expenseModal [data-new-only]').forEach(el => el.hidden = !!editing);
});
</script>
