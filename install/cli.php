<?php
declare(strict_types=1);
/**
 * Command-line installer.
 *   php install/cli.php --db-name=food_pos --db-user=root --db-pass= --admin-pass=secret123 [--no-sample] [--force]
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this script from the command line.\n");
}
require __DIR__ . '/lib.php';

$o = getopt('', ['db-host::', 'db-port::', 'db-name::', 'db-user::', 'db-pass::', 'restaurant::', 'admin-name::', 'admin-user::', 'admin-pass::', 'no-sample', 'force']);
if (is_file(ROOT_PATH . '/config/config.php') && !isset($o['force'])) {
    exit("Already installed (config/config.php exists). Use --force to reinstall.\n");
}
$adminPass = (string)($o['admin-pass'] ?? 'password123');
if (strlen($adminPass) < 8) {
    exit("Admin password must be at least 8 characters.\n");
}
$start = microtime(true);
echo "Installing Saffron POS…\n";
installer_run(
    ['host' => $o['db-host'] ?? '127.0.0.1', 'port' => (int)($o['db-port'] ?? 3306), 'name' => $o['db-name'] ?? 'food_pos', 'user' => $o['db-user'] ?? 'root', 'pass' => (string)($o['db-pass'] ?? '')],
    ['name' => $o['admin-name'] ?? 'Administrator', 'username' => $o['admin-user'] ?? 'admin', 'email' => '', 'password' => $adminPass],
    $o['restaurant'] ?? 'Saffron Fast Food',
    !isset($o['no-sample'])
);
printf("Done in %.1fs. Sign in as '%s'.\n", microtime(true) - $start, $o['admin-user'] ?? 'admin');
