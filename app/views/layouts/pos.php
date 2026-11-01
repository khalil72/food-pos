<?php $u = user(); $restaurant = setting('restaurant_name', 'Saffron POS'); $logo = upload_url(setting('logo')); ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e('POS · ' . $restaurant) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/pos.css') ?>">
</head>
<body class="pos-body">
<header class="pos-top">
    <a href="<?= url(Auth::homeRoute() === 'pos' ? 'orders' : 'dashboard') ?>" class="pos-brand" title="Back to back office">
        <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" class="brand-logo" style="width:34px;height:34px"><?php else: ?><span class="brand-mark" style="width:34px;height:34px;font-size:1rem"><i class="bi bi-fire"></i></span><?php endif; ?>
        <span class="d-none d-md-inline"><?= e($restaurant) ?></span>
    </a>
    <nav class="pos-top-actions">
        <button class="pos-top-btn" data-action="recent" title="Today's orders"><i class="bi bi-clock-history"></i><span class="d-none d-lg-inline">Orders</span></button>
        <button class="pos-top-btn" data-action="held" title="Held orders (F8 to hold)"><i class="bi bi-pause-circle"></i><span class="d-none d-lg-inline">Held</span><span class="pos-count" id="heldCount" hidden>0</span></button>
        <a class="pos-top-btn" href="<?= url('register') ?>" title="Cash register"><i class="bi bi-cash-coin"></i><span class="d-none d-lg-inline">Register</span></a>
        <?php if (has_role('admin', 'manager')): ?>
            <a class="pos-top-btn" href="<?= url('dashboard') ?>" title="Dashboard"><i class="bi bi-grid-1x2"></i><span class="d-none d-lg-inline">Dashboard</span></a>
        <?php else: ?>
            <a class="pos-top-btn" href="<?= url('orders') ?>" title="Orders"><i class="bi bi-receipt"></i><span class="d-none d-lg-inline">History</span></a>
        <?php endif; ?>
        <button class="pos-top-btn d-none d-md-inline-flex" data-action="fullscreen" title="Full screen"><i class="bi bi-arrows-fullscreen"></i></button>
        <div class="dropdown">
            <button class="pos-top-btn" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar" style="width:28px;height:28px;font-size:.7rem"><?= e(initials($u['name'])) ?></span><span class="d-none d-lg-inline"><?= e(explode(' ', $u['name'])[0]) ?></span></button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted"><?= e($u['name']) ?> · <?= e(ucfirst($u['role'])) ?></span></li>
                <li><a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="<?= url('customers') ?>"><i class="bi bi-people me-2"></i>Customers</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button></form></li>
            </ul>
        </div>
    </nav>
</header>
<?= $content ?>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts" style="z-index:2000"></div>
<?php View::partial('confirm_modal'); ?>
<script>window.APP = <?= json_encode([
    'base' => base_url(),
    'csrf' => csrf_token(),
    'currency' => setting('currency_symbol', 'Rs'),
    'currencyPos' => setting('currency_position', 'before'),
    'decimals' => (int)setting('currency_decimals', 2),
    'flashes' => flashes(),
], JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/pos.js') ?>"></script>
</body>
</html>
