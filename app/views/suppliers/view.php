<?php $s = $supplier; $total = array_sum(array_column($purchases, 'total')); $paid = array_sum(array_column($purchases, 'paid')); ?>
<div class="toolbar">
    <a href="<?= url('suppliers') ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All suppliers</a>
    <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal" data-fill='<?= e(json_encode(array_intersect_key($s, array_flip(['id', 'name', 'contact_person', 'phone', 'email', 'address', 'notes'])))) ?>'><i class="bi bi-pencil me-1"></i>Edit</button>
        <a href="<?= url('purchases/create', ['supplier' => $s['id']]) ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New purchase</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3"><span class="avatar avatar-lg"><?= e(initials($s['name'])) ?></span><div><h2 class="h5 mb-0"><?= e($s['name']) ?></h2><div class="cell-sub"><?= e($s['contact_person'] ?? '') ?></div></div></div>
                <?php foreach ([['bi-telephone', $s['phone']], ['bi-envelope', $s['email']], ['bi-geo-alt', $s['address']]] as [$i, $v]): if ($v): ?>
                    <div class="mb-2"><i class="bi <?= $i ?> text-muted me-2"></i><?= e($v) ?></div>
                <?php endif; endforeach; ?>
                <?php if ($s['notes']): ?><div class="small text-muted mt-2"><?= nl2br(e($s['notes'])) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="card mb-3"><div class="card-body">
            <div class="stat-line"><span>Total purchased</span><strong><?= money($total) ?></strong></div>
            <div class="stat-line"><span>Paid</span><strong><?= money($paid) ?></strong></div>
            <div class="stat-line"><span>Balance due</span><strong class="<?= $total - $paid > 0 ? 'text-danger' : '' ?>"><?= money($total - $paid) ?></strong></div>
        </div></div>
        <?php if ($ingredients): ?>
        <div class="card"><div class="card-header"><h2>Supplies</h2></div>
            <?php foreach ($ingredients as $i): ?><div class="list-row"><span class="flex-grow-1"><?= e($i['name']) ?></span><span class="small text-muted"><?= qty($i['stock_qty']) ?> <?= e($i['unit']) ?></span></div><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><h2>Purchase history</h2></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Date</th><th>Reference</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($purchases as $pu): $st = (float)$pu['paid'] >= (float)$pu['total'] ? 'paid' : ((float)$pu['paid'] > 0 ? 'partial' : 'unpaid'); ?>
                        <tr>
                            <td><?= fmt_date($pu['purchase_date']) ?></td>
                            <td><?= e($pu['reference'] ?: 'PUR-' . $pu['id']) ?></td>
                            <td class="text-end"><?= money($pu['total']) ?></td>
                            <td class="text-end"><?= money($pu['paid']) ?></td>
                            <td><?= status_badge($st) ?></td>
                            <td class="text-end"><a href="<?= url('purchases/view', ['id' => $pu['id']]) ?>" class="btn-icon btn-sm" aria-label="View"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$purchases): ?><tr><td colspan="6"><div class="empty-state">No purchases from this supplier yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/_modal.php'; ?>
