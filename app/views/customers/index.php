<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card kpi"><div class="d-flex align-items-center gap-3"><span class="kpi-icon tone-brand"><i class="bi bi-people"></i></span><div><div class="kpi-label">Customers</div><div class="kpi-value" style="font-size:1.3rem;margin:0"><?= number_format((int)$stats['cnt']) ?></div></div></div></div></div>
    <div class="col-sm-4"><div class="card kpi"><div class="d-flex align-items-center gap-3"><span class="kpi-icon tone-success"><i class="bi bi-cash-stack"></i></span><div><div class="kpi-label">Lifetime spend</div><div class="kpi-value" style="font-size:1.3rem;margin:0"><?= money($stats['spent']) ?></div></div></div></div></div>
    <div class="col-sm-4"><div class="card kpi"><div class="d-flex align-items-center gap-3"><span class="kpi-icon tone-warning"><i class="bi bi-star"></i></span><div><div class="kpi-label">Points outstanding</div><div class="kpi-value" style="font-size:1.3rem;margin:0"><?= number_format((int)$stats['points']) ?></div></div></div></div></div>
</div>
<form method="get" class="toolbar">
    <input type="hidden" name="r" value="customers">
    <div class="d-flex gap-2 flex-wrap">
        <div class="search-box"><i class="bi bi-search"></i><input type="search" name="q" value="<?= e(input('q', '')) ?>" class="form-control" placeholder="Name, phone or email" aria-label="Search customers"></div>
        <select name="sort" class="form-select" style="width:auto" onchange="this.form.submit()" aria-label="Sort">
            <option value="">Sort: Name</option>
            <option value="spent" <?= selected(input('sort'), 'spent') ?>>Top spenders</option>
            <option value="points" <?= selected(input('sort'), 'points') ?>>Most points</option>
            <option value="recent" <?= selected(input('sort'), 'recent') ?>>Recently active</option>
        </select>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_role('admin', 'manager')): ?><a href="<?= url('customers/export') ?>" class="btn btn-light"><i class="bi bi-download me-1"></i>CSV</a><?php endif; ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal"><i class="bi bi-person-plus me-1"></i>Add customer</button>
    </div>
</form>
<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Customer</th><th>Contact</th><th class="text-center">Orders</th><th class="text-end">Total spent</th><th class="text-center">Points</th><th>Last visit</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
                <tr>
                    <td><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(initials($c['name'])) ?></span><a href="<?= url('customers/view', ['id' => $c['id']]) ?>" class="cell-title"><?= e($c['name']) ?></a></div></td>
                    <td><?= e($c['phone'] ?? '—') ?><div class="cell-sub"><?= e($c['email'] ?? '') ?></div></td>
                    <td class="text-center"><?= (int)$c['order_count'] ?></td>
                    <td class="text-end fw-semibold"><?= money($c['total_spent']) ?></td>
                    <td class="text-center"><span class="badge badge-soft-warning"><i class="bi bi-star-fill"></i> <?= (int)$c['loyalty_points'] ?></span></td>
                    <td class="small"><?= $c['last_order'] ? fmt_date($c['last_order']) : '—' ?></td>
                    <td>
                        <div class="actions">
                            <a href="<?= url('customers/view', ['id' => $c['id']]) ?>" class="btn-icon btn-sm" aria-label="View"><i class="bi bi-eye"></i></a>
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#customerModal" data-fill='<?= e(json_encode(array_intersect_key($c, array_flip(['id', 'name', 'phone', 'email', 'address', 'notes'])))) ?>' aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <?php if (has_role('admin', 'manager')): ?>
                                <form method="post" action="<?= url('customers/delete', ['id' => $c['id']]) ?>" data-confirm="Delete “<?= e($c['name']) ?>”? Their orders will be kept as walk-in." data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?><tr><td colspan="7"><div class="empty-state"><div class="empty-icon"><i class="bi bi-people"></i></div><p class="mt-2 mb-0">No customers found.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['p' => $p]); ?>
</div>
<?php include __DIR__ . '/_modal.php'; ?>
