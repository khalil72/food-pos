<?php $s = $summary; ?>
<div class="toolbar">
    <a href="<?= url(has_role('admin', 'manager') ? 'register/history' : 'register') ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" data-pdf-export="#zreport" data-pdf-name="register-<?= (int)$reg['id'] ?>"><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
        <button class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Z-report</button>
    </div>
</div>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card" id="zreport">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div>
                        <h2 class="h4 fw-bold mb-1"><?= $reg['status'] === 'closed' ? 'Z-Report' : 'X-Report (shift in progress)' ?></h2>
                        <div class="text-muted small"><?= e(setting('restaurant_name')) ?> · Register #<?= (int)$reg['id'] ?> · <?= e($reg['user_name']) ?></div>
                    </div>
                    <?= status_badge($reg['status']) ?>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <h3 class="h6 text-uppercase text-muted small fw-bold">Shift</h3>
                        <div class="stat-line"><span>Opened</span><span><?= fmt_datetime($reg['opened_at']) ?></span></div>
                        <div class="stat-line"><span>Closed</span><span><?= fmt_datetime($reg['closed_at']) ?></span></div>
                        <div class="stat-line"><span>Orders</span><strong><?= $s['orders'] ?></strong></div>
                        <div class="stat-line"><span>Gross sales</span><strong><?= money($s['sales_total']) ?></strong></div>
                        <div class="stat-line"><span>Discounts given</span><span><?= money($s['discounts']) ?></span></div>
                        <div class="stat-line"><span>Refunds</span><span class="text-danger"><?= money($s['refund_total']) ?></span></div>
                        <h3 class="h6 text-uppercase text-muted small fw-bold mt-4">Sales by channel</h3>
                        <?php foreach ($types as $t): ?><div class="stat-line"><span><?= order_type_label($t['order_type']) ?> (<?= (int)$t['c'] ?>)</span><span><?= money($t['s']) ?></span></div><?php endforeach; ?>
                        <?php if (!$types): ?><p class="small text-muted">No sales.</p><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h3 class="h6 text-uppercase text-muted small fw-bold">Payments</h3>
                        <?php foreach ($s['payments'] as $m => $v): ?><div class="stat-line"><span><?= payment_label($m) ?></span><span><?= money($v) ?></span></div><?php endforeach; ?>
                        <h3 class="h6 text-uppercase text-muted small fw-bold mt-4">Cash drawer</h3>
                        <div class="stat-line"><span>Opening cash</span><span><?= money($s['opening']) ?></span></div>
                        <div class="stat-line"><span>+ Cash sales</span><span><?= money($s['payments']['cash']) ?></span></div>
                        <div class="stat-line"><span>− Cash refunds</span><span><?= money($s['refunds']['cash']) ?></span></div>
                        <div class="stat-line"><span>+ Cash in</span><span><?= money($s['cash_in']) ?></span></div>
                        <div class="stat-line"><span>− Cash out</span><span><?= money($s['cash_out']) ?></span></div>
                        <div class="stat-line"><span>− Drawer expenses</span><span><?= money($s['expenses']) ?></span></div>
                        <div class="stat-line total"><span>Expected</span><span><?= money($reg['expected_cash'] ?? $s['expected_cash']) ?></span></div>
                        <?php if ($reg['status'] === 'closed'): $d = (float)$reg['difference']; ?>
                            <div class="stat-line"><span>Counted</span><strong><?= money($reg['closing_cash']) ?></strong></div>
                            <div class="stat-line"><span>Difference</span><strong class="<?= $d < 0 ? 'text-danger' : ($d > 0 ? 'text-primary' : 'text-success') ?>"><?= $d == 0.0 ? 'Balanced' : money($d) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($movements || $expenses): ?>
                <h3 class="h6 text-uppercase text-muted small fw-bold mt-4">Cash movements &amp; drawer expenses</h3>
                <table class="table table-sm">
                    <tbody>
                    <?php foreach ($movements as $m): ?><tr><td class="small"><?= date('h:i A', strtotime($m['created_at'])) ?></td><td><?= $m['type'] === 'in' ? 'Cash in' : 'Cash out' ?></td><td><?= e($m['reason']) ?></td><td class="text-end"><?= money($m['amount']) ?></td></tr><?php endforeach; ?>
                    <?php foreach ($expenses as $x): ?><tr><td class="small"><?= fmt_date($x['expense_date']) ?></td><td>Expense</td><td><?= e($x['description']) ?></td><td class="text-end"><?= money($x['amount']) ?></td></tr><?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <?php if ($reg['notes']): ?><p class="small text-muted mt-3 mb-0"><strong>Notes:</strong> <?= e($reg['notes']) ?></p><?php endif; ?>
            </div>
        </div>
    </div>
</div>
