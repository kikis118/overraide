<?php
declare(strict_types=1);

// Single-admin PIN login. The PIN is stored only as a password hash in app/config.php
// (created by `php app/setup.php`). Failed attempts are rate-limited per IP and globally.

const SESSION_DAYS = 14;
const MAX_FAILS = 5;
const LOCK_SECONDS = 900;

function auth_boot(bool $force = false): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $name = 'overraide_admin';
    if (!$force && !isset($_COOKIE[$name])) return; // visitors never get a session
    $dir = APP_DIR . '/data/sessions';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    session_name($name);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)(SESSION_DAYS * 86400));
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'lifetime' => SESSION_DAYS * 86400,
        'path' => ($GLOBALS['BASE'] ?? '') . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function is_admin(): bool
{
    auth_boot();
    return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin']);
}

function csrf_token(): string
{
    auth_boot(true);
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_ok(): bool
{
    auth_boot();
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['csrf']) && is_string($sent) && hash_equals($_SESSION['csrf'], $sent);
}

function attempts_file(): string { return APP_DIR . '/data/attempts.json'; }

function attempts_load(): array
{
    $f = attempts_file();
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($d) ? $d : [];
}

function attempts_save(array $d): void
{
    $dir = dirname(attempts_file());
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    file_put_contents(attempts_file(), json_encode($d), LOCK_EX);
}

// returns [ok, message]
function login_with_pin(string $pin): array
{
    $hash = config('pin_hash');
    if (!$hash) return [false, 'No PIN is set yet. Run "php app/setup.php" on the server first.'];

    $now = time();
    $ip = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string)$hash);
    $all = attempts_load();
    $keys = [$ip, '*'];
    foreach ($keys as $k) {
        if (($all[$k]['locked_until'] ?? 0) > $now) {
            $mins = (int)ceil(($all[$k]['locked_until'] - $now) / 60);
            return [false, "Too many wrong PINs. Try again in $mins minute" . ($mins === 1 ? '' : 's') . '.'];
        }
    }

    if (password_verify($pin, (string)$hash)) {
        unset($all[$ip]);
        attempts_save($all);
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return [true, ''];
    }

    foreach ([[$ip, MAX_FAILS], ['*', MAX_FAILS * 6]] as [$k, $limit]) {
        $row = $all[$k] ?? ['fails' => 0, 'since' => $now];
        if ($now - ($row['since'] ?? $now) > 3600) $row = ['fails' => 0, 'since' => $now];
        $row['fails']++;
        if ($row['fails'] >= $limit) { $row['locked_until'] = $now + LOCK_SECONDS; $row['fails'] = 0; $row['since'] = $now; }
        $all[$k] = $row;
    }
    attempts_save($all);
    usleep(400000);
    return [false, 'Wrong PIN.'];
}

function logout(): void
{
    auth_boot();
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
}
