<?php
declare(strict_types=1);

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    private static ?array $user = null;
    private static bool $loaded = false;

    /** Returns null on success or an error message. */
    public static function attempt(string $username, string $password): ?string
    {
        $db = Database::instance();
        $failures = (int)$db->value(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'login_failed' AND ip = ? AND created_at > ?",
            [client_ip(), date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60)]
        );
        if ($failures >= self::MAX_ATTEMPTS) {
            return 'Too many failed attempts. Please wait ' . self::LOCK_MINUTES . ' minutes and try again.';
        }

        $user = $db->fetch('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            audit('login_failed', 'user', $user['id'] ?? null, 'Username: ' . $username);
            return 'Invalid username or password.';
        }
        if (!(int)$user['is_active']) {
            return 'This account has been disabled. Contact an administrator.';
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['_csrf']);
        $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
        self::$loaded = false;
        audit('login', 'user', (int)$user['id']);
        return null;
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            self::$user = null;
            if (!empty($_SESSION['uid'])) {
                $idle = (int)config('app.session_idle', 7200);
                if (time() - (int)($_SESSION['last_activity'] ?? 0) > $idle) {
                    self::logout();
                    flash('warning', 'Your session expired due to inactivity. Please sign in again.');
                    return null;
                }
                $_SESSION['last_activity'] = time();
                $u = Database::instance()->fetch(
                    'SELECT id, name, username, email, phone, role, is_active FROM users WHERE id = ?',
                    [$_SESSION['uid']]
                );
                if ($u && (int)$u['is_active']) {
                    self::$user = $u;
                } else {
                    self::logout();
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int)self::user()['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        session_start();
        self::$user = null;
        self::$loaded = true;
    }

    public static function homeRoute(): string
    {
        return has_role('cashier') ? 'pos' : 'dashboard';
    }
}
