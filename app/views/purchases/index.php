<?php
$extra = '<select name="supplier" class="form-select form-select-sm" style="width:auto" aria-label="Supplier"><option value="">All suppliers</option>'
    . implode('', array_map(fn($s) => '<option value="' . $s['id'] . '" ' . selected(input('supplier'), $s['id']) . '>' . e($s['name']) . '</option>', $suppliers)) . '</select>'
    . '<select name="status" class="form-select form-select-sm" style="width:auto" aria-label="Payment status"><option value="">Any payment</option>'
    . implode('', array_map(fn($s) => '<option value="' . $s . '" ' . selected(input('status'), $s) . '>' . ucfirst($s) . '</option>', ['paid', 'partial', 'unpaid'])) . '</select>';
View::partial('date_filter', compact('from', 'to', 'preset', 'extra'));
?>
<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card kpi"><div class="kpi-label">Purchases</div><div class="kpi-value" style="font-size:1.3rem"><?= money($sum['total']) ?></div><div class="kpi-foot"><?= (int)$sum['cnt'] ?> bills</div></div></div>
    <div class="col-sm-4"><div class="card kpi"><div class="kpi-label">Paid</div><div class="kpi-value" style="font-size:1.3rem"><?= money($sum['paid']) ?></div></div></div>
    <div class="col-sm-4"><div class="card kpi"><div class="kpi-label">Outstanding</div><div class="kpi-value text-danger" style="font-size:1.3rem"><?= money((float)$sum['total'] - (float)$sum['paid']) ?></div></div></div>
</div>
<div class="card table-card">
    <div class="card-header"><h2>Purchase bills</h2><a href="<?= url('purchases/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New purchase</a></div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Reference</th><th>Supplier</th><th>Date</th><th class="text-center">Items</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($purchases as $pu): $st = (float)$pu['paid'] >= (float)$pu['total'] ? 'paid' : ((float)$pu['paid'] > 0 ? 'partial' : 'unpaid'); ?>
                <tr>
                    <td><a href="<?= url('purchases/view', ['id' => $pu['id']]) ?>" class="cell-title"><?= e($pu['reference'] ?: 'PUR-' . $pu['id']) ?></a><div class="cell-sub">by <?= e($pu['user_name']) ?></div></td>
                    <td><?= e($pu['supplier_name'] ?? '—') ?></td>
                    <td><?= fmt_date($pu['purchase_date']) ?></td>
                    <td class="text-center"><?= (int)$pu['item_count'] ?></td>
                    <td class="text-end fw-semibold"><?= money($pu['total']) ?></td>
                    <td class="text-end"><?= money($pu['paid']) ?></td>
                    <td><?= status_badge($st) ?></td>
                    <td class="text-end"><a href="<?= url('purchases/view', ['id' => $pu['id']]) ?>" class="btn-icon btn-sm" aria-label="View"><i class="bi bi-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$purchases): ?><tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="bi bi-truck"></i></div><p class="mt-2 mb-0">No purchases in this period.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['p' => $p]); ?>
</div>
