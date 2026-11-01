<div class="pos-layout">
    <!-- Catalogue -->
    <section class="pos-catalog" aria-label="Products">
        <div class="pos-search-row">
            <div class="pos-search">
                <i class="bi bi-search"></i>
                <input type="search" id="posSearch" placeholder="Search products, SKU or scan barcode…" autocomplete="off" aria-label="Search products">
                <kbd>F2</kbd>
            </div>
            <button type="button" class="scan-indicator" data-action="camera-scan" title="Scan a barcode or QR code with the camera" aria-label="Scan with camera"><i class="bi bi-qr-code-scan"></i></button>
        </div>
        <div class="pos-cats" id="posCats" role="tablist" aria-label="Categories">
            <button class="pos-cat active" data-cat="0" role="tab" aria-selected="true"><span class="pos-cat-icon" style="--c:#1F1B17"><i class="bi bi-grid"></i></span><span>All items</span></button>
            <?php foreach ($categories as $c): ?>
                <button class="pos-cat" data-cat="<?= (int)$c['id'] ?>" role="tab" aria-selected="false"><span class="pos-cat-icon" style="--c:<?= e($c['color']) ?>"><i class="bi <?= e($c['icon']) ?>"></i></span><span><?= e($c['name']) ?></span></button>
            <?php endforeach; ?>
        </div>
        <div class="pos-grid" id="posGrid" aria-live="polite"></div>
    </section>

    <!-- Cart -->
    <aside class="pos-cart" id="posCart" aria-label="Current order">
        <div class="cart-head">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0 fw-bold">Current order</h2>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-light" data-action="hold" title="Hold order (F8)"><i class="bi bi-pause"></i> Hold</button>
                    <button class="btn btn-sm btn-soft-danger" data-action="clear" title="Clear cart"><i class="bi bi-trash3"></i></button>
                    <button class="btn btn-sm btn-light d-lg-none" data-action="close-cart" aria-label="Close cart"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="order-types" role="radiogroup" aria-label="Order type">
                <button data-type="dine_in" role="radio"><i class="bi bi-shop"></i> Dine-in</button>
                <button data-type="takeaway" role="radio"><i class="bi bi-bag"></i> Takeaway</button>
                <button data-type="delivery" role="radio"><i class="bi bi-scooter"></i> Delivery</button>
            </div>
            <div class="cart-meta">
                <div class="customer-picker" id="customerPicker">
                    <i class="bi bi-person"></i>
                    <input type="text" id="customerSearch" placeholder="Walk-in customer · search name/phone" autocomplete="off" aria-label="Customer">
                    <button class="btn-clear-customer" id="clearCustomer" hidden aria-label="Remove customer"><i class="bi bi-x-circle-fill"></i></button>
                    <button class="btn-add-customer" data-bs-toggle="modal" data-bs-target="#customerModal" title="New customer"><i class="bi bi-person-plus"></i></button>
                    <div class="customer-results" id="customerResults" hidden></div>
                </div>
                <div class="type-field" data-for="dine_in"><input type="text" id="tableNo" class="form-control form-control-sm" placeholder="Table no." maxlength="20" aria-label="Table number"></div>
                <div class="type-field" data-for="delivery">
                    <input type="text" id="deliveryAddress" class="form-control form-control-sm" placeholder="Delivery address" maxlength="255" aria-label="Delivery address">
                    <div class="input-group input-group-sm mt-1" style="max-width:220px"><span class="input-group-text">Delivery fee</span><input type="number" id="deliveryFee" class="form-control" min="0" step="0.01" aria-label="Delivery fee"></div>
                </div>
                <div class="loyalty-chip" id="loyaltyChip" hidden></div>
            </div>
        </div>

        <div class="cart-items" id="cartItems"></div>

        <div class="cart-foot">
            <div class="cart-adjust">
                <button class="chip-btn" data-bs-toggle="modal" data-bs-target="#discountModal"><i class="bi bi-percent"></i> <span id="discountLabel">Discount</span></button>
                <button class="chip-btn" data-bs-toggle="modal" data-bs-target="#noteModal"><i class="bi bi-chat-left-text"></i> <span id="noteLabel">Order note</span></button>
            </div>
            <div class="totals">
                <div><span>Subtotal</span><span id="tSubtotal">0</span></div>
                <div class="t-discount" hidden><span>Discount</span><span id="tDiscount">0</span></div>
                <div class="t-loyalty" hidden><span>Loyalty points</span><span id="tLoyalty">0</span></div>
                <div class="t-tax"><span id="tTaxLabel">Tax</span><span id="tTax">0</span></div>
                <div class="t-delivery" hidden><span>Delivery fee</span><span id="tDelivery">0</span></div>
            </div>
            <button class="btn btn-primary btn-pay w-100" data-action="pay" disabled>
                <span>Charge</span><span id="tTotal">0</span><kbd class="ms-2">F9</kbd>
            </button>
        </div>
    </aside>
