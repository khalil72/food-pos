<?php $c = $customer; $pointValue = (float)setting('loyalty_point_value', 1); ?>
<div class="toolbar">
    <a href="<?= url('customers') ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All customers</a>
    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#customerModal" data-fill='<?= e(json_encode(array_intersect_key($c, array_flip(['id', 'name', 'phone', 'email', 'address', 'notes'])))) ?>'><i class="bi bi-pencil me-1"></i>Edit</button>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <span class="avatar mx-auto" style="width:72px;height:72px;font-size:1.5rem"><?= e(initials($c['name'])) ?></span>
                <h2 class="h5 mt-3 mb-1"><?= e($c['name']) ?></h2>
                <p class="text-muted small mb-3">Customer since <?= fmt_date($c['created_at']) ?></p>
                <div class="text-start">
                    <?php foreach ([['bi-telephone', $c['phone']], ['bi-envelope', $c['email']], ['bi-geo-alt', $c['address']]] as [$i, $v]): if ($v): ?>
                        <div class="mb-2"><i class="bi <?= $i ?> text-muted me-2"></i><?= e($v) ?></div>
                    <?php endif; endforeach; ?>
                    <?php if ($c['notes']): ?><div class="p-2 rounded small mt-2" style="background:var(--warning-bg);color:var(--warning)"><?= nl2br(e($c['notes'])) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card kpi accent mb-3">
            <div class="kpi-label">Loyalty balance</div>
            <div class="kpi-value"><i class="bi bi-star-fill" style="color:var(--amber)"></i> <?= (int)$c['loyalty_points'] ?> pts</div>
            <div class="kpi-foot">Worth <?= money((int)$c['loyalty_points'] * $pointValue) ?></div>
        </div>
        <?php if (has_role('admin', 'manager')): ?>
        <div class="card mb-3">
            <div class="card-header"><h2>Adjust points</h2></div>
            <form class="card-body" method="post" action="<?= url('customers/points', ['id' => $c['id']]) ?>">
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-4"><input type="number" class="form-control" name="points" placeholder="±pts" required aria-label="Points (use negative to deduct)"></div>
                    <div class="col-8"><input class="form-control" name="reason" placeholder="Reason" maxlength="200" required aria-label="Reason"></div>
                </div>
                <button class="btn btn-light w-100 mt-2">Apply</button>
            </form>
        </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header"><h2>Favourites</h2></div>
            <?php foreach ($favourites as $f): ?><div class="list-row"><span class="flex-grow-1"><?= e($f['product_name']) ?></span><span class="badge badge-soft-primary"><?= (int)$f['qty'] ?>×</span></div><?php endforeach; ?>
            <?php if (!$favourites): ?><div class="empty-state py-3 small">No orders yet.</div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-4"><div class="card kpi"><div class="kpi-label">Total spent</div><div class="kpi-value" style="font-size:1.2rem"><?= money($c['total_spent']) ?></div></div></div>
            <div class="col-4"><div class="card kpi"><div class="kpi-label">Orders</div><div class="kpi-value" style="font-size:1.2rem"><?= (int)$stats['cnt'] ?></div></div></div>
            <div class="col-4"><div class="card kpi"><div class="kpi-label">Avg order</div><div class="kpi-value" style="font-size:1.2rem"><?= money($stats['avg']) ?></div></div></div>
        </div>
        <div class="card table-card">
            <div class="card-header"><h2>Order history</h2></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Order</th><th>Type</th><th>Status</th><th class="text-center">Points</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><a href="<?= url('orders/view', ['id' => $o['id']]) ?>" class="cell-title"><?= e($o['order_no']) ?></a><div class="cell-sub"><?= fmt_datetime($o['created_at']) ?></div></td>
                            <td><?= order_type_label($o['order_type']) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td class="text-center small"><?= $o['loyalty_points_earned'] ? '+' . (int)$o['loyalty_points_earned'] : '' ?><?= $o['loyalty_points_used'] ? ' −' . (int)$o['loyalty_points_used'] : '' ?></td>
                            <td class="text-end fw-semibold"><?= money($o['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$orders): ?><tr><td colspan="5"><div class="empty-state">No orders yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/_modal.php'; ?>
