<?php
declare(strict_types=1);

/**
 * Session + user authentication.
 *
 * Two ways in, both first-party (no external identity provider):
 *   - email + password (min 8 chars, hashed with password_hash)
 *   - anonymous "wallet" login: a public wallet address is the identity, no password
 */
function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => is_https_request(),
        'path'     => '/',
    ]);
    session_start();
}

/** Detects HTTPS, including the sandbox preview proxy that terminates TLS in front of Apache. */
function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (config('preview_mode')) {
        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    return false;
}

function current_user(): ?array
{
    static $cached = false;
    static $user = null;

    if ($cached) {
        return $user;
    }
    $cached = true;

    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id > 0) {
        $user = db_one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($user !== null) {
            db_run('UPDATE users SET last_seen_at = NOW() WHERE id = ?', [$id]);
        }
    }

    return $user;
}

function is_admin(): bool
{
    $user = current_user();

    return $user !== null && $user['role'] === 'admin';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('Sign in to continue.', 'warning');
        redirect('/login');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        page_layout('', 'Owners only', render_fragment(__DIR__ . '/pages/forbidden.php'));
        exit;
    }

    return $user;
}

/** The first account to register owns the hub — no pre-seeded admin credentials. */
function register_user(array $input): array
{
    $email = trim(strtolower((string)($input['email'] ?? '')));
    $name  = trim((string)($input['name'] ?? ''));
    $wallet = trim((string)($input['wallet_address'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($wallet !== '') {
        if (!preg_match('/^[A-Za-z0-9:_-]{20,120}$/', $wallet)) {
            return ['error' => 'That does not look like a wallet address.'];
        }
        if (db_one('SELECT id FROM users WHERE wallet_address = ?', [$wallet]) !== null) {
            return ['error' => 'That wallet already has an account — sign in instead.'];
        }
        $name = $name !== '' ? $name : anonymous_alias($wallet);
        $role = db_value('SELECT COUNT(*) FROM users') == 0 ? 'admin' : 'user';
        db_run(
            'INSERT INTO users (name, wallet_address, role, created_at, last_seen_at) VALUES (?, ?, ?, NOW(), NOW())',
            [$name, $wallet, $role]
        );

        return ['user_id' => (int)db()->lastInsertId()];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'Enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['error' => 'Password must be at least 8 characters.'];
    }
    if (db_one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
        return ['error' => 'That email is already registered — sign in instead.'];
    }
    if ($name === '') {
        $name = anonymous_alias($email);
    }

    $role = db_value('SELECT COUNT(*) FROM users') == 0 ? 'admin' : 'user';
    db_run(
        'INSERT INTO users (name, email, password_hash, role, created_at, last_seen_at) VALUES (?, ?, ?, ?, NOW(), NOW())',
        [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]
    );

    return ['user_id' => (int)db()->lastInsertId()];
}

function attempt_login(array $input): array
{
    $email = trim(strtolower((string)($input['email'] ?? '')));
    $wallet = trim((string)($input['wallet_address'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($wallet !== '') {
        $user = db_one('SELECT * FROM users WHERE wallet_address = ?', [$wallet]);
        if ($user === null) {
            return ['error' => 'No account for that wallet address yet — create one in a second.'];
        }
        auth_login($user);

        return [];
    }

    $user = db_one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($user === null || $user['password_hash'] === null || !password_verify($password, $user['password_hash'])) {
        return ['error' => 'Wrong email or password.'];
    }

    auth_login($user);

    return [];
}

function auth_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    db_run('UPDATE users SET last_seen_at = NOW() WHERE id = ?', [(int)$user['id']]);
}

function auth_logout(): void
{
    $_SESSION = [];
    session_destroy();
}

/** Deterministic pseudonym so anonymous accounts still feel like players. */
function anonymous_alias(string $seed): string
{
    $adjectives = ['Ember', 'Mossy', 'Copper', 'Frost', 'Silent', 'Blazing', 'Deep', 'Lucky', 'Quantum', 'Nether'];
    $nouns = ['Creeper', 'Warden', 'Golem', 'Axolotl', 'Builder', 'Admin', 'Rogue', 'Squid', 'Panda', 'Farmer'];
    $hash = crc32($seed);

    return $adjectives[$hash % count($adjectives)] . ' ' . $nouns[intdiv($hash, 7) % count($nouns)]
        . ' #' . substr(dechex($hash), 0, 3);
}
