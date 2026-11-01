<?php
$refundable = array_filter($order['items'], fn($i) => (int)$i['qty'] > (int)$i['returned_qty']);
$canRefund = has_role('admin', 'manager') && $order['status'] !== 'refunded' && $refundable;
?>
<div class="toolbar">
    <a href="<?= url('orders') ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All orders</a>
    <div class="d-flex gap-2 flex-wrap">
        <div class="btn-group">
            <a href="<?= url('orders/receipt', ['id' => $order['id'], 'size' => '80', 'autoprint' => 1]) ?>" target="_blank" class="btn btn-dark btn-sm"><i class="bi bi-printer me-1"></i>80mm</a>
            <a href="<?= url('orders/receipt', ['id' => $order['id'], 'size' => '58', 'autoprint' => 1]) ?>" target="_blank" class="btn btn-dark btn-sm">58mm</a>
            <a href="<?= url('orders/receipt', ['id' => $order['id'], 'size' => 'a4']) ?>" target="_blank" class="btn btn-dark btn-sm"><i class="bi bi-file-earmark-text me-1"></i>A4 / PDF</a>
        </div>
        <?php if ($canRefund): ?>
            <button class="btn btn-soft-danger btn-sm" data-bs-toggle="modal" data-bs-target="#refundModal"><i class="bi bi-arrow-counterclockwise me-1"></i>Refund / return</button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card table-card mb-3">
            <div class="card-header">
                <div><h2><?= e($order['order_no']) ?></h2><p class="card-sub"><?= fmt_datetime($order['created_at']) ?></p></div>
                <?= status_badge($order['status']) ?>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Unit</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] as $it): ?>
                        <tr>
                            <td>
                                <div class="cell-title"><?= e($it['product_name']) ?><?= $it['variant_name'] ? ' <span class="text-muted fw-normal">(' . e($it['variant_name']) . ')</span>' : '' ?></div>
                                <?php if ($it['addons_text']): ?><div class="cell-sub">+ <?= e($it['addons_text']) ?></div><?php endif; ?>
                                <?php if ($it['note']): ?><div class="cell-sub text-warning"><i class="bi bi-chat-left-dots"></i> <?= e($it['note']) ?></div><?php endif; ?>
                                <?php if ((int)$it['returned_qty'] > 0): ?><span class="badge badge-soft-danger mt-1"><?= (int)$it['returned_qty'] ?> returned</span><?php endif; ?>
                            </td>
                            <td class="text-center"><?= (int)$it['qty'] ?></td>
                            <td class="text-end"><?= money($it['unit_price']) ?></td>
                            <td class="text-end fw-semibold"><?= money($it['line_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top">
                <div class="ms-auto" style="max-width:340px">
                    <div class="stat-line"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
                    <?php if ((float)$order['discount_amount'] > 0): ?><div class="stat-line text-success"><span>Discount <?= $order['discount_type'] === 'percent' ? '(' . num($order['discount_value'], 0) . '%)' : '' ?></span><span>− <?= money($order['discount_amount']) ?></span></div><?php endif; ?>
                    <?php if ((float)$order['loyalty_discount'] > 0): ?><div class="stat-line text-success"><span>Loyalty (<?= (int)$order['loyalty_points_used'] ?> pts)</span><span>− <?= money($order['loyalty_discount']) ?></span></div><?php endif; ?>
                    <?php if ((float)$order['tax_rate'] > 0): ?><div class="stat-line"><span><?= e(setting('tax_name', 'Tax')) ?> <?= qty($order['tax_rate']) ?>%<?= $order['tax_inclusive'] ? ' (incl.)' : '' ?></span><span><?= money($order['tax_amount']) ?></span></div><?php endif; ?>
                    <?php if ((float)$order['delivery_fee'] > 0): ?><div class="stat-line"><span>Delivery fee</span><span><?= money($order['delivery_fee']) ?></span></div><?php endif; ?>
                    <div class="stat-line total"><span>Total</span><span><?= money($order['total']) ?></span></div>
                    <?php if ((float)$order['refunded_amount'] > 0): ?>
                        <div class="stat-line text-danger"><span>Refunded</span><span>− <?= money($order['refunded_amount']) ?></span></div>
                        <div class="stat-line fw-bold"><span>Net</span><span><?= money((float)$order['total'] - (float)$order['refunded_amount']) ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($order['returns']): ?>
            <div class="card table-card">
                <div class="card-header"><h2>Refunds &amp; returns</h2></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Date</th><th>By</th><th>Method</th><th>Reason</th><th>Restocked</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($order['returns'] as $r): ?>
                            <tr><td><?= fmt_datetime($r['created_at']) ?></td><td><?= e($r['user_name']) ?></td><td><?= payment_label($r['refund_method']) ?></td><td><?= e($r['reason'] ?: '—') ?></td><td><?= $r['restocked'] ? 'Yes' : 'No' ?></td><td class="text-end fw-semibold text-danger"><?= money($r['amount']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h2>Details</h2></div>
            <div class="card-body">
                <div class="stat-line"><span class="text-muted">Type</span><span><i class="bi <?= order_type_icon($order['order_type']) ?> me-1"></i><?= order_type_label($order['order_type']) ?></span></div>
                <?php if ($order['table_no']): ?><div class="stat-line"><span class="text-muted">Table</span><span><?= e($order['table_no']) ?></span></div><?php endif; ?>
                <div class="stat-line"><span class="text-muted">Cashier</span><span><?= e($order['cashier']) ?></span></div>
                <div class="stat-line"><span class="text-muted">Payment</span><span><?= payment_label($order['payment_method']) ?></span></div>
                <?php if ($order['payment_method'] === 'cash'): ?>
                    <div class="stat-line"><span class="text-muted">Received</span><span><?= money($order['tendered']) ?></span></div>
                    <div class="stat-line"><span class="text-muted">Change</span><span><?= money($order['change_amount']) ?></span></div>
                <?php endif; ?>
                <?php foreach ($order['payments'] as $pay): if ($pay['reference']): ?>
                    <div class="stat-line"><span class="text-muted">Reference</span><span><?= e($pay['reference']) ?></span></div>
                <?php endif; endforeach; ?>
                <?php if ($order['notes']): ?><div class="mt-3 p-2 rounded small" style="background:var(--warning-bg);color:var(--warning)"><i class="bi bi-chat-left-text me-1"></i><?= e($order['notes']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2>Customer</h2></div>
            <div class="card-body">
                <?php if ($order['customer_id']): ?>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span class="avatar avatar-lg"><?= e(initials($order['customer_name'])) ?></span>
                        <div><a href="<?= url('customers/view', ['id' => $order['customer_id']]) ?>" class="cell-title"><?= e($order['customer_name']) ?></a><div class="cell-sub"><?= e($order['customer_phone'] ?? '') ?></div></div>
                    </div>
                    <?php if ($order['loyalty_points_earned']): ?><div class="small text-success"><i class="bi bi-star-fill"></i> Earned <?= (int)$order['loyalty_points_earned'] ?> points on this order</div><?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">Walk-in customer</p>
                <?php endif; ?>
                <?php if ($order['delivery_address']): ?><div class="mt-2 small"><i class="bi bi-geo-alt me-1 text-muted"></i><?= e($order['delivery_address']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canRefund): ?>
<div class="modal fade" id="refundModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" method="post" action="<?= url('orders/refund', ['id' => $order['id']]) ?>" data-confirm="Refund the selected items? This cannot be undone." data-confirm-ok="Refund">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Refund / return items</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted">Choose quantities to return. Amounts include each item’s share of discounts and tax.</span>
                    <button type="button" class="btn btn-sm btn-light" onclick="document.querySelectorAll('[data-refund-qty]').forEach(i=>i.value=i.max);refundCalc()">Select all</button>
                </div>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Item</th><th class="text-center">Sold</th><th class="text-center">Returned</th><th style="width:110px">Refund qty</th></tr></thead>
                    <tbody>
                    <?php foreach ($order['items'] as $it): $avail = (int)$it['qty'] - (int)$it['returned_qty']; ?>
                        <tr>
                            <td><?= e($it['product_name']) ?><?= $it['variant_name'] ? ' (' . e($it['variant_name']) . ')' : '' ?><div class="cell-sub"><?= money($it['unit_price']) ?> each</div></td>
                            <td class="text-center"><?= (int)$it['qty'] ?></td>
                            <td class="text-center"><?= (int)$it['returned_qty'] ?></td>
                            <td><input type="number" class="form-control form-control-sm" name="qty[<?= (int)$it['id'] ?>]" min="0" max="<?= $avail ?>" value="0" data-refund-qty data-price="<?= e($it['unit_price']) ?>" <?= $avail ? '' : 'disabled' ?> oninput="refundCalc()" aria-label="Refund quantity"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Refund via</label>
                        <select name="refund_method" class="form-select">
                            <?php foreach (['cash', 'card', 'online'] as $m): ?><option value="<?= $m ?>" <?= selected($order['payment_method'], $m) ?>><?= payment_label($m) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="refundReason">Reason</label>
                        <input type="text" name="reason" id="refundReason" class="form-control" maxlength="255" placeholder="e.g. wrong item, customer complaint">
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="restock" value="1" id="restock">
                            <label class="form-check-label" for="restock">Return stock-tracked items to inventory (e.g. sealed drinks)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>Estimated refund: <strong class="fs-5" id="refundEst"><?= money(0) ?></strong></div>
                <div><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button> <button type="submit" class="btn btn-danger">Process refund</button></div>
            </div>
        </form>
    </div>
</div>
<script>
function refundCalc() {
    const ratio = <?= (float)$order['subtotal'] > 0 ? ((float)$order['total'] - (float)$order['delivery_fee']) / (float)$order['subtotal'] : 0 ?>;
    let total = 0, all = true;
    document.querySelectorAll('[data-refund-qty]').forEach(i => {
        const q = Math.min(Number(i.max), Math.max(0, Number(i.value) || 0));
        total += APP.round(Number(i.dataset.price) * q * ratio);
        if (q < Number(i.max)) all = false;
    });
    if (all) total = <?= (float)$order['total'] - (float)$order['refunded_amount'] ?>;
    document.getElementById('refundEst').textContent = APP.money(total);
}
</script>
<?php endif; ?>
