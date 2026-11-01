<?php if (!$register): ?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card text-center">
            <div class="card-body p-4 p-md-5">
                <div class="empty-icon mb-3"><i class="bi bi-cash-coin"></i></div>
                <h2 class="h4 fw-bold">Open the cash register</h2>
                <p class="text-muted">Count the cash in the drawer and enter the amount to begin your shift.</p>
                <form method="post" action="<?= url('register/open') ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <label class="form-label" for="openCash">Opening cash</label>
                    <div class="input-group input-group-lg mb-3"><span class="input-group-text"><?= e(setting('currency_symbol', 'Rs')) ?></span><input type="number" class="form-control fw-bold" id="openCash" name="opening_cash" min="0" step="0.01" value="0" required autofocus></div>
                    <button class="btn btn-primary btn-lg w-100"><i class="bi bi-unlock me-1"></i>Open register</button>
                </form>
            </div>
        </div>
        <?php if ($recent): ?>
        <div class="card table-card mt-3">
            <div class="card-header"><h2>Your recent shifts</h2><?php if (has_role('admin', 'manager')): ?><a href="<?= url('register/history') ?>" class="small">All sessions</a><?php endif; ?></div>
            <?php foreach ($recent as $r): ?>
                <a href="<?= url('register/view', ['id' => $r['id']]) ?>" class="list-row text-reset">
                    <span class="flex-grow-1"><span class="cell-title d-block"><?= fmt_datetime($r['opened_at']) ?></span><span class="cell-sub">Closed <?= fmt_datetime($r['closed_at']) ?></span></span>
                    <span class="badge badge-soft-<?= (float)$r['difference'] == 0.0 ? 'success' : ((float)$r['difference'] > 0 ? 'info' : 'danger') ?>"><?= (float)$r['difference'] == 0.0 ? 'Balanced' : ((float)$r['difference'] > 0 ? '+' : '') . money($r['difference']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: $s = $summary; ?>
<div class="toolbar">
    <div class="d-flex gap-2">
        <a href="<?= url('pos') ?>" class="btn btn-primary"><i class="bi bi-cart3 me-1"></i>Go to POS</a>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#moveModal" data-type="in"><i class="bi bi-box-arrow-in-down me-1"></i>Cash in</button>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#moveModal" data-type="out"><i class="bi bi-box-arrow-up me-1"></i>Cash out</button>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_role('admin', 'manager')): ?><a href="<?= url('register/history') ?>" class="btn btn-light">All sessions</a><?php endif; ?>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#closeModal"><i class="bi bi-lock me-1"></i>Close register</button>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card kpi accent mb-3">
            <div class="kpi-label">Expected cash in drawer</div>
            <div class="kpi-value" style="font-size:2rem"><?= money($s['expected_cash']) ?></div>
            <div class="kpi-foot">Opening + cash sales − refunds ± cash movements − drawer expenses</div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="stat-line"><span>Opening cash</span><strong><?= money($s['opening']) ?></strong></div>
                <div class="stat-line"><span>Cash sales</span><strong class="text-success">+ <?= money($s['payments']['cash']) ?></strong></div>
                <div class="stat-line"><span>Cash refunds</span><strong class="text-danger">− <?= money($s['refunds']['cash']) ?></strong></div>
                <div class="stat-line"><span>Cash in</span><strong class="text-success">+ <?= money($s['cash_in']) ?></strong></div>
                <div class="stat-line"><span>Cash out</span><strong class="text-danger">− <?= money($s['cash_out']) ?></strong></div>
                <div class="stat-line"><span>Expenses from drawer</span><strong class="text-danger">− <?= money($s['expenses']) ?></strong></div>
                <div class="stat-line total"><span>Expected</span><span><?= money($s['expected_cash']) ?></span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <?php foreach ([['Orders', $s['orders'], 'bi-receipt', 'info'], ['Sales', money($s['sales_total']), 'bi-graph-up', 'brand'], ['Card', money($s['payments']['card']), 'bi-credit-card', 'success'], ['Online', money($s['payments']['online']), 'bi-phone', 'warning']] as [$l, $v, $i, $t]): ?>
                <div class="col-6 col-md-3"><div class="card kpi"><span class="kpi-icon tone-<?= $t ?> mb-2"><i class="bi <?= $i ?>"></i></span><div class="kpi-label"><?= $l ?></div><div class="kpi-value" style="font-size:1.15rem"><?= $v ?></div></div></div>
            <?php endforeach; ?>
        </div>
        <div class="card table-card">
            <div class="card-header"><h2>Cash movements this shift</h2></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Time</th><th>Type</th><th>Reason</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($movements as $m): ?>
                        <tr><td><?= date('h:i A', strtotime($m['created_at'])) ?></td><td><?= $m['type'] === 'in' ? '<span class="badge badge-soft-success">Cash in</span>' : '<span class="badge badge-soft-danger">Cash out</span>' ?></td><td><?= e($m['reason']) ?></td><td class="text-end fw-semibold"><?= money($m['amount']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$movements): ?><tr><td colspan="4"><div class="empty-state py-4 small">No cash in/out recorded yet.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" method="post" action="<?= url('register/movement') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="type" id="moveType" value="in">
            <div class="modal-header"><h5 class="modal-title" id="moveTitle">Cash in</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label required" for="moveAmt">Amount</label>
                <input type="number" class="form-control form-control-lg mb-3" id="moveAmt" name="amount" min="0.01" step="0.01" required>
                <label class="form-label required" for="moveReason">Reason</label>
                <input class="form-control" id="moveReason" name="reason" maxlength="255" required placeholder="e.g. Float top-up, bank deposit">
            </div>
            <div class="modal-footer"><button class="btn btn-primary w-100">Save</button></div>
        </form>
    </div>
</div>
<div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('register/close') ?>" data-confirm="Close the register and end your shift?" data-confirm-ok="Close register">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Close register</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="d-flex justify-content-between p-3 rounded mb-3" style="background:var(--surface-2)"><span>Expected cash</span><strong id="expectedCash" data-v="<?= $s['expected_cash'] ?>"><?= money($s['expected_cash']) ?></strong></div>
                <label class="form-label required" for="countCash">Counted cash in drawer</label>
                <input type="number" class="form-control form-control-lg fw-bold" id="countCash" name="closing_cash" min="0" step="0.01" required>
                <div class="mt-2 fw-semibold" id="diffOut"></div>
                <label class="form-label mt-3" for="closeNotes">Notes</label>
                <input class="form-control" id="closeNotes" name="notes" maxlength="255" placeholder="Explain any difference">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-dark">Close register</button></div>
        </form>
    </div>
</div>
<script>
document.getElementById('moveModal').addEventListener('show.bs.modal', (e) => {
    const t = (e.relatedTarget && e.relatedTarget.dataset.type) || 'in';
    document.getElementById('moveType').value = t;
    document.getElementById('moveTitle').textContent = t === 'in' ? 'Cash in' : 'Cash out';
});
document.getElementById('countCash').addEventListener('input', (e) => {
    const d = APP.round((Number(e.target.value) || 0) - Number(document.getElementById('expectedCash').dataset.v));
    const out = document.getElementById('diffOut');
    out.className = 'mt-2 fw-semibold ' + (d === 0 ? 'text-success' : d > 0 ? 'text-primary' : 'text-danger');
    out.textContent = e.target.value === '' ? '' : d === 0 ? 'Balanced ✓' : (d > 0 ? 'Over by ' : 'Short by ') + APP.money(Math.abs(d));
});
</script>
<?php endif; ?>
