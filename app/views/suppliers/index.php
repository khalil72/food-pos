<div class="toolbar">
    <div class="search-box"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Filter suppliers" data-table-filter="#supTable" aria-label="Filter suppliers"></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal"><i class="bi bi-plus-lg me-1"></i>Add supplier</button>
</div>
<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover" id="supTable">
            <thead><tr><th>Supplier</th><th>Contact</th><th class="text-center">Purchases</th><th class="text-end">Total purchased</th><th class="text-end">Balance due</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td><a href="<?= url('suppliers/view', ['id' => $s['id']]) ?>" class="cell-title"><?= e($s['name']) ?></a><div class="cell-sub"><?= e($s['address'] ?? '') ?></div></td>
                    <td><?= e($s['contact_person'] ?? '—') ?><div class="cell-sub"><?= e(implode(' · ', array_filter([$s['phone'], $s['email']]))) ?></div></td>
                    <td class="text-center"><?= (int)$s['purchase_count'] ?></td>
                    <td class="text-end"><?= money($s['purchased']) ?></td>
                    <td class="text-end fw-semibold <?= (float)$s['due'] > 0 ? 'text-danger' : '' ?>"><?= money($s['due']) ?></td>
                    <td>
                        <div class="actions">
                            <a href="<?= url('suppliers/view', ['id' => $s['id']]) ?>" class="btn-icon btn-sm" aria-label="View"><i class="bi bi-eye"></i></a>
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal" data-fill='<?= e(json_encode(array_intersect_key($s, array_flip(['id', 'name', 'contact_person', 'phone', 'email', 'address', 'notes'])))) ?>' aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= url('suppliers/delete', ['id' => $s['id']]) ?>" data-confirm="Delete supplier “<?= e($s['name']) ?>”? Purchase records will be kept." data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$suppliers): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="bi bi-building"></i></div><p class="mt-2 mb-0">No suppliers yet.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/_modal.php'; ?>
