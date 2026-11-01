<?php $logo = upload_url(setting('logo')); $restaurant = setting('restaurant_name', 'Saffron POS'); ?>
<div class="auth-wrap">
    <section class="auth-hero" aria-hidden="true">
        <div class="d-flex align-items-center gap-2 position-relative">
            <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" class="brand-logo"><?php else: ?><span class="brand-mark"><i class="bi bi-fire"></i></span><?php endif; ?>
            <strong class="font-display fs-5"><?= e($restaurant) ?></strong>
        </div>

        <div class="auth-showcase">
            <div class="food-orbit">
                <span class="food-tile t1">🍔</span>
                <span class="food-tile t2">🍕</span>
                <span class="food-tile t3">🍟</span>
                <span class="food-tile t4">🥤</span>
                <span class="food-tile t5">🍗</span>
                <div class="orbit-card">
                    <small>Today’s sales</small>
                    <strong>Live</strong>
                    <span><i class="bi bi-graph-up-arrow"></i> Real-time dashboard</span>
                </div>
            </div>
        </div>

        <div class="position-relative">
            <h2 class="mb-3">Serve faster.<br>Know your numbers.</h2>
            <p class="mb-4">Dine-in, takeaway and delivery orders in a few taps, with stock, expenses and profit always up to date.</p>
            <div class="auth-features">
                <div class="auth-feature"><i class="bi bi-lightning-charge"></i> Quick checkout</div>
                <div class="auth-feature"><i class="bi bi-printer"></i> Thermal receipts</div>
                <div class="auth-feature"><i class="bi bi-pie-chart"></i> Profit reports</div>
            </div>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="mb-4">
                <div class="d-lg-none mb-3">
                    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" class="brand-logo"><?php else: ?><span class="brand-mark"><i class="bi bi-fire"></i></span><?php endif; ?>
                </div>
                <span class="auth-eyebrow"><span class="dot"></span><?= e($restaurant) ?></span>
                <h1 class="auth-title">Welcome back</h1>
                <p class="text-muted mb-0">Sign in to your account to start your shift.</p>
            </div>

            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> d-flex gap-2 align-items-start py-2 small" role="alert">
                    <i class="bi <?= $f['type'] === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle' ?>"></i><span><?= e($f['message']) ?></span>
                </div>
            <?php endforeach; ?>

            <form method="post" action="<?= url('login') ?>" class="auth-form" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <div class="auth-input">
                        <i class="bi bi-person"></i>
                        <input type="text" class="form-control" id="username" name="username" value="<?= e(old('username')) ?>" placeholder="Enter your username" autocomplete="username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="auth-input">
                        <i class="bi bi-lock"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button class="auth-eye" type="button" id="togglePass" aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="auth-caps small mt-2" id="capsWarn" hidden><i class="bi bi-capslock"></i> Caps Lock is on</div>
                </div>
                <button class="btn btn-primary w-100 auth-submit" type="submit">Sign in <i class="bi bi-arrow-right ms-1"></i></button>
            </form>

            <p class="auth-foot"><i class="bi bi-shield-lock"></i> Secure sign-in · Forgot your password? Ask your administrator.</p>
        </div>
    </section>
</div>
<script>
(function () {
    const pass = document.getElementById('password');
    const btn = document.getElementById('togglePass');
    btn.addEventListener('click', () => {
        const show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        btn.innerHTML = `<i class="bi ${show ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        pass.focus();
    });
    const caps = document.getElementById('capsWarn');
    ['keyup', 'keydown'].forEach((ev) => pass.addEventListener(ev, (e) => { caps.hidden = !(e.getModifierState && e.getModifierState('CapsLock')); }));
    const form = document.querySelector('.auth-form');
    form.addEventListener('submit', (e) => {
        const u = document.getElementById('username');
        if (!u.value.trim()) { e.preventDefault(); u.focus(); u.classList.add('is-invalid'); return; }
        if (!pass.value) { e.preventDefault(); pass.focus(); pass.classList.add('is-invalid'); return; }
        const b = form.querySelector('.auth-submit');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in…';
    });
    form.addEventListener('input', (e) => e.target.classList.remove('is-invalid'));
})();
</script>
