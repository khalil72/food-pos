<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

$installed = is_file(ROOT_PATH . '/config/config.php');
$error = null;
$done = false;
$v = [
    'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'food_pos', 'db_user' => 'root', 'db_pass' => '',
    'restaurant' => 'Saffron Fast Food', 'admin_name' => 'Administrator', 'admin_user' => 'admin', 'admin_email' => '', 'sample' => '1',
];

$checks = [
    'PHP 8.1 or newer' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    'mbstring extension' => extension_loaded('mbstring'),
    'GD / image support' => function_exists('getimagesize'),
    'config/ is writable' => is_writable(ROOT_PATH . '/config') || (!is_dir(ROOT_PATH . '/config') && is_writable(ROOT_PATH)),
    'uploads/ is writable' => is_writable(ROOT_PATH . '/uploads') || (!is_dir(ROOT_PATH . '/uploads') && is_writable(ROOT_PATH)),
];
$allOk = !in_array(false, $checks, true);

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ($k === 'sample' ? '0' : '')));
    }
    $pass = (string)($_POST['admin_pass'] ?? '');
    if (!$allOk) {
        $error = 'Please fix the server requirements first.';
    } elseif ($v['restaurant'] === '' || $v['admin_name'] === '' || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $v['admin_user'])) {
        $error = 'Enter a restaurant name, your name and a username (3+ letters/numbers).';
    } elseif (strlen($pass) < 8) {
        $error = 'The admin password must be at least 8 characters.';
    } elseif ($pass !== ($_POST['admin_pass2'] ?? '')) {
        $error = 'The passwords do not match.';
    } else {
        try {
            installer_run(
                ['host' => $v['db_host'], 'port' => (int)$v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => (string)($_POST['db_pass'] ?? '')],
                ['name' => $v['admin_name'], 'username' => $v['admin_user'], 'email' => $v['admin_email'], 'password' => $pass],
                $v['restaurant'],
                $v['sample'] === '1'
            );
            $done = true;
        } catch (Throwable $e) {
            @unlink(ROOT_PATH . '/config/config.php');
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · Saffron POS</title>
<link rel="stylesheet" href="../assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/app.css">
<style>.install-wrap{max-width:760px;margin:0 auto;padding:40px 16px}.check{display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px dashed var(--border)}</style>
</head>
<body>
<div class="install-wrap">
    <div class="d-flex align-items-center gap-3 mb-4">
        <span class="brand-mark"><i class="bi bi-fire"></i></span>
        <div><h1 class="h3 fw-bold mb-0">Saffron POS setup</h1><p class="text-muted mb-0">Fast food POS &amp; restaurant management</p></div>
    </div>

    <?php if ($done): ?>
        <div class="card"><div class="card-body p-4 text-center">
            <div class="empty-icon mb-3" style="background:var(--success-bg);color:var(--success)"><i class="bi bi-check2-circle"></i></div>
            <h2 class="h4 fw-bold">Installation complete</h2>
            <p class="text-muted">Sign in as <strong><?= $h($v['admin_user']) ?></strong> with the password you chose.<?= $v['sample'] === '1' ? ' Demo accounts <code>manager</code>, <code>cashier</code> and <code>hina</code> use the password <code>password123</code>.' : '' ?></p>
            <p class="small text-danger">For security, delete or rename the <code>install</code> folder on a live server.</p>
            <a href="../index.php" class="btn btn-primary btn-lg">Open Saffron POS</a>
        </div></div>
    <?php elseif ($installed): ?>
        <div class="card"><div class="card-body p-4">
            <h2 class="h5 fw-bold">Already installed</h2>
            <p class="text-muted mb-3">A configuration file already exists. To reinstall, delete <code>config/config.php</code> first (this will not delete your database unless you reuse its name).</p>
            <a href="../index.php" class="btn btn-primary">Go to the app</a>
        </div></div>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
        <div class="card mb-3"><div class="card-header"><h2>1. Server requirements</h2></div><div class="card-body py-2">
            <?php foreach ($checks as $label => $ok): ?>
                <div class="check"><span><?= $h($label) ?></span><?= $ok ? '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> OK</span>' : '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill"></i> Missing</span>' ?></div>
            <?php endforeach; ?>
        </div></div>
        <form method="post" class="card" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<span class=\'spinner-border spinner-border-sm me-2\'></span>Installing… this can take a minute';">
            <div class="card-header"><h2>2. Database &amp; administrator</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">MySQL host</label><input class="form-control" name="db_host" value="<?= $h($v['db_host']) ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Port</label><input class="form-control" name="db_port" value="<?= $h($v['db_port']) ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Database name</label><input class="form-control" name="db_name" value="<?= $h($v['db_name']) ?>" pattern="[A-Za-z0-9_]+" required><div class="form-text">Created if it does not exist. <strong>Existing tables are replaced.</strong></div></div>
                    <div class="col-md-4"><label class="form-label">DB username</label><input class="form-control" name="db_user" value="<?= $h($v['db_user']) ?>" required></div>
                    <div class="col-md-4"><label class="form-label">DB password</label><input type="password" class="form-control" name="db_pass" value=""></div>
                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-md-6"><label class="form-label">Restaurant name</label><input class="form-control" name="restaurant" value="<?= $h($v['restaurant']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Your name</label><input class="form-control" name="admin_name" value="<?= $h($v['admin_name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Admin username</label><input class="form-control" name="admin_user" value="<?= $h($v['admin_user']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Admin email</label><input type="email" class="form-control" name="admin_email" value="<?= $h($v['admin_email']) ?>"></div>
                    <div class="col-md-6"><label class="form-label">Admin password</label><input type="password" class="form-control" name="admin_pass" minlength="8" required></div>
                    <div class="col-md-6"><label class="form-label">Confirm password</label><input type="password" class="form-control" name="admin_pass2" minlength="8" required></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sample" value="1" id="sample" <?= $v['sample'] === '1' ? 'checked' : '' ?>><label class="form-check-label" for="sample">Load sample menu, customers, staff and 30 days of sales (recommended for evaluation)</label></div></div>
                </div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary btn-lg" <?= $allOk ? '' : 'disabled' ?>>Install now</button></div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
