<?php
$activityIcon = fn(string $a) => match (true) {
    str_contains($a, 'login') => ['bi-box-arrow-in-right', 'info'],
    str_contains($a, 'logout') => ['bi-box-arrow-right', 'secondary'],
    str_contains($a, 'password') => ['bi-shield-lock', 'warning'],
    str_contains($a, 'order') => ['bi-receipt', 'success'],
    str_contains($a, 'refund') => ['bi-arrow-counterclockwise', 'danger'],
    str_contains($a, 'register') || str_contains($a, 'cash') => ['bi-cash-coin', 'warning'],
    str_contains($a, 'expense') => ['bi-wallet2', 'brand'],
    str_contains($a, 'delete') => ['bi-trash3', 'danger'],
    str_contains($a, 'backup') => ['bi-database', 'info'],
    default => ['bi-pencil-square', 'brand'],
};
$roleDesc = ['admin' => 'Full access to every module', 'manager' => 'Runs menu, stock, finance & reports', 'cashier' => 'Takes orders and manages the till'][$u['role']] ?? '';
?>
<!-- Profile banner -->
<div class="card profile-hero mb-3">
    <div class="profile-cover"></div>
    <div class="profile-head">
        <span class="profile-avatar"><?= e(initials($u['name'])) ?></span>
        <div class="profile-id">
            <h2><?= e($u['name']) ?></h2>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?= role_badge($u['role']) ?>
                <span class="text-muted small">@<?= e($u['username']) ?></span>
                <span class="text-muted small d-none d-sm-inline">·</span>
                <span class="text-muted small"><?= e($roleDesc) ?></span>
            </div>
        </div>
        <div class="profile-meta">
            <div><small>Member since</small><strong><?= fmt_date($u['created_at']) ?></strong></div>
            <div><small>Last sign-in</small><strong><?= $u['last_login'] ? date('d M, h:i A', strtotime($u['last_login'])) : '—' ?></strong></div>
        </div>
    </div>
    <div class="profile-stats">
        <?php foreach ([
            ['Orders today', number_format((int)$stats['today']), money($stats['today_sales']) . ' sold', 'bi-lightning-charge', 'brand'],
            ['All-time orders', number_format((int)$stats['orders']), $shifts . ' shift' . ($shifts === 1 ? '' : 's') . ' worked', 'bi-receipt', 'info'],
            ['All-time sales', money($stats['sales']), 'Total value rung up', 'bi-graph-up-arrow', 'success'],
            ['Average order', money($stats['avg']), 'Per order', 'bi-basket', 'warning'],
        ] as [$label, $value, $foot, $icon, $tone]): ?>
            <div class="profile-stat">
                <span class="kpi-icon tone-<?= $tone ?>"><i class="bi <?= $icon ?>"></i></span>
                <div class="min-w-0">
                    <div class="kpi-label"><?= $label ?></div>
                    <div class="profile-stat-value"><?= $value ?></div>
                    <div class="cell-sub"><?= e($foot) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <!-- Account details -->
        <form method="post" action="<?= url('profile/save') ?>" class="card mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="details">
            <div class="card-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="kpi-icon tone-brand"><i class="bi bi-person-vcard"></i></span>
                    <div><h2>Account details</h2><p class="card-sub">How you appear on receipts and reports</p></div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required" for="pn">Full name</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-person"></i></span><input class="form-control" id="pn" name="name" value="<?= e(old('name', $u['name'])) ?>" maxlength="100" required></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pu">Username</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-at"></i></span><input class="form-control" id="pu" value="<?= e($u['username']) ?>" disabled></div>
                        <div class="form-text">Only an administrator can change usernames.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pe">Email</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input type="email" class="form-control" id="pe" name="email" value="<?= e(old('email', $u['email'])) ?>" maxlength="150" placeholder="name@example.com"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pp">Phone</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-telephone"></i></span><input class="form-control" id="pp" name="phone" value="<?= e(old('phone', $u['phone'])) ?>" maxlength="30" placeholder="03xx xxxxxxx"></div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-end py-3">
                <button class="btn btn-primary px-4"><i class="bi bi-check2 me-1"></i>Save details</button>
            </div>
        </form>

        <!-- Security -->
        <form method="post" action="<?= url('profile/save') ?>" class="card" id="pwForm">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="password">
            <div class="card-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="kpi-icon tone-warning"><i class="bi bi-shield-lock"></i></span>
                    <div><h2>Password &amp; security</h2><p class="card-sub">Use at least 8 characters with a mix of letters and numbers</p></div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="cp">Current password</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-key"></i></span><input type="password" class="form-control" id="cp" name="current_password" autocomplete="current-password" required></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="np">New password</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-lock"></i></span><input type="password" class="form-control" id="np" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required></div>
                        <div class="pw-meter mt-2" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                        <div class="form-text" id="pwHint">Enter a new password.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required" for="np2">Confirm new password</label>
                        <div class="input-group"><span class="input-group-text"><i class="bi bi-lock-fill"></i></span><input type="password" class="form-control" id="np2" name="new_password_confirm" autocomplete="new-password" required></div>
                        <div class="form-text" id="matchHint">&nbsp;</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center py-3 gap-2 flex-wrap">
                <div class="form-check m-0"><input class="form-check-input" type="checkbox" id="showPw"><label class="form-check-label small" for="showPw">Show passwords</label></div>
                <button class="btn btn-dark px-4"><i class="bi bi-shield-check me-1"></i>Update password</button>
            </div>
        </form>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="kpi-icon tone-info"><i class="bi bi-clock-history"></i></span>
                    <div><h2>Recent activity</h2><p class="card-sub">Your last <?= count($activity) ?> actions</p></div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($activity): ?>
                    <ul class="timeline">
                        <?php foreach ($activity as $a): [$icon, $tone] = $activityIcon($a['action']); ?>
                            <li>
                                <span class="timeline-dot tone-<?= $tone ?>"><i class="bi <?= $icon ?>"></i></span>
                                <div class="timeline-body">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong><?= e(ucwords(str_replace('_', ' ', $a['action']))) ?></strong>
                                        <time class="cell-sub text-nowrap" datetime="<?= e($a['created_at']) ?>"><?= date('d M, h:i A', strtotime($a['created_at'])) ?></time>
                                    </div>
                                    <?php if ($a['details']): ?><div class="cell-sub text-break"><?= e($a['details']) ?></div><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state"><div class="empty-icon"><i class="bi bi-clock-history"></i></div><p class="mt-2 mb-0">No activity yet.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const np = document.getElementById('np'), np2 = document.getElementById('np2');
    const bars = document.querySelectorAll('.pw-meter span'), hint = document.getElementById('pwHint'), match = document.getElementById('matchHint');
    const labels = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'], colors = ['#C62828', '#C62828', '#D97706', '#15803D', '#15803D'];
    function score(p) {
        if (p.length < 8) return 0;
        let s = 1;
        if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
        if (/\d/.test(p)) s++;
        if (/[^A-Za-z0-9]/.test(p) || p.length >= 12) s++;
        return s;
    }
    function update() {
        const s = np.value ? score(np.value) : -1;
        bars.forEach((b, i) => { b.style.background = i < s ? colors[s] : ''; });
        hint.textContent = s < 0 ? 'Enter a new password.' : labels[s];
        hint.style.color = s < 0 ? '' : colors[s];
        if (!np2.value) { match.innerHTML = '&nbsp;'; return; }
        const ok = np.value === np2.value;
        match.textContent = ok ? '✓ Passwords match' : 'Passwords do not match';
        match.style.color = ok ? '#15803D' : '#C62828';
    }
    np.addEventListener('input', update); np2.addEventListener('input', update);
    document.getElementById('showPw').addEventListener('change', (e) => {
        document.querySelectorAll('#pwForm input[type=password], #pwForm input[data-pw]').forEach((i) => { i.dataset.pw = 1; i.type = e.target.checked ? 'text' : 'password'; });
    });
    document.getElementById('pwForm').addEventListener('submit', (e) => {
        if (np.value !== np2.value) { e.preventDefault(); np2.focus(); update(); }
    }, true);
})();
</script>