</div>

<button class="mobile-cart-btn d-lg-none" data-action="open-cart"><i class="bi bi-basket2"></i> View order <span class="pos-count" id="mobileCount">0</span><span id="mobileTotal" class="ms-auto">0</span></button>

<!-- Product options modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title" id="pmName">Product</h5><div class="small text-muted" id="pmDesc"></div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="pmVariants" class="mb-3"></div>
                <div id="pmAddons" class="mb-3"></div>
                <label class="form-label" for="pmNote">Item note</label>
                <input type="text" class="form-control" id="pmNote" maxlength="255" placeholder="e.g. no onions, extra spicy">
            </div>
            <div class="modal-footer justify-content-between">
                <div class="qty-control lg">
                    <button type="button" data-pm-qty="-1" aria-label="Decrease"><i class="bi bi-dash"></i></button>
                    <span id="pmQty">1</span>
                    <button type="button" data-pm-qty="1" aria-label="Increase"><i class="bi bi-plus"></i></button>
                </div>
                <button type="button" class="btn btn-primary px-4" id="pmAdd">Add · <span id="pmTotal">0</span></button>
            </div>
        </div>
    </div>
</div>

<!-- Payment modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content pay-modal">
            <div class="modal-header">
                <h5 class="modal-title">Take payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="pay-total">
                            <small>Amount due</small>
                            <div id="payDue">0</div>
                        </div>
                        <div class="pay-summary mt-3" id="paySummary"></div>
                        <div class="loyalty-redeem mt-3" id="redeemBox" hidden>
                            <label class="form-label" for="redeemPoints">Redeem loyalty points</label>
                            <div class="input-group">
                                <input type="number" min="0" step="1" class="form-control" id="redeemPoints" value="0">
                                <button class="btn btn-light border" type="button" id="redeemMax">Max</button>
                            </div>
                            <div class="form-text" id="redeemHint"></div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="pay-methods" role="radiogroup" aria-label="Payment method">
                            <button data-method="cash" role="radio"><i class="bi bi-cash-stack"></i>Cash</button>
                            <button data-method="card" role="radio"><i class="bi bi-credit-card-2-front"></i>Card</button>
                            <button data-method="online" role="radio"><i class="bi bi-phone"></i>Online</button>
                        </div>
                        <div id="cashPane" class="mt-3">
                            <label class="form-label" for="tendered">Cash received</label>
                            <input type="number" class="form-control form-control-lg fw-bold" id="tendered" min="0" step="0.01" inputmode="decimal">
                            <div class="quick-cash mt-2" id="quickCash"></div>
                            <div class="change-box mt-3"><span>Change due</span><strong id="changeDue">0</strong></div>
                        </div>
                        <div id="refPane" class="mt-3" hidden>
                            <label class="form-label" for="payRef">Transaction reference <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" class="form-control" id="payRef" maxlength="100" placeholder="Last 4 digits / transaction ID">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button>
                <button type="button" class="btn btn-primary px-4 btn-lg" id="completeSale"><i class="bi bi-check2-circle me-1"></i> Complete sale <kbd class="ms-1">Enter</kbd></button>
            </div>
        </div>
    </div>
</div>

<!-- Success modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-body p-4">
                <div class="success-check"><i class="bi bi-check-lg"></i></div>
                <h4 class="fw-bold mt-3 mb-1">Payment complete</h4>
                <p class="text-muted mb-3">Order <strong id="sOrderNo"></strong></p>
                <div class="row g-2 mb-3">
                    <div class="col"><div class="success-stat"><small>Total</small><strong id="sTotal"></strong></div></div>
                    <div class="col"><div class="success-stat"><small>Received</small><strong id="sTendered"></strong></div></div>
                    <div class="col"><div class="success-stat highlight"><small>Change</small><strong id="sChange"></strong></div></div>
                </div>
                <p class="small text-success mb-3" id="sPoints" hidden></p>
                <div class="d-flex flex-wrap gap-2 justify-content-center mb-3">
                    <button class="btn btn-dark" data-print="80"><i class="bi bi-printer me-1"></i> Receipt 80mm</button>
                    <button class="btn btn-light border" data-print="58">58mm</button>
                    <button class="btn btn-light border" data-print="a4">A4 Invoice</button>
                </div>
                <button class="btn btn-primary w-100 btn-lg" data-action="new-order">New order <kbd class="ms-1">Enter</kbd></button>
            </div>
        </div>
    </div>
