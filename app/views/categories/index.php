<div class="toolbar">
    <div class="search-box"><i class="bi bi-search"></i><input type="search" class="form-control" placeholder="Filter categories" data-table-filter="#catTable" aria-label="Filter categories"></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal"><i class="bi bi-plus-lg me-1"></i>Add category</button>
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover" id="catTable">
            <thead><tr><th>Category</th><th class="text-center">Products</th><th class="text-center">Sort</th><th class="text-center">Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><div class="d-flex align-items-center gap-3"><span class="thumb" style="background:<?= e($c['color']) ?>"><i class="bi <?= e($c['icon']) ?>"></i></span><span class="cell-title"><?= e($c['name']) ?></span></div></td>
                    <td class="text-center"><a href="<?= url('products', ['category' => $c['id']]) ?>"><?= (int)$c['product_count'] ?></a></td>
                    <td class="text-center"><?= (int)$c['sort_order'] ?></td>
                    <td class="text-center"><?= $c['is_active'] ? '<span class="badge badge-soft-success">Visible</span>' : '<span class="badge badge-soft-secondary">Hidden</span>' ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#catModal" data-fill='<?= e(json_encode(['id' => $c['id'], 'name' => $c['name'], 'color' => $c['color'], 'icon' => $c['icon'], 'sort_order' => $c['sort_order'], 'is_active' => $c['is_active']])) ?>' aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <form method="post" action="<?= url('categories/delete', ['id' => $c['id']]) ?>" data-confirm="Delete category “<?= e($c['name']) ?>”? Its <?= (int)$c['product_count'] ?> product(s) will become uncategorised." data-confirm-ok="Delete">
                                <?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$categories): ?><tr><td colspan="5"><div class="empty-state"><div class="empty-icon"><i class="bi bi-tags"></i></div><p class="mt-2 mb-0">No categories yet.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('categories/save') ?>" data-fill-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0" data-reset="0">
            <div class="modal-header"><h5 class="modal-title" data-modal-title data-create="Add category" data-edit="Edit category">Add category</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-8"><label class="form-label required" for="catName">Name</label><input class="form-control" id="catName" name="name" maxlength="80" required></div>
                    <div class="col-4"><label class="form-label" for="catColor">Colour</label><input type="color" class="form-control form-control-color w-100" id="catColor" name="color" value="#E8572A"></div>
                    <div class="col-12">
                        <label class="form-label">Icon</label>
                        <div class="icon-pick">
                            <?php foreach ($icons as $i => $icon): ?>
                                <input type="radio" name="icon" value="<?= $icon ?>" id="ic<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>><label for="ic<?= $i ?>" title="<?= e($icon) ?>"><i class="bi <?= $icon ?>"></i></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-6"><label class="form-label" for="catSort">Sort order</label><input type="number" class="form-control" id="catSort" name="sort_order" value="0" min="0"></div>
                    <div class="col-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="catActive" checked><label class="form-check-label" for="catActive">Show on POS</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save category</button></div>
        </form>
    </div>
</div>
