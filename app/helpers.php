<?php
declare(strict_types=1);

/** HTML-escapes a value for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** App-relative URL. Deliberately relative, so it works on localhost and behind the preview proxy. */
function url(string $path = '/'): string
{
    return $path === '/' ? '/' : '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function json_out(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------------------------------------------------------- CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function request_csrf_token(): string
{
    return (string)($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
}

function csrf_ok(): bool
{
    return hash_equals(csrf_token(), request_csrf_token());
}

function require_csrf(): void
{
    if (!csrf_ok()) {
        json_out(['ok' => false, 'error' => 'Your session expired — reload the page and try again.'], 419);
    }
}

/* --------------------------------------------------------------- flash */

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

/* ------------------------------------------------------------ numbers */

function usd(float $amount): string
{
    return '$' . number_format($amount, 2);
}

/** Formats a crypto amount without trailing zero noise. */
function crypto_amount(float $amount): string
{
    $formatted = rtrim(rtrim(number_format($amount, 8, '.', ''), '0'), '.');

    return $formatted === '' ? '0' : $formatted;
}

function human_number(int|float $value): string
{
    if ($value >= 1000000) {
        return round($value / 1000000, 1) . 'M';
    }
    if ($value >= 1000) {
        return round($value / 1000, 1) . 'K';
    }

    return (string)(int)$value;
}

/** File size for download listings. */
function human_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }

    return $bytes . ' B';
}

function time_ago(string $datetime): string
{
    $seconds = time() - strtotime($datetime);

    if ($seconds < 60) {
        return 'just now';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ago';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . 'h ago';
    }
    if ($seconds < 2592000) {
        return floor($seconds / 86400) . 'd ago';
    }

    return date('M j, Y', strtotime($datetime));
}

/* --------------------------------------------------------------- icons */

/** Inline SVG icons (keeps the app dependency-free and offline-safe). */
function icon(string $name, int $size = 18): string
{
    $paths = [
        'download' => '<path d="M12 3v12m0 0l-4-4m4 4l4-4M4 19h16"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-5-6 5z"/>',
        'bolt'     => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
        'shield'   => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
        'wallet'   => '<path d="M3 7h14a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm0 0V6a2 2 0 012-2h10m2 9h2"/>',
        'chart'    => '<path d="M4 20V10m5 10V4m5 16v-7m5 7V8"/>',
        'check'    => '<path d="M4 12l5 5L20 6"/>',
        'copy'     => '<path d="M9 9V5a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2h-4M5 9h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2v-8a2 2 0 012-2z"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
        'arrow'    => '<path d="M5 12h14m-6-6l6 6-6 6"/>',
        'users'    => '<path d="M16 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2m13-9a4 4 0 010 0m3 9v-2a4 4 0 00-3-3.9M10 7a3 3 0 11-6 0 3 3 0 016 0zm9-1a3 3 0 11-3 3"/>',
        'cup'      => '<path d="M4 8h13v6a5 5 0 01-5 5H9a5 5 0 01-5-5V8zm13 1h2a3 3 0 010 6h-2M4 21h13"/>',
        'star'     => '<path d="M12 3l2.7 5.7 6.3.9-4.5 4.4 1 6.2-5.5-3-5.5 3 1-6.2L3 9.6l6.3-.9z"/>',
        'cube'     => '<path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M12 12l8-4.5M12 12v9M12 12L4 7.5"/>',
        'lock'     => '<path d="M6 11h12v9H6zM9 11V8a3 3 0 016 0v3"/>',
        'upload'   => '<path d="M12 16V4m0 0L8 8m4-4l4 4M4 19h16"/>',
    ];

    $body = $paths[$name] ?? '';

    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true">' . $body . '</svg>';
}

/** Decorative preview art for a catalog item, generated on the fly (see public/thumb.php). */
function thumb_url(string $seed, string $kind = 'shader', int $w = 640, int $h = 360): string
{
    return url('/thumb.php') . '?' . http_build_query([
        'seed' => $seed,
        'kind' => $kind,
        'w'    => $w,
        'h'    => $h,
    ]);
}