</div>

<!-- Discount modal -->
<div class="modal fade" id="discountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Order discount</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="segmented w-100 mb-3" role="radiogroup">
                    <button type="button" class="flex-fill active" data-dtype="percent">Percent %</button>
                    <button type="button" class="flex-fill" data-dtype="fixed">Amount</button>
                </div>
                <input type="number" class="form-control form-control-lg" id="discountValue" min="0" step="0.01" placeholder="0" aria-label="Discount value">
                <div class="d-flex gap-2 mt-2 flex-wrap" id="discountPresets">
                    <?php foreach ([5, 10, 15, 20] as $d): ?><button type="button" class="btn btn-sm btn-light" data-dpreset="<?= $d ?>"><?= $d ?>%</button><?php endforeach; ?>
                </div>
                <?php if (has_role('cashier')): ?><div class="form-text mt-2">Cashier limit: <?= num(setting('cashier_max_discount', 20), 0) ?>% of subtotal.</div><?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" id="discountRemove">Remove</button>
                <button type="button" class="btn btn-primary" id="discountApply">Apply</button>
            </div>
        </div>
    </div>
</div>

<!-- Note modal -->
<div class="modal fade" id="noteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Order note</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <textarea class="form-control" id="orderNote" rows="3" maxlength="500" placeholder="Kitchen instructions, allergies, delivery notes…"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="noteSave">Save note</button></div>
        </div>
    </div>
</div>

<!-- New customer modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="customerForm">
            <div class="modal-header"><h5 class="modal-title">New customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label required" for="cName">Name</label><input class="form-control" id="cName" name="name" required maxlength="120"></div>
                    <div class="col-sm-6"><label class="form-label" for="cPhone">Phone</label><input class="form-control" id="cPhone" name="phone" maxlength="30" inputmode="tel"></div>
                    <div class="col-sm-6"><label class="form-label" for="cEmail">Email</label><input class="form-control" id="cEmail" name="email" type="email" maxlength="150"></div>
                    <div class="col-12"><label class="form-label" for="cAddress">Address</label><input class="form-control" id="cAddress" name="address" maxlength="255"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-no-lock>Save &amp; select</button></div>
        </form>
    </div>
</div>

<!-- Camera scanner modal -->
<div class="modal fade" id="scanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title"><i class="bi bi-qr-code-scan me-2"></i>Scan barcode / QR code</h5><div class="small text-muted">Hold the code inside the frame. Items are added automatically.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="scan-stage">
                    <div id="qrReader"></div>
                    <div class="scan-status" id="scanStatus"><span class="spinner-border spinner-border-sm me-2"></span>Starting camera…</div>
                </div>
                <div class="d-flex gap-2 mt-3" id="cameraPickWrap" hidden>
                    <select class="form-select form-select-sm" id="cameraPick" aria-label="Camera"></select>
                </div>
                <div class="scan-last mt-3" id="scanLast" hidden></div>
                <label class="form-label mt-3" for="manualCode">Or type the code / SKU</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-upc"></i></span>
                    <input type="text" class="form-control" id="manualCode" placeholder="e.g. 5449000000996 or BUR-001" autocomplete="off">
                    <button class="btn btn-primary" type="button" id="manualAdd">Add</button>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Done</button></div>
        </div>
    </div>
</div>

<!-- Held orders modal -->
<div class="modal fade" id="heldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Held orders</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body p-0" id="heldList"></div>
        </div>
    </div>
</div>

<!-- Recent orders modal -->
<div class="modal fade" id="recentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Today’s orders</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body p-0" id="recentList"></div>
        </div>
    </div>
</div>

<!-- Open register modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" method="post" action="<?= url('register/open') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="return" value="pos">
            <div class="modal-body p-4 text-center">
                <div class="empty-icon mb-3"><i class="bi bi-cash-coin"></i></div>
                <h5 class="fw-bold">Open the cash register</h5>
                <p class="text-muted small">Count the cash in the drawer to start your shift.</p>
                <label class="form-label visually-hidden" for="openingCash">Opening cash</label>
                <input type="number" class="form-control form-control-lg text-center fw-bold" id="openingCash" name="opening_cash" min="0" step="0.01" value="0" required>
                <button class="btn btn-primary w-100 mt-3">Open register &amp; start selling</button>
                <a href="<?= url(has_role('cashier') ? 'orders' : 'dashboard') ?>" class="btn btn-link btn-sm mt-2 text-muted">Not now</a>
            </div>
        </form>
    </div>
</div>

<iframe id="printFrame" title="Receipt print frame" style="position:absolute;width:0;height:0;border:0;left:-9999px"></iframe>

<script>window.POS = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
