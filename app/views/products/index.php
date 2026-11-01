<form method="get" class="toolbar">
    <input type="hidden" name="r" value="products">
    <div class="d-flex gap-2 flex-wrap">
        <div class="search-box"><i class="bi bi-search"></i><input type="search" name="q" value="<?= e(input('q', '')) ?>" class="form-control" placeholder="Search name, SKU, barcode" aria-label="Search products"></div>
        <select name="category" class="form-select" style="width:auto" onchange="this.form.submit()" aria-label="Category">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= selected(input('category'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="status" class="form-select" style="width:auto" onchange="this.form.submit()" aria-label="Status">
            <option value="">Any status</option>
            <option value="active" <?= selected(input('status'), 'active') ?>>Active</option>
            <option value="inactive" <?= selected(input('status'), 'inactive') ?>>Inactive</option>
            <option value="low" <?= selected(input('status'), 'low') ?>>Low stock</option>
        </select>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('products/export') ?>" class="btn btn-light"><i class="bi bi-download me-1"></i>CSV</a>
        <a href="<?= url('products/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add product</a>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Product</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Cost</th><th class="text-end">Margin</th><th class="text-center">Stock</th><th class="text-center">Available</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $pr):
                $cost = $pr['recipe_cost'] !== null ? (float)$pr['recipe_cost'] : (float)$pr['cost'];
                $margin = (float)$pr['price'] > 0 ? ((float)$pr['price'] - $cost) / (float)$pr['price'] * 100 : 0; ?>
                <tr class="<?= $pr['is_active'] ? '' : 'opacity-50' ?>">
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($pr['image']): ?><img src="<?= e(upload_url($pr['image'])) ?>" alt="" class="thumb"><?php else: ?><span class="thumb" style="background:<?= e($pr['category_color'] ?? '#E8572A') ?>"><i class="bi <?= e($pr['category_icon'] ?? 'bi-egg-fried') ?>"></i></span><?php endif; ?>
                            <div>
                                <a href="<?= url('products/edit', ['id' => $pr['id']]) ?>" class="cell-title"><?= e($pr['name']) ?></a>
                                <div class="cell-sub"><?= e($pr['sku'] ?: '—') ?><?= $pr['barcode'] ? ' · <i class="bi bi-upc"></i> ' . e($pr['barcode']) : '' ?><?= $pr['variant_count'] ? ' · ' . (int)$pr['variant_count'] . ' options' : '' ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php if ($pr['category_name']): ?><span class="badge badge-soft-secondary"><span class="color-dot me-1" style="background:<?= e($pr['category_color']) ?>;width:8px;height:8px"></span><?= e($pr['category_name']) ?></span><?php else: ?>—<?php endif; ?></td>
                    <td class="text-end fw-semibold"><?= money($pr['price']) ?></td>
                    <td class="text-end"><?= money($cost) ?><?php if ($pr['recipe_cost'] !== null): ?><div class="cell-sub">from recipe</div><?php endif; ?></td>
                    <td class="text-end"><span class="<?= $margin >= 50 ? 'trend-up' : ($margin < 25 ? 'trend-down' : '') ?>"><?= num($margin, 0) ?>%</span></td>
                    <td class="text-center">
                        <?php if ($pr['track_stock']): $low = (float)$pr['stock_qty'] <= (float)$pr['low_stock_level']; ?>
                            <span class="badge badge-soft-<?= (float)$pr['stock_qty'] <= 0 ? 'danger' : ($low ? 'warning' : 'success') ?>"><?= qty($pr['stock_qty']) ?></span>
                        <?php else: ?><span class="text-muted small">Not tracked</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <form method="post" action="<?= url('products/toggle', ['id' => $pr['id']]) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" <?= checked($pr['is_active']) ?> onchange="this.form.submit()" aria-label="Available on POS"></div>
                        </form>
                    </td>
                    <td>
                        <div class="actions">
                            <a href="<?= url('products/edit', ['id' => $pr['id']]) ?>" class="btn-icon btn-sm" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="<?= url('products/delete', ['id' => $pr['id']]) ?>" data-confirm="Delete “<?= e($pr['name']) ?>”? Products with sales history are archived instead." data-confirm-ok="Delete">
                                <?= csrf_field() ?><button class="btn-icon btn-sm danger" title="Delete" aria-label="Delete"><i class="bi bi-trash3"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$products): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="bi bi-box-seam"></i></div><p class="mt-2">No products found.</p><a href="<?= url('products/create') ?>" class="btn btn-primary btn-sm">Add your first product</a></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['p' => $p]); ?>
</div>
