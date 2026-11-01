<?php $pu = $purchase; $due = (float)$pu['total'] - (float)$pu['paid']; $st = $due <= 0 ? 'paid' : ((float)$pu['paid'] > 0 ? 'partial' : 'unpaid'); ?>
<div class="toolbar">
    <a href="<?= url('purchases') ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All purchases</a>
    <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <?php if (has_role('admin')): ?>
            <form method="post" action="<?= url('purchases/delete', ['id' => $pu['id']]) ?>" data-confirm="Delete this purchase? Received quantities will be removed from stock." data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn btn-soft-danger btn-sm"><i class="bi bi-trash3 me-1"></i>Delete</button></form>
        <?php endif; ?>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><div><h2><?= e($pu['reference'] ?: 'PUR-' . $pu['id']) ?></h2><p class="card-sub">Recorded by <?= e($pu['user_name']) ?> on <?= fmt_datetime($pu['created_at']) ?></p></div><?= status_badge($st) ?></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Item</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr><td class="cell-title"><?= e($it['item_name']) ?></td><td><?= ucfirst($it['item_type']) ?></td><td class="text-end"><?= qty($it['qty']) ?></td><td class="text-end"><?= money($it['unit_cost']) ?></td><td class="text-end fw-semibold"><?= money($it['line_total']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="4" class="text-end">Total</td><td class="text-end"><?= money($pu['total']) ?></td></tr></tfoot>
                </table>
            </div>
            <?php if ($pu['notes']): ?><div class="card-body border-top small text-muted"><?= nl2br(e($pu['notes'])) ?></div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header"><h2>Supplier</h2></div><div class="card-body">
            <?php if ($pu['supplier_id']): ?>
                <a href="<?= url('suppliers/view', ['id' => $pu['supplier_id']]) ?>" class="cell-title"><?= e($pu['supplier_name']) ?></a>
                <div class="cell-sub"><?= e(implode(' · ', array_filter([$pu['supplier_phone'], $pu['supplier_email']]))) ?></div>
            <?php else: ?><span class="text-muted">No supplier recorded</span><?php endif; ?>
        </div></div>
        <div class="card"><div class="card-header"><h2>Payment</h2></div><div class="card-body">
            <div class="stat-line"><span>Total</span><strong><?= money($pu['total']) ?></strong></div>
            <div class="stat-line"><span>Paid</span><strong><?= money($pu['paid']) ?></strong></div>
            <div class="stat-line"><span>Balance due</span><strong class="<?= $due > 0 ? 'text-danger' : 'text-success' ?>"><?= money(max(0, $due)) ?></strong></div>
            <?php if ($due > 0): ?>
                <form method="post" action="<?= url('purchases/payment', ['id' => $pu['id']]) ?>" class="mt-3 no-print">
                    <?= csrf_field() ?>
                    <label class="form-label" for="payAmt">Record a payment</label>
                    <div class="input-group"><input type="number" class="form-control" id="payAmt" name="amount" min="0.01" max="<?= $due ?>" step="0.01" value="<?= number_format($due, 2, '.', '') ?>" required><button class="btn btn-primary">Save</button></div>
                </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
