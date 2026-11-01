<?php
$v = fn(string $k, $d = '') => old($k, $s[$k] ?? $d);
$on = fn(string $k, string $d = '0') => ($s[$k] ?? $d) === '1' ? 'checked' : '';
$tab = in_array($t = (string)input('tab', 'restaurant'), ['restaurant', 'tax', 'receipt', 'pos', 'loyalty'], true) ? $t : 'restaurant';
$tabs = ['restaurant' => ['Restaurant', 'bi-shop'], 'tax' => ['Tax & currency', 'bi-percent'], 'receipt' => ['Receipts', 'bi-printer'], 'pos' => ['POS & orders', 'bi-cart3'], 'loyalty' => ['Loyalty', 'bi-star']];
?>
<form method="post" action="<?= url('settings/save') ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="tab" id="tabInput" value="<?= $tab ?>">
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card p-2">
                <div class="nav flex-column nav-pills gap-1" role="tablist">
                    <?php foreach ($tabs as $k => [$label, $icon]): ?>
                        <button type="button" class="nav-link-item border-0 bg-transparent text-start w-100 <?= $tab === $k ? 'active' : '' ?>" style="<?= $tab === $k ? '' : 'color:var(--ink-2)' ?>" data-bs-toggle="pill" data-bs-target="#tab-<?= $k ?>" data-tab="<?= $k ?>" role="tab"><i class="bi <?= $icon ?>"></i><span><?= $label ?></span></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="tab-content">
                <div class="tab-pane fade <?= $tab === 'restaurant' ? 'show active' : '' ?>" id="tab-restaurant" role="tabpanel">
                    <div class="card"><div class="card-header"><h2>Restaurant profile</h2><p class="card-sub w-100">Shown on the sidebar, login page, receipts and invoices.</p></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-12 d-flex gap-3 align-items-center">
                                <div class="img-preview" id="logoPreview" style="width:96px;height:96px"><?php if (!empty($s['logo'])): ?><img src="<?= e(upload_url($s['logo'])) ?>" alt="Logo"><?php else: ?><i class="bi bi-image fs-3"></i><?php endif; ?></div>
                                <div>
                                    <label class="form-label" for="logo">Logo</label>
                                    <input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/png,image/jpeg,image/webp" data-preview="#logoPreview">
                                    <div class="form-text">Square PNG works best. Max 2 MB.</div>
                                    <?php if (!empty($s['logo'])): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rmLogo"><label class="form-check-label small" for="rmLogo">Remove logo</label></div><?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6"><label class="form-label required" for="rn">Restaurant name</label><input class="form-control" id="rn" name="restaurant_name" value="<?= e($v('restaurant_name')) ?>" maxlength="100" required></div>
                            <div class="col-md-6"><label class="form-label" for="rp">Phone</label><input class="form-control" id="rp" name="restaurant_phone" value="<?= e($v('restaurant_phone')) ?>" maxlength="50"></div>
                            <div class="col-md-6"><label class="form-label" for="re">Email</label><input type="email" class="form-control" id="re" name="restaurant_email" value="<?= e($v('restaurant_email')) ?>" maxlength="150"></div>
                            <div class="col-md-6"><label class="form-label" for="rw">Website</label><input class="form-control" id="rw" name="restaurant_website" value="<?= e($v('restaurant_website')) ?>" maxlength="150"></div>
                            <div class="col-12"><label class="form-label" for="ra">Address</label><textarea class="form-control" id="ra" name="restaurant_address" rows="2" maxlength="300"><?= e($v('restaurant_address')) ?></textarea></div>
                        </div></div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $tab === 'tax' ? 'show active' : '' ?>" id="tab-tax" role="tabpanel">
                    <div class="card mb-3"><div class="card-header"><h2>Tax</h2></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-md-4"><label class="form-label required" for="tn">Tax name</label><input class="form-control" id="tn" name="tax_name" value="<?= e($v('tax_name', 'GST')) ?>" maxlength="30"></div>
                            <div class="col-md-4"><label class="form-label required" for="tr">Tax rate (%)</label><input type="number" class="form-control" id="tr" name="tax_rate" value="<?= e($v('tax_rate', '0')) ?>" min="0" max="100" step="0.01"></div>
                            <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="tax_inclusive" value="1" id="ti" <?= $on('tax_inclusive') ?>><label class="form-check-label" for="ti">Prices include tax</label></div></div>
                            <div class="col-md-4"><label class="form-label" for="tnl">Tax number label</label><input class="form-control" id="tnl" name="tax_number_label" value="<?= e($v('tax_number_label', 'NTN')) ?>" maxlength="30" placeholder="NTN / VAT No / GSTIN"></div>
                            <div class="col-md-8"><label class="form-label" for="tnum">Tax registration number</label><input class="form-control" id="tnum" name="tax_number" value="<?= e($v('tax_number')) ?>" maxlength="60"></div>
                        </div></div>
                    </div>
                    <div class="card"><div class="card-header"><h2>Currency, time &amp; dates</h2></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-md-3"><label class="form-label required" for="cs">Symbol</label><input class="form-control" id="cs" name="currency_symbol" value="<?= e($v('currency_symbol', 'Rs')) ?>" maxlength="8"></div>
                            <div class="col-md-3"><label class="form-label" for="cc">Code</label><input class="form-control" id="cc" name="currency_code" value="<?= e($v('currency_code', 'PKR')) ?>" maxlength="5"></div>
                            <div class="col-md-3"><label class="form-label" for="cp">Position</label><select class="form-select" id="cp" name="currency_position"><option value="before" <?= selected($v('currency_position'), 'before') ?>>Before (Rs 100)</option><option value="after" <?= selected($v('currency_position'), 'after') ?>>After (100 Rs)</option></select></div>
                            <div class="col-md-3"><label class="form-label" for="cd">Decimals</label><select class="form-select" id="cd" name="currency_decimals"><?php foreach ([0, 1, 2, 3] as $d): ?><option value="<?= $d ?>" <?= selected($v('currency_decimals', '2'), $d) ?>><?= $d ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label" for="tz">Timezone</label><select class="form-select" id="tz" name="timezone"><?php foreach ($timezones as $z): ?><option value="<?= e($z) ?>" <?= selected($v('timezone', 'Asia/Karachi'), $z) ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label" for="df">Date format</label><select class="form-select" id="df" name="date_format"><?php foreach (['d M Y', 'd/m/Y', 'm/d/Y', 'Y-m-d', 'M d Y'] as $f): ?><option value="<?= $f ?>" <?= selected($v('date_format', 'd M Y'), $f) ?>><?= date($f) ?></option><?php endforeach; ?></select></div>
                        </div></div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $tab === 'receipt' ? 'show active' : '' ?>" id="tab-receipt" role="tabpanel">
                    <div class="card"><div class="card-header"><h2>Receipts &amp; invoices</h2></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="rs">Default receipt size</label><select class="form-select" id="rs" name="receipt_size"><option value="80" <?= selected($v('receipt_size', '80'), '80') ?>>80mm thermal</option><option value="58" <?= selected($v('receipt_size'), '58') ?>>58mm thermal</option><option value="a4" <?= selected($v('receipt_size'), 'a4') ?>>A4 invoice</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="op">Order number prefix</label><input class="form-control" id="op" name="order_prefix" value="<?= e($v('order_prefix', 'ORD-')) ?>" maxlength="10"><div class="form-text">Example: <?= e(($s['order_prefix'] ?? 'ORD-') . '000123') ?></div></div>
                            <div class="col-12"><label class="form-label" for="rh">Receipt header</label><textarea class="form-control" id="rh" name="receipt_header" rows="2" maxlength="300" placeholder="e.g. Open daily 11am – 2am"><?= e($v('receipt_header')) ?></textarea></div>
                            <div class="col-12"><label class="form-label" for="rf">Receipt footer</label><textarea class="form-control" id="rf" name="receipt_footer" rows="2" maxlength="300"><?= e($v('receipt_footer', 'Thank you for dining with us!')) ?></textarea></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="receipt_show_logo" value="1" id="rsl" <?= $on('receipt_show_logo', '1') ?>><label class="form-check-label" for="rsl">Show logo on receipts</label></div></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="auto_print" value="1" id="ap" <?= $on('auto_print') ?>><label class="form-check-label" for="ap">Print receipt automatically after each sale</label></div></div>
                        </div></div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $tab === 'pos' ? 'show active' : '' ?>" id="tab-pos" role="tabpanel">
                    <div class="card"><div class="card-header"><h2>POS &amp; orders</h2></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="dot">Default order type</label><select class="form-select" id="dot" name="default_order_type"><?php foreach (['takeaway', 'dine_in', 'delivery'] as $t): ?><option value="<?= $t ?>" <?= selected($v('default_order_type', 'takeaway'), $t) ?>><?= order_type_label($t) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label class="form-label" for="dfee">Default delivery fee</label><input type="number" class="form-control" id="dfee" name="delivery_fee" value="<?= e($v('delivery_fee', '0')) ?>" min="0" step="0.01"></div>
                            <div class="col-md-6"><label class="form-label" for="cmd">Cashier discount limit (%)</label><input type="number" class="form-control" id="cmd" name="cashier_max_discount" value="<?= e($v('cashier_max_discount', '20')) ?>" min="0" max="100" step="0.5"><div class="form-text">Managers and admins have no limit.</div></div>
                            <div class="col-12"><hr class="my-1"></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="require_register" value="1" id="rr" <?= $on('require_register', '1') ?>><label class="form-check-label" for="rr">Require an open cash register to sell</label></div></div>
                            <div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_negative_stock" value="1" id="ans" <?= $on('allow_negative_stock') ?>><label class="form-check-label" for="ans">Allow selling tracked items with no stock</label></div></div>
                        </div></div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $tab === 'loyalty' ? 'show active' : '' ?>" id="tab-loyalty" role="tabpanel">
                    <div class="card"><div class="card-header"><h2>Loyalty programme</h2></div>
                        <div class="card-body"><div class="row g-3">
                            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="loyalty_enabled" value="1" id="le" <?= $on('loyalty_enabled', '1') ?>><label class="form-check-label" for="le">Enable loyalty points</label></div></div>
                            <div class="col-md-4"><label class="form-label" for="lea">Spend per 1 point earned</label><input type="number" class="form-control" id="lea" name="loyalty_earn_amount" value="<?= e($v('loyalty_earn_amount', '100')) ?>" min="1" step="1"></div>
                            <div class="col-md-4"><label class="form-label" for="lpv">Value of 1 point</label><input type="number" class="form-control" id="lpv" name="loyalty_point_value" value="<?= e($v('loyalty_point_value', '1')) ?>" min="0.01" step="0.01"></div>
                            <div class="col-md-4"><label class="form-label" for="lmr">Minimum points to redeem</label><input type="number" class="form-control" id="lmr" name="loyalty_min_redeem" value="<?= e($v('loyalty_min_redeem', '0')) ?>" min="0" step="1"></div>
                            <div class="col-12"><div class="alert alert-light border small mb-0" id="loyaltyExample"></div></div>
                        </div></div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary btn-lg px-4" type="submit"><i class="bi bi-check2 me-1"></i>Save settings</button></div>
        </div>
    </div>
</form>
<script>
document.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('shown.bs.tab', () => {
    document.getElementById('tabInput').value = b.dataset.tab;
    document.querySelectorAll('[data-tab]').forEach(x => { x.classList.toggle('active', x === b); x.style.color = x === b ? '' : 'var(--ink-2)'; });
    history.replaceState(null, '', '?r=settings&tab=' + b.dataset.tab);
}));
function loyaltyExample() {
    const earn = Number(document.getElementById('lea').value) || 1, val = Number(document.getElementById('lpv').value) || 0;
    const pts = Math.floor(2000 / earn);
    document.getElementById('loyaltyExample').innerHTML = `<i class="bi bi-lightbulb me-1"></i>A customer spending <strong>${APP.money(2000)}</strong> earns <strong>${pts} points</strong>, worth <strong>${APP.money(pts * val)}</strong> off a future order (${(earn > 0 ? val / earn * 100 : 0).toFixed(1)}% back).`;
}
['lea', 'lpv'].forEach(id => document.getElementById(id).addEventListener('input', loyaltyExample));
loyaltyExample();
</script>
