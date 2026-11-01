<?php
$reports = [
    ['reports/profit', 'Profit & loss', 'Revenue, cost of goods, expenses and net profit with a daily breakdown.', 'bi-graph-up-arrow', 'success'],
    ['reports/sales', 'Sales report', 'Daily sales, discounts, tax, refunds and order-type split.', 'bi-receipt', 'brand'],
    ['reports/products', 'Product sales', 'Best sellers, quantities, revenue and margin per product and category.', 'bi-box-seam', 'info'],
    ['reports/expenses', 'Expense report', 'Spending by category with every entry listed.', 'bi-wallet2', 'warning'],
    ['reports/payments', 'Payment methods', 'Cash, card and online collections day by day.', 'bi-credit-card', 'brand'],
    ['reports/inventory', 'Inventory valuation', 'Stock on hand, value at cost, usage and wastage.', 'bi-basket', 'info'],
    ['reports/staff', 'Staff performance', 'Orders, sales, discounts and cash accuracy per team member.', 'bi-person-badge', 'success'],
    ['register/history', 'Register sessions', 'Every shift with expected vs counted cash.', 'bi-cash-coin', 'warning'],
];
?>
<div class="card kpi accent mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-md-4"><div class="kpi-label">This month · net profit</div><div class="kpi-value" style="font-size:2rem"><?= money($summary['net_profit']) ?></div></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Net sales</div><strong class="text-white fs-5"><?= money($summary['net_sales']) ?></strong></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Gross profit</div><strong class="text-white fs-5"><?= money($summary['gross_profit']) ?></strong></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Expenses</div><strong class="text-white fs-5"><?= money($summary['expenses']) ?></strong></div>
        <div class="col-6 col-md-2"><div class="kpi-label">Orders</div><strong class="text-white fs-5"><?= number_format($summary['orders']) ?></strong></div>
    </div>
</div>
<div class="row g-3">
    <?php foreach ($reports as [$r, $t, $d, $i, $tone]): ?>
        <div class="col-sm-6 col-xl-3">
            <a href="<?= url($r) ?>" class="card kpi d-block text-reset h-100">
                <span class="kpi-icon tone-<?= $tone ?> mb-3"><i class="bi <?= $i ?>"></i></span>
                <h2 class="h6 fw-bold mb-1"><?= $t ?></h2>
                <p class="small text-muted mb-0"><?= $d ?></p>
            </a>
        </div>
    <?php endforeach; ?>
</div>
