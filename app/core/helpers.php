<?php
declare(strict_types=1);

/* ---------- Config, escaping, URLs ---------- */

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir, '/');
    }
    return $base;
}

function url(string $route = '', array $params = []): string
{
    $query = $route !== '' ? ['r' => $route] + $params : $params;
    return base_url() . '/index.php' . ($query ? '?' . http_build_query($query) : '');
}

function asset(string $path): string
{
    return base_url() . '/assets/' . ltrim($path, '/') . '?v=' . APP_VERSION;
}

function upload_url(?string $path): ?string
{
    return $path ? base_url() . '/uploads/' . ltrim($path, '/') : null;
}

/** Current query string with overrides applied (null removes a key). */
function query_with(array $overrides): string
{
    $q = array_merge($_GET, $overrides);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return base_url() . '/index.php?' . http_build_query($q);
}

/* ---------- Requests & responses ---------- */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key);
    return is_numeric($v) ? (int)$v : $default;
}

function input_float(string $key, float $default = 0.0): float
{
    $v = input($key);
    return is_numeric($v) ? (float)$v : $default;
}

/** Decoded JSON request body (for AJAX endpoints). */
function json_body(): array
{
    static $body = null;
    if ($body === null) {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        $body = is_array($decoded) ? $decoded : [];
    }
    return $body;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $route = '', array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function redirect_back(string $fallback = 'dashboard'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

/* ---------- Flash messages & old input ---------- */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function set_old(array $data): void
{
    unset($data['_csrf']);
    foreach (array_keys($data) as $k) {
        if (str_contains((string)$k, 'password')) {
            unset($data[$k]);
        }
    }
    $_SESSION['_old'] = $data;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* ---------- Security ---------- */

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function user(): ?array
{
    return Auth::user();
}

function has_role(string ...$roles): bool
{
    $u = Auth::user();
    return $u !== null && in_array($u['role'], $roles, true);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function audit(string $action, ?string $entity = null, ?int $entityId = null, string $details = ''): void
{
    Database::instance()->insert('audit_logs', [
        'user_id'   => Auth::id(),
        'action'    => $action,
        'entity'    => $entity,
        'entity_id' => $entityId,
        'details'   => mb_substr($details, 0, 500),
        'ip'        => client_ip(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/* ---------- Settings ---------- */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        try {
            $cache = [];
            foreach (Database::instance()->fetchAll('SELECT `key`, `value` FROM settings') as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (PDOException) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
}

function setting_save(array $pairs): void
{
    $db = Database::instance();
    foreach ($pairs as $k => $v) {
        $db->query('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$k, (string)$v]);
    }
    settings_all(true);
}

/* ---------- Formatting ---------- */

function money(mixed $amount, bool $withSymbol = true): string
{
    $decimals = (int)setting('currency_decimals', 2);
    $n = number_format((float)$amount, $decimals);
    if (!$withSymbol) {
        return $n;
    }
    $sym = (string)setting('currency_symbol', 'Rs');
    return setting('currency_position', 'before') === 'after' ? "$n $sym" : "$sym $n";
}

function num(mixed $n, int $decimals = 2): string
{
    return number_format((float)$n, $decimals);
}

/** Quantity without trailing zeros: 2.500 -> 2.5, 3.000 -> 3 */
function qty(mixed $n): string
{
    $s = number_format((float)$n, 3, '.', ',');
    return rtrim(rtrim($s, '0'), '.');
}

function fmt_date(?string $date): string
{
    return $date ? date(setting('date_format', 'd M Y'), strtotime($date)) : '—';
}

function fmt_datetime(?string $date): string
{
    return $date ? date(setting('date_format', 'd M Y') . ' h:i A', strtotime($date)) : '—';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i ?: '?';
}

function order_type_label(string $type): string
{
    return ['dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'][$type] ?? ucfirst($type);
}

function order_type_icon(string $type): string
{
    return ['dine_in' => 'bi-shop', 'takeaway' => 'bi-bag', 'delivery' => 'bi-scooter'][$type] ?? 'bi-receipt';
}

function payment_label(string $method): string
{
    return ['cash' => 'Cash', 'card' => 'Card', 'online' => 'Online / Wallet', 'bank' => 'Bank Transfer'][$method] ?? ucfirst($method);
}

function status_badge(string $status): string
{
    $map = [
        'completed'          => ['success', 'Completed'],
        'partially_refunded' => ['warning', 'Part. Refunded'],
        'refunded'           => ['danger', 'Refunded'],
        'open'               => ['success', 'Open'],
        'closed'             => ['secondary', 'Closed'],
        'paid'               => ['success', 'Paid'],
        'partial'            => ['warning', 'Partial'],
        'unpaid'             => ['danger', 'Unpaid'],
    ];
    [$cls, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge badge-soft-' . $cls . '">' . e($label) . '</span>';
}

function role_badge(string $role): string
{
    $cls = ['admin' => 'danger', 'manager' => 'primary', 'cashier' => 'success'][$role] ?? 'secondary';
    return '<span class="badge badge-soft-' . $cls . '">' . e(ucfirst($role)) . '</span>';
}

function selected(mixed $a, mixed $b): string
{
    return (string)$a === (string)$b ? 'selected' : '';
}

function checked(mixed $v): string
{
    return $v ? 'checked' : '';
}

/* ---------- Dates & pagination ---------- */

/**
 * Reads ?from=&to= (Y-m-d) from the query string. Returns [from, to, preset].
 */
function date_range(string $default = 'today'): array
{
    $preset = (string)($_GET['range'] ?? '');
    $from = (string)($_GET['from'] ?? '');
    $to = (string)($_GET['to'] ?? '');
    $valid = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

    if ($preset === '' && $valid($from) && $valid($to)) {
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to, 'custom'];
    }
    $preset = $preset ?: $default;
    $today = date('Y-m-d');
    return match ($preset) {
        'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), $preset],
        '7d'        => [date('Y-m-d', strtotime('-6 days')), $today, $preset],
        '30d'       => [date('Y-m-d', strtotime('-29 days')), $today, $preset],
        'month'     => [date('Y-m-01'), $today, $preset],
        'last_month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month')), $preset],
        'year'      => [date('Y-01-01'), $today, $preset],
        default     => [$today, $today, 'today'],
    };
}

function range_label(string $from, string $to): string
{
    return $from === $to ? fmt_date($from) : fmt_date($from) . ' – ' . fmt_date($to);
}

function paginate(int $total, int $perPage = 20): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    return ['page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

/* ---------- Files ---------- */

/**
 * Stores an uploaded raster image under uploads/$dir. Returns the relative path,
 * null when no file was sent, or throws RuntimeException with a user-facing message.
 */
function store_image_upload(?array $file, string $dir, int $maxBytes = 2097152): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('The image could not be uploaded.');
    }
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.');
    }
    $info = @getimagesize($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$info['mime'] ?? ''] ?? null;
    if (!$info || !$ext) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images are allowed.');
    }
    $target = UPLOAD_PATH . '/' . $dir;
    if (!is_dir($target)) {
        mkdir($target, 0755, true);
    }
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], "$target/$name")) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return "$dir/$name";
}

function delete_upload(?string $path): void
{
    if ($path && !str_contains($path, '..')) {
        $full = UPLOAD_PATH . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

/** Streams rows as a CSV download. */
function csv_download(string $filename, array $headers, iterable $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        // Neutralise spreadsheet formula injection
        $row = array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) && !is_numeric($v) ? "'" . $v : $v, array_values($row));
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
