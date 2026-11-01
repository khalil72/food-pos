<?php
$u = user();
$route = (string)($_GET['r'] ?? '');
$section = explode('/', $route)[0];
$restaurant = setting('restaurant_name', 'Saffron POS');
$logo = upload_url(setting('logo'));
$openRegister = $u ? Register::currentFor((int)$u['id']) : null;

$nav = [
    'Overview' => [
        ['dashboard', 'Dashboard', 'bi-grid-1x2', ['admin', 'manager']],
        ['pos', 'Point of Sale', 'bi-cart3', ['admin', 'manager', 'cashier']],
    ],
    'Sales' => [
        ['orders', 'Orders', 'bi-receipt', ['admin', 'manager', 'cashier']],
        ['customers', 'Customers', 'bi-people', ['admin', 'manager', 'cashier']],
        ['register', 'Cash Register', 'bi-cash-coin', ['admin', 'manager', 'cashier']],
    ],
    'Menu' => [
        ['products', 'Products', 'bi-box-seam', ['admin', 'manager']],
        ['categories', 'Categories', 'bi-tags', ['admin', 'manager']],
        ['addons', 'Add-ons', 'bi-plus-square', ['admin', 'manager']],
    ],
    'Inventory' => [
        ['inventory', 'Stock & Ingredients', 'bi-basket', ['admin', 'manager']],
        ['purchases', 'Purchases', 'bi-truck', ['admin', 'manager']],
        ['suppliers', 'Suppliers', 'bi-building', ['admin', 'manager']],
    ],
    'Finance' => [
        ['expenses', 'Expenses', 'bi-wallet2', ['admin', 'manager']],
        ['reports', 'Reports', 'bi-bar-chart-line', ['admin', 'manager']],
    ],
    'Administration' => [
        ['users', 'Users & Roles', 'bi-person-badge', ['admin']],
        ['settings', 'Settings', 'bi-gear', ['admin']],
        ['audit', 'Audit Log', 'bi-shield-check', ['admin']],
        ['backup', 'Backup & Restore', 'bi-database', ['admin']],
    ],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(($title ?? 'Dashboard') . ' · ' . $restaurant) ?></title>
    <link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <?php foreach ($styles ?? [] as $s): ?><link rel="stylesheet" href="<?= asset($s) ?>"><?php endforeach; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <a href="<?= url(Auth::homeRoute()) ?>" class="brand">
            <?php if ($logo): ?>
                <img src="<?= e($logo) ?>" alt="" class="brand-logo">
            <?php else: ?>
                <span class="brand-mark"><i class="bi bi-fire"></i></span>
            <?php endif; ?>
            <span class="brand-text">
                <strong><?= e($restaurant) ?></strong>
                <small>Restaurant POS</small>
            </span>
        </a>
        <nav class="sidebar-nav">
            <?php foreach ($nav as $group => $items):
                $visible = array_filter($items, fn($i) => has_role(...$i[3]));
                if (!$visible) continue; ?>
                <div class="nav-group-label"><?= e($group) ?></div>
                <?php foreach ($visible as [$r, $label, $icon]): ?>
                    <a href="<?= url($r) ?>" class="nav-link-item <?= $section === $r ? 'active' : '' ?>" <?= $section === $r ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= $icon ?>"></i><span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <span class="small">v<?= APP_VERSION ?></span>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-icon d-lg-none" type="button" data-sidebar-toggle aria-label="Open menu"><i class="bi bi-list"></i></button>
            <div class="topbar-title">
                <h1><?= e($title ?? 'Dashboard') ?></h1>
                <?php if (!empty($subtitle)): ?><p><?= e($subtitle) ?></p><?php endif; ?>
            </div>
            <div class="topbar-actions">
                <a href="<?= url('register') ?>" class="register-pill <?= $openRegister ? 'is-open' : 'is-closed' ?> d-none d-md-inline-flex" title="Cash register">
                    <span class="dot"></span><?= $openRegister ? 'Register open' : 'Register closed' ?>
                </a>
                <a href="<?= url('pos') ?>" class="btn btn-primary btn-sm d-none d-sm-inline-flex align-items-center gap-1"><i class="bi bi-plus-lg"></i> New Sale</a>
                <div class="dropdown">
                    <button class="user-chip" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= e(initials($u['name'])) ?></span>
                        <span class="d-none d-md-inline text-start">
                            <strong><?= e($u['name']) ?></strong>
                            <small><?= e(ucfirst($u['role'])) ?></small>
                        </span>
                        <i class="bi bi-chevron-down small d-none d-md-inline"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="<?= url('profile') ?>"><i class="bi bi-person me-2"></i>My profile</a></li>
                        <li><a class="dropdown-item" href="<?= url('register') ?>"><i class="bi bi-cash-coin me-2"></i>Cash register</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= url('logout') ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content" id="main-content">
            <?= $content ?>
        </main>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="toasts"></div>
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
<?php foreach ($scripts ?? [] as $s): ?><script src="<?= asset($s) ?>"></script><?php endforeach; ?>
</body>
</html>
