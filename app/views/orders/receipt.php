<?php
$s = settings_all();
$name = $s['restaurant_name'] ?? 'Saffron POS';
$logo = (($s['receipt_show_logo'] ?? '1') === '1') ? upload_url($s['logo'] ?? null) : null;
$taxName = $s['tax_name'] ?? 'Tax';
$isA4 = $size === 'a4';
$width = $size === '58' ? 58 : 80;
$o = $order;
$netPaid = (float)$o['total'] - (float)$o['refunded_amount'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isA4 ? 'Invoice' : 'Receipt' ?> <?= e($o['order_no']) ?></title>
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<style>
@font-face { font-family: 'Inter'; font-weight: 400; src: url('<?= base_url() ?>/assets/vendor/fonts/inter-400.woff2') format('woff2'); }
@font-face { font-family: 'Inter'; font-weight: 600; src: url('<?= base_url() ?>/assets/vendor/fonts/inter-600.woff2') format('woff2'); }
@font-face { font-family: 'Inter'; font-weight: 700; src: url('<?= base_url() ?>/assets/vendor/fonts/inter-700.woff2') format('woff2'); }
@font-face { font-family: 'Outfit'; font-weight: 700; src: url('<?= base_url() ?>/assets/vendor/fonts/outfit-700.woff2') format('woff2'); }
* { box-sizing: border-box; }
body { margin: 0; background: #EDE8E1; color: #111; font-family: 'Inter', Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap; padding: 10px; background: #1C1814; }
.toolbar a, .toolbar button { font: 600 13px Inter, Arial, sans-serif; color: #EDE6DE; background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; padding: 7px 12px; cursor: pointer; text-decoration: none; display: inline-flex; gap: 6px; align-items: center; }
.toolbar a.active { background: #E8572A; border-color: #E8572A; color: #fff; }
.toolbar .primary { background: #E8572A; border-color: #E8572A; color: #fff; }
.sheet-wrap { padding: 24px 12px 48px; display: flex; justify-content: center; }

/* Thermal receipt */
.receipt { width: <?= $width ?>mm; background: #fff; padding: <?= $width === 58 ? '3mm 2.5mm' : '4mm 4mm' ?>; font-size: <?= $width === 58 ? '10.5px' : '12px' ?>; line-height: 1.35; box-shadow: 0 10px 30px -10px rgba(0,0,0,.25); }
.receipt .center { text-align: center; }
.receipt .logo { max-width: <?= $width === 58 ? '28mm' : '36mm' ?>; max-height: 22mm; margin: 0 auto 2mm; display: block; filter: grayscale(1) contrast(1.2); }
.receipt .biz { font-family: 'Outfit', Arial, sans-serif; font-size: <?= $width === 58 ? '15px' : '18px' ?>; font-weight: 700; margin: 0 0 1mm; }
.receipt .muted { color: #333; }
.receipt .rule { border: 0; border-top: 1px dashed #000; margin: 2.2mm 0; }
.receipt .rule.solid { border-top-style: solid; }
.receipt .row { display: flex; justify-content: space-between; gap: 2mm; }
.receipt .row span:last-child { text-align: right; white-space: nowrap; }
.receipt .item { margin-bottom: 1.4mm; }
.receipt .item .name { font-weight: 600; }
.receipt .item .opt { font-size: .9em; color: #333; padding-left: 3mm; }
.receipt .total { font-size: 1.35em; font-weight: 700; }
.receipt .tag { display: inline-block; border: 1px solid #000; padding: .5mm 2mm; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin: 1mm 0; }
.receipt .big-no { font-size: 1.3em; font-weight: 700; letter-spacing: .03em; }

/* A4 invoice */
.invoice { width: 210mm; min-height: 297mm; background: #fff; padding: 16mm 16mm 14mm; font-size: 12.5px; box-shadow: 0 10px 30px -10px rgba(0,0,0,.25); position: relative; }
.invoice .band { position: absolute; inset: 0 0 auto 0; height: 6mm; background: linear-gradient(90deg, #F5A524, #E8572A); }
.inv-head { display: flex; justify-content: space-between; gap: 10mm; margin-top: 4mm; }
.inv-brand { display: flex; gap: 4mm; align-items: flex-start; }
.inv-brand img { width: 22mm; height: 22mm; object-fit: contain; border-radius: 3mm; }
.inv-brand h1 { font-family: 'Outfit', Arial, sans-serif; margin: 0 0 1.5mm; font-size: 22px; }
.inv-brand p, .inv-meta p { margin: 0; color: #555; line-height: 1.5; }
.inv-title { text-align: right; }
.inv-title h2 { font-family: 'Outfit', Arial, sans-serif; font-size: 30px; letter-spacing: .12em; margin: 0; color: #E8572A; }
.inv-title table { margin-left: auto; margin-top: 3mm; border-collapse: collapse; }
.inv-title td { padding: .8mm 0 .8mm 6mm; text-align: right; }
.inv-title td:first-child { color: #777; }
.inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 8mm; margin: 10mm 0 7mm; }
.inv-parties .label { font-size: 10px; text-transform: uppercase; letter-spacing: .1em; color: #999; font-weight: 700; margin-bottom: 1.5mm; }
.inv-parties .box { background: #FAF7F2; border-radius: 3mm; padding: 4mm 5mm; }
.inv-items { width: 100%; border-collapse: collapse; }
.inv-items th { background: #1C1814; color: #fff; text-align: left; padding: 3mm; font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em; }
.inv-items th:first-child { border-radius: 2mm 0 0 2mm; } .inv-items th:last-child { border-radius: 0 2mm 2mm 0; }
.inv-items td { padding: 3mm; border-bottom: 1px solid #EEE7DE; vertical-align: top; }
.inv-items .r { text-align: right; } .inv-items .c { text-align: center; }
.inv-items small { color: #777; display: block; }
.inv-bottom { display: flex; justify-content: space-between; gap: 10mm; margin-top: 6mm; }
.inv-totals { width: 75mm; }
.inv-totals .row { display: flex; justify-content: space-between; padding: 1.6mm 0; border-bottom: 1px dashed #E5DDD2; }
.inv-totals .grand { background: #E8572A; color: #fff; border-radius: 2.5mm; padding: 3mm 4mm; margin-top: 2mm; border: 0; font-size: 15px; font-weight: 700; }
.inv-note { flex: 1; color: #555; }
.inv-foot { position: absolute; left: 16mm; right: 16mm; bottom: 12mm; text-align: center; color: #888; font-size: 11px; border-top: 1px solid #EEE7DE; padding-top: 4mm; }
.stamp { display: inline-block; border: 2px solid #15803D; color: #15803D; border-radius: 2mm; padding: 1mm 3mm; font-weight: 700; letter-spacing: .1em; transform: rotate(-4deg); margin-top: 3mm; }
.stamp.refund { border-color: #C62828; color: #C62828; }

@media print {
    body { background: #fff; }
    .toolbar { display: none !important; }
    .sheet-wrap { padding: 0; display: block; }
    .receipt, .invoice { box-shadow: none; }
    .receipt { width: <?= $width ?>mm; }
    @page { margin: <?= $isA4 ? '0' : '0' ?>; <?= $isA4 ? 'size: A4;' : '' ?> }
}
</style>
</head>
<body>
<?php if (!$embed): ?>
<div class="toolbar">
    <a href="<?= e(query_with(['size' => '58', 'autoprint' => null])) ?>" class="<?= $size === '58' ? 'active' : '' ?>">58mm</a>
    <a href="<?= e(query_with(['size' => '80', 'autoprint' => null])) ?>" class="<?= $size === '80' ? 'active' : '' ?>">80mm</a>
    <a href="<?= e(query_with(['size' => 'a4', 'autoprint' => null])) ?>" class="<?= $isA4 ? 'active' : '' ?>">A4 Invoice</a>
    <button onclick="window.print()" class="primary"><i class="bi bi-printer"></i> Print</button>
    <button id="pdfBtn"><i class="bi bi-file-earmark-pdf"></i> Download PDF</button>
    <button onclick="window.close()"><i class="bi bi-x-lg"></i> Close</button>
</div>
<?php endif; ?>

<div class="sheet-wrap">
<?php if (!$isA4): ?>
    <div class="receipt" id="doc">
        <div class="center">
            <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" class="logo"><?php endif; ?>
            <div class="biz"><?= e($name) ?></div>
            <?php if (!empty($s['restaurant_address'])): ?><div class="muted"><?= nl2br(e($s['restaurant_address'])) ?></div><?php endif; ?>
            <?php if (!empty($s['restaurant_phone'])): ?><div class="muted">Tel: <?= e($s['restaurant_phone']) ?></div><?php endif; ?>
            <?php if (!empty($s['tax_number'])): ?><div class="muted"><?= e($s['tax_number_label'] ?? 'Tax No') ?>: <?= e($s['tax_number']) ?></div><?php endif; ?>
            <?php if (!empty($s['receipt_header'])): ?><div style="margin-top:1.5mm"><?= nl2br(e($s['receipt_header'])) ?></div><?php endif; ?>
            <div class="tag"><?= e(order_type_label($o['order_type'])) ?><?= $o['table_no'] ? ' · Table ' . e($o['table_no']) : '' ?></div>
        </div>
        <hr class="rule">
        <div class="row"><span>Order</span><span class="big-no"><?= e($o['order_no']) ?></span></div>
        <div class="row"><span>Date</span><span><?= date('d/m/Y h:i A', strtotime($o['created_at'])) ?></span></div>
        <div class="row"><span>Cashier</span><span><?= e($o['cashier']) ?></span></div>
        <?php if ($o['customer_id']): ?>
            <div class="row"><span>Customer</span><span><?= e($o['customer_name']) ?></span></div>
            <?php if ($o['customer_phone']): ?><div class="row"><span>Phone</span><span><?= e($o['customer_phone']) ?></span></div><?php endif; ?>
        <?php endif; ?>
        <?php if ($o['delivery_address']): ?><div style="margin-top:1mm"><strong>Deliver to:</strong> <?= e($o['delivery_address']) ?></div><?php endif; ?>
        <hr class="rule">
        <?php foreach ($o['items'] as $it): ?>
            <div class="item">
                <div class="row"><span class="name"><?= (int)$it['qty'] ?> × <?= e($it['product_name']) ?></span><span><?= money($it['line_total'], false) ?></span></div>
                <?php if ($it['variant_name'] || (int)$it['qty'] > 1): ?><div class="opt"><?= e($it['variant_name'] ?: '') ?><?= $it['variant_name'] && (int)$it['qty'] > 1 ? ' · ' : '' ?><?= (int)$it['qty'] > 1 ? '@ ' . money($it['unit_price'], false) : '' ?></div><?php endif; ?>
                <?php if ($it['addons_text']): ?><div class="opt">+ <?= e($it['addons_text']) ?></div><?php endif; ?>
                <?php if ($it['note']): ?><div class="opt">» <?= e($it['note']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <hr class="rule">
        <div class="row"><span>Subtotal</span><span><?= money($o['subtotal'], false) ?></span></div>
        <?php if ((float)$o['discount_amount'] > 0): ?><div class="row"><span>Discount<?= $o['discount_type'] === 'percent' ? ' (' . qty($o['discount_value']) . '%)' : '' ?></span><span>-<?= money($o['discount_amount'], false) ?></span></div><?php endif; ?>
        <?php if ((float)$o['loyalty_discount'] > 0): ?><div class="row"><span>Loyalty (<?= (int)$o['loyalty_points_used'] ?> pts)</span><span>-<?= money($o['loyalty_discount'], false) ?></span></div><?php endif; ?>
        <?php if ((float)$o['tax_rate'] > 0): ?><div class="row"><span><?= e($taxName) ?> <?= qty($o['tax_rate']) ?>%<?= $o['tax_inclusive'] ? ' incl.' : '' ?></span><span><?= money($o['tax_amount'], false) ?></span></div><?php endif; ?>
        <?php if ((float)$o['delivery_fee'] > 0): ?><div class="row"><span>Delivery fee</span><span><?= money($o['delivery_fee'], false) ?></span></div><?php endif; ?>
        <hr class="rule solid">
        <div class="row total"><span>TOTAL</span><span><?= money($o['total']) ?></span></div>
        <hr class="rule solid">
        <div class="row"><span>Paid by</span><span><?= e(payment_label($o['payment_method'])) ?></span></div>
        <?php if ($o['payment_method'] === 'cash'): ?>
            <div class="row"><span>Cash received</span><span><?= money($o['tendered'], false) ?></span></div>
            <div class="row"><span>Change</span><span><?= money($o['change_amount'], false) ?></span></div>
        <?php endif; ?>
        <?php if ((float)$o['refunded_amount'] > 0): ?>
            <div class="row"><span>Refunded</span><span>-<?= money($o['refunded_amount'], false) ?></span></div>
            <div class="row" style="font-weight:700"><span>Net paid</span><span><?= money($netPaid, false) ?></span></div>
        <?php endif; ?>
        <?php if ($o['customer_id'] && ((int)$o['loyalty_points_earned'] > 0 || (int)$o['loyalty_points_used'] > 0)): ?>
            <hr class="rule">
            <div class="row"><span>Points earned</span><span>+<?= (int)$o['loyalty_points_earned'] ?></span></div>
            <div class="row"><span>Points balance</span><span><?= (int)$o['customer_points'] ?></span></div>
        <?php endif; ?>
        <?php if ($o['notes']): ?><hr class="rule"><div><strong>Note:</strong> <?= e($o['notes']) ?></div><?php endif; ?>
        <hr class="rule">
        <div class="center">
            <?= nl2br(e($s['receipt_footer'] ?? 'Thank you for dining with us!')) ?>
            <?php if (!empty($s['restaurant_website'])): ?><div class="muted" style="margin-top:1mm"><?= e($s['restaurant_website']) ?></div><?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="invoice" id="doc">
        <div class="band"></div>
        <div class="inv-head">
            <div class="inv-brand">
                <?php if ($logo): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?>
                <div>
                    <h1><?= e($name) ?></h1>
                    <?php if (!empty($s['restaurant_address'])): ?><p><?= nl2br(e($s['restaurant_address'])) ?></p><?php endif; ?>
                    <p><?= e(implode(' · ', array_filter([$s['restaurant_phone'] ?? '', $s['restaurant_email'] ?? '']))) ?></p>
                    <?php if (!empty($s['restaurant_website'])): ?><p><?= e($s['restaurant_website']) ?></p><?php endif; ?>
                    <?php if (!empty($s['tax_number'])): ?><p><?= e($s['tax_number_label'] ?? 'Tax No') ?>: <?= e($s['tax_number']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="inv-title">
                <h2>INVOICE</h2>
                <table>
                    <tr><td>Invoice #</td><td><strong><?= e($o['order_no']) ?></strong></td></tr>
                    <tr><td>Date</td><td><?= fmt_datetime($o['created_at']) ?></td></tr>
                    <tr><td>Order type</td><td><?= e(order_type_label($o['order_type'])) ?><?= $o['table_no'] ? ' · Table ' . e($o['table_no']) : '' ?></td></tr>
                    <tr><td>Served by</td><td><?= e($o['cashier']) ?></td></tr>
                </table>
            </div>
        </div>
        <div class="inv-parties">
            <div>
                <div class="label">Billed to</div>
                <div class="box">
                    <?php if ($o['customer_id']): ?>
                        <strong><?= e($o['customer_name']) ?></strong><br>
                        <?= e(implode(' · ', array_filter([$o['customer_phone'] ?? '', $o['customer_email'] ?? '']))) ?>
                        <?php if ($o['customer_address']): ?><br><?= e($o['customer_address']) ?><?php endif; ?>
                    <?php else: ?>
                        <strong>Walk-in customer</strong>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="label"><?= $o['delivery_address'] ? 'Delivery address' : 'Payment' ?></div>
                <div class="box">
                    <?php if ($o['delivery_address']): ?>
                        <?= e($o['delivery_address']) ?>
                    <?php else: ?>
                        <strong><?= e(payment_label($o['payment_method'])) ?></strong><br>
                        <?= $o['payment_method'] === 'cash' ? 'Received ' . money($o['tendered']) . ' · Change ' . money($o['change_amount']) : 'Paid in full' ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <table class="inv-items">
            <thead><tr><th style="width:8mm">#</th><th>Item</th><th class="c">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($o['items'] as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= e($it['product_name']) ?></strong><?= $it['variant_name'] ? ' — ' . e($it['variant_name']) : '' ?>
                        <?php if ($it['addons_text']): ?><small>Add-ons: <?= e($it['addons_text']) ?></small><?php endif; ?>
                        <?php if ($it['note']): ?><small>Note: <?= e($it['note']) ?></small><?php endif; ?>
                        <?php if ((int)$it['returned_qty'] > 0): ?><small style="color:#C62828"><?= (int)$it['returned_qty'] ?> returned</small><?php endif; ?>
                    </td>
                    <td class="c"><?= (int)$it['qty'] ?></td>
                    <td class="r"><?= money($it['unit_price']) ?></td>
                    <td class="r"><?= money($it['line_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="inv-bottom">
            <div class="inv-note">
                <?php if ($o['notes']): ?><p><strong>Notes</strong><br><?= nl2br(e($o['notes'])) ?></p><?php endif; ?>
                <?php if ($o['customer_id'] && (int)$o['loyalty_points_earned'] > 0): ?><p>Loyalty points earned: <strong><?= (int)$o['loyalty_points_earned'] ?></strong> · Balance: <strong><?= (int)$o['customer_points'] ?></strong></p><?php endif; ?>
                <?php if ($o['status'] === 'completed'): ?><span class="stamp">PAID</span><?php else: ?><span class="stamp refund"><?= $o['status'] === 'refunded' ? 'REFUNDED' : 'PARTIALLY REFUNDED' ?></span><?php endif; ?>
            </div>
            <div class="inv-totals">
                <div class="row"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
                <?php if ((float)$o['discount_amount'] > 0): ?><div class="row"><span>Discount<?= $o['discount_type'] === 'percent' ? ' (' . qty($o['discount_value']) . '%)' : '' ?></span><span>− <?= money($o['discount_amount']) ?></span></div><?php endif; ?>
                <?php if ((float)$o['loyalty_discount'] > 0): ?><div class="row"><span>Loyalty discount</span><span>− <?= money($o['loyalty_discount']) ?></span></div><?php endif; ?>
                <?php if ((float)$o['tax_rate'] > 0): ?><div class="row"><span><?= e($taxName) ?> (<?= qty($o['tax_rate']) ?>%<?= $o['tax_inclusive'] ? ' incl.' : '' ?>)</span><span><?= money($o['tax_amount']) ?></span></div><?php endif; ?>
                <?php if ((float)$o['delivery_fee'] > 0): ?><div class="row"><span>Delivery fee</span><span><?= money($o['delivery_fee']) ?></span></div><?php endif; ?>
                <div class="row grand"><span>Total</span><span><?= money($o['total']) ?></span></div>
                <?php if ((float)$o['refunded_amount'] > 0): ?>
                    <div class="row" style="color:#C62828"><span>Refunded</span><span>− <?= money($o['refunded_amount']) ?></span></div>
                    <div class="row" style="font-weight:700"><span>Net paid</span><span><?= money($netPaid) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="inv-foot"><?= nl2br(e($s['receipt_footer'] ?? 'Thank you for dining with us!')) ?></div>
    </div>
<?php endif; ?>
</div>

<script>
<?php if ($autoprint): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 300));
<?php endif; ?>
const pdfBtn = document.getElementById('pdfBtn');
if (pdfBtn) pdfBtn.addEventListener('click', () => {
    pdfBtn.disabled = true;
    const go = () => {
        const el = document.getElementById('doc');
        const a4 = <?= $isA4 ? 'true' : 'false' ?>;
        const shadow = el.style.boxShadow; el.style.boxShadow = 'none';
        const mmH = el.offsetHeight * 25.4 / 96 + 2;
        html2pdf().set({
            margin: 0, filename: '<?= e(($isA4 ? 'invoice-' : 'receipt-') . $o['order_no']) ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 3, backgroundColor: '#ffffff' },
            jsPDF: a4 ? { unit: 'mm', format: 'a4' } : { unit: 'mm', format: [<?= $width ?>, Math.max(mmH, 60)] },
        }).from(el).save().then(() => { el.style.boxShadow = shadow; pdfBtn.disabled = false; });
    };
    if (window.html2pdf) return go();
    const s = document.createElement('script');
    s.src = '<?= base_url() ?>/assets/vendor/html2pdf/html2pdf.bundle.min.js';
    s.onload = go;
    document.head.appendChild(s);
});
</script>
</body>
</html>
