<?php
$pv = fn(string $k, $d = '') => old($k, $product[$k] ?? $d);
$variantRows = old('variants', $variants) ?: [];
$recipeRows = old('recipe', $recipe) ?: [];
$checkedAddons = array_map('intval', (array)old('addons', $selectedAddons));
$isNew = !$product;
?>
<form method="post" action="<?= url('products/save') ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)($product['id'] ?? 0) ?>">

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><h2>Basic information</h2></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label required" for="name">Product name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($pv('name')) ?>" maxlength="120" required autofocus>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="category_id">Category</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">— None —</option>
                                <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= selected($pv('category_id'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required" for="price">Selling price</label>
                            <div class="input-group"><span class="input-group-text"><?= e(setting('currency_symbol', 'Rs')) ?></span><input type="number" class="form-control" id="price" name="price" value="<?= e($pv('price')) ?>" min="0" step="0.01" required></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cost">Cost price</label>
                            <div class="input-group"><span class="input-group-text"><?= e(setting('currency_symbol', 'Rs')) ?></span><input type="number" class="form-control" id="cost" name="cost" value="<?= e($pv('cost', 0)) ?>" min="0" step="0.01"></div>
                            <div class="form-text">Used for profit when no recipe is set.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Margin</label>
                            <div class="form-control bg-light" id="marginOut">—</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sku">SKU / code</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?= e($pv('sku')) ?>" maxlength="50" placeholder="e.g. BRG-001">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="barcode">Barcode</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" class="form-control" id="barcode" name="barcode" value="<?= e($pv('barcode')) ?>" maxlength="64" placeholder="Scan or type">
                                <button type="button" class="btn btn-light border" onclick="document.getElementById('barcode').value='2'+String(Date.now()).slice(-11)" title="Generate">Generate</button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2" maxlength="500" placeholder="Short description shown on the POS options dialog"><?= e($pv('description')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <div><h2>Sizes &amp; options</h2><p class="card-sub">Optional. Each option has its own price (e.g. Small / Regular / Large).</p></div>
                    <button type="button" class="btn btn-sm btn-light" data-add-row="variant"><i class="bi bi-plus-lg me-1"></i>Add option</button>
                </div>
                <div class="card-body">
                    <div id="variantRows">
                        <?php foreach ($variantRows as $i => $v): ?>
                            <div class="row g-2 mb-2 align-items-center" data-row>
                                <input type="hidden" name="variants[<?= $i ?>][id]" value="<?= (int)($v['id'] ?? 0) ?>">
                                <div class="col-5"><input type="text" class="form-control" name="variants[<?= $i ?>][name]" value="<?= e($v['name'] ?? '') ?>" placeholder="Option name" maxlength="60" aria-label="Option name"></div>
                                <div class="col-3"><input type="number" class="form-control" name="variants[<?= $i ?>][price]" value="<?= e($v['price'] ?? '') ?>" placeholder="Price" min="0" step="0.01" aria-label="Option price"></div>
                                <div class="col-3"><input type="number" class="form-control" name="variants[<?= $i ?>][cost]" value="<?= e($v['cost'] ?? '') ?>" placeholder="Cost" min="0" step="0.01" aria-label="Option cost"></div>
                                <div class="col-1 text-end"><button type="button" class="btn-icon btn-sm danger" data-remove-row aria-label="Remove option"><i class="bi bi-x-lg"></i></button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small mb-0" data-empty="variantRows" <?= $variantRows ? 'hidden' : '' ?>>No options — the product sells at its base price.</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <div><h2>Recipe / ingredients</h2><p class="card-sub">Ingredients deducted from stock for each unit sold. Recipe cost overrides the cost price.</p></div>
                    <button type="button" class="btn btn-sm btn-light" data-add-row="recipe" <?= $ingredients ? '' : 'disabled' ?>><i class="bi bi-plus-lg me-1"></i>Add ingredient</button>
                </div>
                <div class="card-body">
                    <div id="recipeRows">
                        <?php foreach ($recipeRows as $i => $r): ?>
                            <div class="row g-2 mb-2 align-items-center" data-row>
                                <div class="col-7">
                                    <select class="form-select" name="recipe[<?= $i ?>][ingredient_id]" aria-label="Ingredient">
                                        <?php foreach ($ingredients as $ing): ?><option value="<?= $ing['id'] ?>" data-cost="<?= e($ing['cost_per_unit']) ?>" data-unit="<?= e($ing['unit']) ?>" <?= selected($r['ingredient_id'] ?? '', $ing['id']) ?>><?= e($ing['name']) ?> (<?= e($ing['unit']) ?>)</option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-4"><input type="number" class="form-control" name="recipe[<?= $i ?>][qty]" value="<?= e($r['qty'] ?? '') ?>" placeholder="Qty per unit" min="0" step="0.0001" aria-label="Quantity"></div>
                                <div class="col-1 text-end"><button type="button" class="btn-icon btn-sm danger" data-remove-row aria-label="Remove ingredient"><i class="bi bi-x-lg"></i></button></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small mb-0" data-empty="recipeRows" <?= $recipeRows ? 'hidden' : '' ?>><?= $ingredients ? 'No recipe set.' : 'Add ingredients under Stock & Ingredients first.' ?></p>
                    <div class="small mt-2 fw-semibold" id="recipeCost"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2>Image</h2></div>
                <div class="card-body d-flex gap-3 align-items-center">
                    <div class="img-preview" id="imgPreview">
                        <?php if (!empty($product['image'])): ?><img src="<?= e(upload_url($product['image'])) ?>" alt=""><?php else: ?><i class="bi bi-image fs-2"></i><?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <input type="file" class="form-control form-control-sm" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-preview="#imgPreview" aria-label="Product image">
                        <div class="form-text">JPG, PNG or WEBP up to 2 MB. Square images look best.</div>
                        <?php if (!empty($product['image'])): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImage"><label class="form-check-label small" for="removeImage">Remove image</label></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2>Inventory</h2></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="track_stock" value="1" id="trackStock" <?= checked($pv('track_stock', 0)) ?>>
                        <label class="form-check-label" for="trackStock">Track stock for this product</label>
                        <div class="form-text">For ready-made items such as canned drinks. Cooked items usually use a recipe instead.</div>
                    </div>
                    <div class="row g-2" id="stockFields">
                        <div class="col-6"><label class="form-label" for="stock_qty"><?= $isNew ? 'Opening stock' : 'Stock on hand' ?></label><input type="number" class="form-control" id="stock_qty" name="stock_qty" value="<?= e(qty($pv('stock_qty', 0))) ?>" step="1"></div>
                        <div class="col-6"><label class="form-label" for="low_stock_level">Alert at</label><input type="number" class="form-control" id="low_stock_level" name="low_stock_level" value="<?= e(qty($pv('low_stock_level', 5))) ?>" min="0" step="1"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2>Add-ons</h2><a href="<?= url('addons') ?>" class="small">Manage</a></div>
                <div class="card-body" style="max-height:260px;overflow:auto">
                    <?php foreach ($addons as $a): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="addons[]" value="<?= $a['id'] ?>" id="addon<?= $a['id'] ?>" <?= in_array((int)$a['id'], $checkedAddons, true) ? 'checked' : '' ?>>
                            <label class="form-check-label d-flex justify-content-between" for="addon<?= $a['id'] ?>"><span><?= e($a['name']) ?><?= $a['is_active'] ? '' : ' <span class="text-muted">(inactive)</span>' ?></span><span class="text-muted small">+<?= money($a['price']) ?></span></label>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$addons): ?><p class="text-muted small mb-0">No add-ons yet.</p><?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= checked($pv('is_active', 1)) ?>>
                        <label class="form-check-label" for="isActive">Available for sale on POS</label>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-check2 me-1"></i>Save product</button>
                <?php if ($isNew): ?><button class="btn btn-light" type="submit" name="save_new" value="1">Save &amp; add another</button><?php endif; ?>
                <a href="<?= url('products') ?>" class="btn btn-link text-muted">Cancel</a>
            </div>
        </div>
    </div>
</form>

<template id="tpl-variant">
    <div class="row g-2 mb-2 align-items-center" data-row>
        <input type="hidden" name="variants[__i__][id]" value="0">
        <div class="col-5"><input type="text" class="form-control" name="variants[__i__][name]" placeholder="Option name" maxlength="60" aria-label="Option name"></div>
        <div class="col-3"><input type="number" class="form-control" name="variants[__i__][price]" placeholder="Price" min="0" step="0.01" aria-label="Option price"></div>
        <div class="col-3"><input type="number" class="form-control" name="variants[__i__][cost]" placeholder="Cost" min="0" step="0.01" aria-label="Option cost"></div>
        <div class="col-1 text-end"><button type="button" class="btn-icon btn-sm danger" data-remove-row aria-label="Remove option"><i class="bi bi-x-lg"></i></button></div>
    </div>
</template>
<template id="tpl-recipe">
    <div class="row g-2 mb-2 align-items-center" data-row>
        <div class="col-7"><select class="form-select" name="recipe[__i__][ingredient_id]" aria-label="Ingredient">
            <?php foreach ($ingredients as $ing): ?><option value="<?= $ing['id'] ?>" data-cost="<?= e($ing['cost_per_unit']) ?>" data-unit="<?= e($ing['unit']) ?>"><?= e($ing['name']) ?> (<?= e($ing['unit']) ?>)</option><?php endforeach; ?>
        </select></div>
        <div class="col-4"><input type="number" class="form-control" name="recipe[__i__][qty]" placeholder="Qty per unit" min="0" step="0.0001" aria-label="Quantity"></div>
        <div class="col-1 text-end"><button type="button" class="btn-icon btn-sm danger" data-remove-row aria-label="Remove ingredient"><i class="bi bi-x-lg"></i></button></div>
    </div>
</template>

<script>
(function () {
    let counter = 1000;
    const toggleEmpty = () => document.querySelectorAll('[data-empty]').forEach(p => { p.hidden = !!document.getElementById(p.dataset.empty).children.length; });
    document.addEventListener('click', (e) => {
        const add = e.target.closest('[data-add-row]');
        if (add) {
            const html = document.getElementById('tpl-' + add.dataset.addRow).innerHTML.replaceAll('__i__', counter++);
            const box = document.getElementById(add.dataset.addRow + 'Rows');
            box.insertAdjacentHTML('beforeend', html);
            box.lastElementChild.querySelector('input:not([type=hidden]),select').focus();
            toggleEmpty(); calc();
        }
        const rm = e.target.closest('[data-remove-row]');
        if (rm) { rm.closest('[data-row]').remove(); toggleEmpty(); calc(); }
    });
    const track = document.getElementById('trackStock');
    const syncStock = () => document.getElementById('stockFields').style.opacity = track.checked ? 1 : .45;
    track.addEventListener('change', syncStock); syncStock();

    function calc() {
        let recipe = 0, has = false;
        document.querySelectorAll('#recipeRows [data-row]').forEach(r => {
            const sel = r.querySelector('select'); const q = Number(r.querySelector('input').value) || 0;
            if (q > 0) { has = true; recipe += q * Number(sel.selectedOptions[0].dataset.cost || 0); }
        });
        document.getElementById('recipeCost').textContent = has ? 'Recipe cost per unit: ' + APP.money(recipe) : '';
        const price = Number(document.getElementById('price').value) || 0;
        const cost = has ? recipe : Number(document.getElementById('cost').value) || 0;
        document.getElementById('marginOut').textContent = price > 0 ? `${((price - cost) / price * 100).toFixed(1)}%  ·  ${APP.money(price - cost)} profit` : '—';
    }
    document.addEventListener('input', calc);
    document.addEventListener('change', calc);
    calc();
})();
</script>
