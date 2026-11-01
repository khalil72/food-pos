<?php
$extra = '<select name="type" class="form-select form-select-sm" style="width:auto" aria-label="Order type"><option value="">All types</option>'
    . implode('', array_map(fn($t) => '<option value="' . $t . '" ' . selected(input('type'), $t) . '>' . order_type_label($t) . '</option>', ['dine_in', 'takeaway', 'delivery'])) . '</select>'
    . '<select name="method" class="form-select form-select-sm" style="width:auto" aria-label="Payment method"><option value="">All payments</option>'
    . implode('', array_map(fn($m) => '<option value="' . $m . '" ' . selected(input('method'), $m) . '>' . payment_label($m) . '</option>', ['cash', 'card', 'online'])) . '</select>'
    . '<select name="status" class="form-select form-select-sm" style="width:auto" aria-label="Status"><option value="">Any status</option>'
    . implode('', array_map(fn($s) => '<option value="' . $s . '" ' . selected(input('status'), $s) . '>' . ucwords(str_replace('_', ' ', $s)) . '</option>', ['completed', 'partially_refunded', 'refunded'])) . '</select>';
if ($users) {
    $extra .= '<select name="user" class="form-select form-select-sm" style="width:auto" aria-label="Cashier"><option value="">All staff</option>'
        . implode('', array_map(fn($u) => '<option value="' . $u['id'] . '" ' . selected(input('user'), $u['id']) . '>' . e($u['name']) . '</option>', $users)) . '</select>';
}
$extra .= '<input type="search" name="q" value="' . e(input('q', '')) . '" class="form-control form-control-sm" style="width:180px" placeholder="Order #, customer, table">';
View::partial('date_filter', compact('from', 'to', 'preset', 'extra'));
?>

<div class="row g-3 mb-3">
    <?php foreach ([
        ['Orders', number_format((int)$totals['cnt']), 'bi-receipt', 'info'],
        ['Gross sales', money($totals['total']), 'bi-cash-stack', 'brand'],
        ['Discounts', money($totals['discounts']), 'bi-percent', 'warning'],
        ['Refunded', money($totals['refunded']), 'bi-arrow-counterclockwise', 'danger'],
    ] as [$l, $v, $i, $t]): ?>
        <div class="col-6 col-lg-3">
            <div class="card kpi"><div class="d-flex align-items-center gap-3"><span class="kpi-icon tone-<?= $t ?>"><i class="bi <?= $i ?>"></i></span><div><div class="kpi-label"><?= $l ?></div><div class="kpi-value" style="font-size:1.25rem;margin:0"><?= $v ?></div></div></div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card table-card">
    <div class="card-header">
        <h2>Order history</h2>
        <?php if (has_role('admin', 'manager')): ?>
            <a href="<?= e(query_with(['r' => 'orders/export', 'page' => null])) ?>" class="btn btn-sm btn-light"><i class="bi bi-download me-1"></i>Export CSV</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Order</th><th>Type</th><th>Customer</th><th>Cashier</th><th>Payment</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= url('orders/view', ['id' => $o['id']]) ?>" class="cell-title"><?= e($o['order_no']) ?></a><div class="cell-sub"><?= fmt_datetime($o['created_at']) ?> · <?= (int)$o['item_count'] ?> items</div></td>
                    <td><i class="bi <?= order_type_icon($o['order_type']) ?> text-muted me-1"></i><?= order_type_label($o['order_type']) ?><?= $o['table_no'] ? ' <span class="cell-sub">· T' . e($o['table_no']) . '</span>' : '' ?></td>
                    <td><?= e($o['customer_name'] ?? 'Walk-in') ?><?php if ($o['customer_phone']): ?><div class="cell-sub"><?= e($o['customer_phone']) ?></div><?php endif; ?></td>
                    <td><?= e($o['cashier']) ?></td>
                    <td><?= payment_label($o['payment_method']) ?></td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td class="text-end fw-semibold"><?= money($o['total']) ?><?php if ((float)$o['refunded_amount'] > 0): ?><div class="cell-sub text-danger">− <?= money($o['refunded_amount']) ?></div><?php endif; ?></td>
                    <td class="text-end">
                        <div class="actions">
                            <a href="<?= url('orders/view', ['id' => $o['id']]) ?>" class="btn-icon btn-sm" title="View" data-bs-toggle="tooltip"><i class="bi bi-eye"></i></a>
                            <a href="<?= url('orders/receipt', ['id' => $o['id'], 'size' => setting('receipt_size', '80')]) ?>" target="_blank" class="btn-icon btn-sm" title="Receipt" data-bs-toggle="tooltip"><i class="bi bi-printer"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="bi bi-receipt"></i></div><p class="mt-2 mb-0">No orders match these filters.</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['p' => $p]); ?>
</div>
