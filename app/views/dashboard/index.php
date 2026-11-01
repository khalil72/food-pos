<?php
$change = function (float $cur, float $old, bool $higherIsGood = true): string {
    if ($old == 0.0) {
        return $cur > 0 ? '<span class="trend-up"><i class="bi bi-arrow-up-right"></i> new</span>' : '<span class="text-muted">—</span>';
    }
    $pct = ($cur - $old) / abs($old) * 100;
    $good = $higherIsGood ? $pct >= 0 : $pct <= 0;
    return '<span class="' . ($good ? 'trend-up' : 'trend-down') . '"><i class="bi bi-arrow-' . ($pct >= 0 ? 'up' : 'down') . '-right"></i> ' . num(abs($pct), 1) . '%</span>';
};
$kpis = [
    ['Net Sales', money($now['net_sales']), 'bi-currency-exchange', 'brand', $change($now['net_sales'], $prev['net_sales']), 'vs previous period'],
    ['Orders', number_format($now['orders']), 'bi-receipt', 'info', $change($now['orders'], $prev['orders']), 'Avg ' . money($now['avg_order'])],
    ['Gross Profit', money($now['gross_profit']), 'bi-graph-up-arrow', 'success', $change($now['gross_profit'], $prev['gross_profit']), num($now['gross_margin'], 1) . '% margin'],
    ['Expenses', money($now['expenses']), 'bi-wallet2', 'warning', $change($now['expenses'], $prev['expenses'], false), 'Operating costs'],
    ['Refunds', money($now['refunds']), 'bi-arrow-counterclockwise', 'danger', $change($now['refunds'], $prev['refunds'], false), $now['refund_count'] . ' refund(s)'],
];
$typeLabels = array_map(fn($t) => order_type_label($t['order_type']), $types);
?>
<?php View::partial('date_filter', compact('from', 'to', 'preset')); ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-4">
        <div class="card kpi accent">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Net Profit</div>
                    <div class="kpi-value" style="font-size:2rem"><?= money($now['net_profit']) ?></div>
                </div>
                <span class="kpi-icon tone-dark"><i class="bi bi-trophy"></i></span>
            </div>
            <div class="kpi-foot mt-2"><?= $change($now['net_profit'], $prev['net_profit']) ?> &nbsp;vs previous period</div>
            <div class="d-flex gap-4 mt-3 small" style="color:#D8CEC4">
                <div><div class="text-uppercase" style="font-size:.66rem;letter-spacing:.08em;color:#9C9085">Revenue (ex. tax)</div><strong class="text-white"><?= money($now['net_revenue']) ?></strong></div>
                <div><div class="text-uppercase" style="font-size:.66rem;letter-spacing:.08em;color:#9C9085">Cost of goods</div><strong class="text-white"><?= money($now['cogs']) ?></strong></div>
                <div><div class="text-uppercase" style="font-size:.66rem;letter-spacing:.08em;color:#9C9085">Tax collected</div><strong class="text-white"><?= money($now['tax']) ?></strong></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="row g-3 h-100">
            <?php foreach ($kpis as [$label, $value, $icon, $tone, $delta, $foot]): ?>
                <div class="col-6 col-md-4 <?= $label === 'Refunds' ? 'col-xl-4' : 'col-xl-4' ?>">
                    <div class="card kpi">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="kpi-label"><?= e($label) ?></div>
                            <span class="kpi-icon tone-<?= $tone ?>"><i class="bi <?= $icon ?>"></i></span>
                        </div>
                        <div class="kpi-value" style="font-size:1.35rem"><?= $value ?></div>
                        <div class="kpi-foot"><?= $delta ?> · <?= e($foot) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="col-6 col-md-4 col-xl-4">
                <a href="<?= url('register') ?>" class="card kpi d-block text-reset">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="kpi-label">Open registers</div>
                        <span class="kpi-icon tone-success"><i class="bi bi-cash-coin"></i></span>
                    </div>
                    <div class="kpi-value" style="font-size:1.35rem"><?= count($openRegisters) ?></div>
                    <div class="kpi-foot text-truncate"><?= $openRegisters ? e(implode(', ', array_column($openRegisters, 'user_name'))) : 'No active shift' ?></div>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <h2>Sales &amp; profit trend</h2>
                    <p class="card-sub"><?= e(range_label($trend[0]['date'], end($trend)['date'])) ?></p>
                </div>
                <div class="d-flex gap-3 small text-muted">
                    <span><span class="color-dot" style="background:#E8572A"></span> Net sales</span>
                    <span><span class="color-dot" style="background:#F5A524"></span> Expenses</span>
                    <span><span class="color-dot" style="background:#15803D"></span> Net profit</span>
                </div>
            </div>
            <div class="card-body"><div class="chart-box"><canvas id="trendChart" aria-label="Sales trend chart" role="img"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><div><h2>Sales by category</h2><p class="card-sub">Share of item revenue</p></div></div>
            <div class="card-body">
                <?php if ($categories): ?>
                    <div class="chart-box sm"><canvas id="categoryChart" aria-label="Sales by category" role="img"></canvas></div>
                <?php else: ?>
                    <div class="empty-state"><div class="empty-icon"><i class="bi bi-pie-chart"></i></div><p class="mt-2 mb-0">No sales in this period.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-8">
        <div class="card h-100">
            <div class="card-header"><div><h2>Busiest hours</h2><p class="card-sub">Orders per hour of day</p></div></div>
            <div class="card-body"><div class="chart-box sm"><canvas id="hourlyChart" aria-label="Orders by hour" role="img"></canvas></div></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><div><h2>Order channels</h2><p class="card-sub">Dine-in, takeaway &amp; delivery</p></div></div>
            <div class="card-body">
                <?php $typeTotal = array_sum(array_column($types, 'c')) ?: 1;
                foreach (['dine_in', 'takeaway', 'delivery'] as $t):
                    $row = current(array_filter($types, fn($x) => $x['order_type'] === $t)) ?: ['c' => 0, 's' => 0]; ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold"><i class="bi <?= order_type_icon($t) ?> me-1 text-muted"></i><?= order_type_label($t) ?></span>
                            <span class="small text-muted"><?= (int)$row['c'] ?> orders · <strong class="text-body"><?= money($row['s']) ?></strong></span>
                        </div>
                        <div class="progress progress-thin"><div class="progress-bar" style="width:<?= round($row['c'] / $typeTotal * 100) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
                <hr>
                <div class="small text-muted mb-2 fw-semibold text-uppercase" style="letter-spacing:.06em;font-size:.7rem">Payments received</div>
                <?php foreach (['cash', 'card', 'online'] as $m):
                    $row = current(array_filter($payments, fn($x) => $x['method'] === $m)) ?: ['s' => 0]; ?>
                    <div class="stat-line"><span><?= payment_label($m) ?></span><strong><?= money($row['s']) ?></strong></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card table-card h-100">
            <div class="card-header">
                <div><h2>Recent transactions</h2><p class="card-sub">Latest orders across all registers</p></div>
                <a href="<?= url('orders') ?>" class="btn btn-sm btn-light">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Order</th><th>Type</th><th>Customer</th><th>Payment</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr style="cursor:pointer" onclick="location='<?= url('orders/view', ['id' => $o['id']]) ?>'">
                            <td><div class="cell-title"><?= e($o['order_no']) ?></div><div class="cell-sub"><?= fmt_datetime($o['created_at']) ?> · <?= e($o['cashier']) ?></div></td>
                            <td><i class="bi <?= order_type_icon($o['order_type']) ?> text-muted me-1"></i><?= order_type_label($o['order_type']) ?></td>
                            <td><?= e($o['customer'] ?? 'Walk-in') ?></td>
                            <td><?= payment_label($o['payment_method']) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td class="text-end fw-semibold"><?= money($o['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="6"><div class="empty-state">No orders yet. <a href="<?= url('pos') ?>">Make the first sale</a>.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header"><div><h2>Top sellers</h2><p class="card-sub">By quantity sold</p></div><a href="<?= e(url('reports/products', ['from' => $from, 'to' => $to])) ?>" class="small">Report</a></div>
            <?php $maxQty = max(1, ...array_map(fn($p) => (int)$p['qty'], $topProducts ?: [['qty' => 1]]));
            foreach ($topProducts as $i => $p): ?>
                <div class="list-row">
                    <span class="rank"><?= $i + 1 ?></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between"><span class="cell-title text-truncate"><?= e($p['name']) ?></span><span class="small fw-semibold"><?= (int)$p['qty'] ?> sold</span></div>
                        <div class="progress progress-thin mt-1"><div class="progress-bar" style="width:<?= round($p['qty'] / $maxQty * 100) ?>%"></div></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$topProducts): ?><div class="empty-state py-4 small">No sales in this period.</div><?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><div><h2>Low stock alerts</h2><p class="card-sub">At or below reorder level</p></div><a href="<?= url('inventory', ['tab' => 'low']) ?>" class="small">Manage</a></div>
            <?php foreach ($lowStock as $s): ?>
                <div class="list-row">
                    <span class="kpi-icon tone-<?= (float)$s['stock_qty'] <= 0 ? 'danger' : 'warning' ?>" style="width:32px;height:32px;font-size:.95rem"><i class="bi <?= $s['type'] === 'product' ? 'bi-box-seam' : 'bi-basket' ?>"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="cell-title text-truncate"><?= e($s['name']) ?></div>
                        <div class="cell-sub"><?= ucfirst($s['type']) ?> · reorder at <?= qty($s['low_stock_level']) ?> <?= e($s['unit']) ?></div>
                    </div>
                    <span class="badge badge-soft-<?= (float)$s['stock_qty'] <= 0 ? 'danger' : 'warning' ?>"><?= qty($s['stock_qty']) ?> <?= e($s['unit']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?><div class="empty-state py-4 small"><i class="bi bi-check-circle text-success"></i> All stock levels are healthy.</div><?php endif; ?>
        </div>
    </div>
</div>

<script>
window.DASH = <?= json_encode([
    'trend' => [
        'labels' => array_map(fn($d) => date('d M', strtotime($d['date'])), $trend),
        'sales' => array_map(fn($d) => round($d['sales'] - $d['refunds'], 2), $trend),
        'expenses' => array_column($trend, 'expenses'),
        'profit' => array_column($trend, 'net_profit'),
    ],
    'categories' => ['labels' => array_column($categories, 'name'), 'values' => array_map('floatval', array_column($categories, 'total')), 'colors' => array_column($categories, 'color')],
    'hourly' => $hourly,
], JSON_HEX_TAG) ?>;
</script>
