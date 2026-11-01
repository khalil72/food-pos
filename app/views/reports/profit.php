<?php $exports = true; $pdfName = 'profit-loss'; ?>
<div class="d-flex align-items-center gap-2 mb-3 no-print"><a href="<?= url('reports') ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All reports</a></div>
<?php View::partial('date_filter', compact('from', 'to', 'preset', 'exports', 'pdfName')); ?>

<div id="report-area">
    <div class="print-only mb-3">
        <h2 class="h4 mb-0"><?= e(setting('restaurant_name')) ?> — Profit &amp; Loss</h2>
        <div class="text-muted"><?= e(range_label($from, $to)) ?></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Net revenue</div><div class="kpi-value" style="font-size:1.3rem"><?= money($m['net_revenue']) ?></div><div class="kpi-foot">Excluding tax, after refunds</div></div></div>
        <div class="col-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Gross profit</div><div class="kpi-value" style="font-size:1.3rem"><?= money($m['gross_profit']) ?></div><div class="kpi-foot"><?= num($m['gross_margin'], 1) ?>% margin</div></div></div>
        <div class="col-6 col-xl-3"><div class="card kpi"><div class="kpi-label">Expenses</div><div class="kpi-value" style="font-size:1.3rem"><?= money($m['expenses']) ?></div><div class="kpi-foot"><?= count($expenses) ?> categories</div></div></div>
        <div class="col-6 col-xl-3"><div class="card kpi accent"><div class="kpi-label">Net profit</div><div class="kpi-value" style="font-size:1.3rem"><?= money($m['net_profit']) ?></div><div class="kpi-foot"><?= $m['net_revenue'] > 0 ? num($m['net_profit'] / $m['net_revenue'] * 100, 1) : '0.0' ?>% of revenue</div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h2>Statement</h2></div>
                <div class="card-body">
                    <div class="stat-line"><span>Gross sales <span class="text-muted small">(incl. tax &amp; delivery)</span></span><span><?= money($m['gross_sales']) ?></span></div>
                    <div class="stat-line text-muted small"><span class="ps-3">Discounts &amp; loyalty given</span><span>(<?= money($m['discounts']) ?>)</span></div>
                    <div class="stat-line"><span>Less: refunds</span><span class="text-danger">− <?= money($m['refunds']) ?></span></div>
                    <div class="stat-line"><span>Less: tax collected</span><span class="text-danger">− <?= money($m['tax']) ?></span></div>
                    <div class="stat-line fw-bold"><span>Net revenue</span><span><?= money($m['net_revenue']) ?></span></div>
                    <div class="stat-line"><span>Cost of goods sold</span><span class="text-danger">− <?= money($m['cogs']) ?></span></div>
                    <div class="stat-line fw-bold"><span>Gross profit</span><span><?= money($m['gross_profit']) ?></span></div>
                    <?php foreach ($expenses as $x): ?>
                        <div class="stat-line small"><span class="ps-3"><span class="color-dot me-1" style="background:<?= e($x['color']) ?>"></span><?= e($x['name']) ?></span><span class="text-danger">− <?= money($x['total']) ?></span></div>
                    <?php endforeach; ?>
                    <div class="stat-line"><span>Total operating expenses</span><span class="text-danger">− <?= money($m['expenses']) ?></span></div>
                    <div class="stat-line total"><span>Net profit</span><span class="<?= $m['net_profit'] < 0 ? 'text-danger' : 'text-success' ?>"><?= money($m['net_profit']) ?></span></div>
                </div>
            </div>
            <p class="small text-muted"><i class="bi bi-info-circle me-1"></i>Purchases of stock are not expenses here — their cost reaches the P&amp;L through cost of goods sold when items are sold.</p>
        </div>
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h2>Daily profit</h2></div>
                <div class="card-body"><div class="chart-box"><canvas id="plChart" role="img" aria-label="Daily profit chart"></canvas></div></div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header"><h2>Daily breakdown</h2></div>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead><tr><th>Date</th><th class="text-end">Orders</th><th class="text-end">Net revenue</th><th class="text-end">COGS</th><th class="text-end">Gross profit</th><th class="text-end">Expenses</th><th class="text-end">Net profit</th></tr></thead>
                <tbody>
                <?php foreach (array_reverse($days) as $d): ?>
                    <tr>
                        <td><?= fmt_date($d['date']) ?></td>
                        <td class="text-end"><?= $d['orders'] ?></td>
                        <td class="text-end"><?= money($d['net_revenue']) ?></td>
                        <td class="text-end"><?= money($d['cogs'] - $d['refund_cost']) ?></td>
                        <td class="text-end"><?= money($d['gross_profit']) ?></td>
                        <td class="text-end"><?= money($d['expenses']) ?></td>
                        <td class="text-end fw-semibold <?= $d['net_profit'] < 0 ? 'text-danger' : '' ?>"><?= money($d['net_profit']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td>Total</td><td class="text-end"><?= $m['orders'] ?></td><td class="text-end"><?= money($m['net_revenue']) ?></td><td class="text-end"><?= money($m['cogs']) ?></td><td class="text-end"><?= money($m['gross_profit']) ?></td><td class="text-end"><?= money($m['expenses']) ?></td><td class="text-end"><?= money($m['net_profit']) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const d = <?= json_encode(['labels' => array_map(fn($d) => date('d M', strtotime($d['date'])), $days), 'gp' => array_column($days, 'gross_profit'), 'ex' => array_column($days, 'expenses'), 'np' => array_column($days, 'net_profit')], JSON_HEX_TAG) ?>;
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#71685F';
    new Chart(document.getElementById('plChart'), {
        data: { labels: d.labels, datasets: [
            { type: 'bar', label: 'Gross profit', data: d.gp, backgroundColor: 'rgba(21,128,61,.75)', borderRadius: 5, maxBarThickness: 18 },
            { type: 'bar', label: 'Expenses', data: d.ex, backgroundColor: 'rgba(245,165,36,.8)', borderRadius: 5, maxBarThickness: 18 },
            { type: 'line', label: 'Net profit', data: d.np, borderColor: '#E8572A', backgroundColor: '#E8572A', tension: .35, pointRadius: 2, borderWidth: 2.5 },
        ] },
        options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${APP.money(c.parsed.y)}` } } },
            scales: { x: { grid: { display: false } }, y: { grid: { color: '#F1ECE5' } } } },
    });
});
</script>
