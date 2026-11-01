<div class="toolbar">
    <div class="search-box"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Filter add-ons" data-table-filter="#addonTable" aria-label="Filter add-ons"></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addonModal"><i class="bi bi-plus-lg me-1"></i>Add add-on</button>
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover" id="addonTable">
            <thead><tr><th>Add-on</th><th class="text-end">Price</th><th class="text-end">Cost</th><th class="text-center">Used by</th><th class="text-center">Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($addons as $a): ?>
                <tr>
                    <td class="cell-title"><?= e($a['name']) ?></td>
                    <td class="text-end fw-semibold">+ <?= money($a['price']) ?></td>
                    <td class="text-end"><?= money($a['cost']) ?></td>
                    <td class="text-center"><?= (int)$a['product_count'] ?> products</td>
                    <td class="text-center"><?= $a['is_active'] ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Inactive</span>' ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#addonModal" data-fill='<?= e(json_encode(['id' => $a['id'], 'name' => $a['name'], 'price' => $a['price'], 'cost' => $a['cost'], 'is_active' => $a['is_active']])) ?>' aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= url('addons/delete', ['id' => $a['id']]) ?>" data-confirm="Delete add-on “<?= e($a['name']) ?>”?" data-confirm-ok="Delete">
                                <?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$addons): ?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="bi bi-plus-square"></i></div><p class="mt-2 mb-0">No add-ons yet. Add extras like cheese, sauces or toppings.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('addons/save') ?>" data-fill-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0" data-reset="0">
            <div class="modal-header"><h5 class="modal-title" data-modal-title data-create="Add add-on" data-edit="Edit add-on">Add add-on</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label required" for="aName">Name</label><input class="form-control" id="aName" name="name" maxlength="80" required placeholder="e.g. Extra cheese"></div>
                    <div class="col-6"><label class="form-label required" for="aPrice">Price</label><input type="number" class="form-control" id="aPrice" name="price" min="0" step="0.01" required></div>
                    <div class="col-6"><label class="form-label" for="aCost">Cost</label><input type="number" class="form-control" id="aCost" name="cost" min="0" step="0.01" value="0"></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="aActive" checked><label class="form-check-label" for="aActive">Active</label></div></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="attach_all" value="1" id="aAll"><label class="form-check-label" for="aAll">Make available on all products (new add-ons only)</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save add-on</button></div>
        </form>
    </div>
</div>
