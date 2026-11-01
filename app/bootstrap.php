<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('BACKUP_PATH', ROOT_PATH . '/backups');
define('APP_VERSION', '1.0.2');

if (!is_file(ROOT_PATH . '/config/config.php')) {
    header('Location: install/');
    exit;
}

$GLOBALS['__config'] = require ROOT_PATH . '/config/config.php';

$debug = (bool)($GLOBALS['__config']['app']['debug'] ?? false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

spl_autoload_register(function (string $class): void {
    foreach (['core', 'controllers', 'models'] as $dir) {
        $file = APP_PATH . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_PATH . '/core/helpers.php';

set_exception_handler(function (Throwable $e) use ($debug): void {
    error_log((string)$e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_ajax()) {
        echo json_encode(['ok' => false, 'message' => $debug ? $e->getMessage() : 'An unexpected error occurred.']);
        return;
    }
    echo '<div style="font-family:system-ui;max-width:640px;margin:60px auto;padding:24px;border:1px solid #eee;border-radius:12px">'
        . '<h2 style="margin-top:0">Something went wrong</h2><p>The request could not be completed. Please go back and try again.</p>'
        . ($debug ? '<pre style="white-space:pre-wrap;font-size:12px">' . e((string)$e) . '</pre>' : '')
        . '<a href="index.php">Return to dashboard</a></div>';
});

// Secure session
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name($GLOBALS['__config']['app']['session_name'] ?? 'SAFFRONPOS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

date_default_timezone_set(setting('timezone', 'Asia/Karachi') ?: 'UTC');
Database::instance()->syncTimezone();

