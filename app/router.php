<?php
declare(strict_types=1);

/**
 * Tiny front controller. Apache's FallbackResource sends every non-file request here
 * (see the Dockerfile), so /library, /asset/{slug} and /api/* never need real files.
 *
 * Page files output a content fragment and may set $page_nav / $page_title; the
 * dispatcher wraps them in the shared layout. A page that streams its own response
 * (a download) sets $page_layout = false.
 */
function routes(): array
{
    return [
        ['GET',  '/',                 'pages/home.php'],
        ['GET',  '/library',          'pages/library.php'],
        ['GET',  '/marketplace',      'pages/marketplace.php'],
        ['GET',  '/asset/{slug}',     'pages/asset.php'],
        ['GET',  '/download/{id}',    'pages/download.php'],
        ['GET',  '/pay/{id}',         'pages/pay.php'],
        ['GET',  '/tip',              'pages/tip.php'],
        ['GET',  '/login',            'pages/login.php'],
        ['POST', '/login',            'pages/login.php'],
        ['GET',  '/register',         'pages/register.php'],
        ['POST', '/register',         'pages/register.php'],
        ['GET',  '/logout',           'pages/logout.php'],
        ['GET',  '/dashboard',        'pages/dashboard.php'],
        ['GET',  '/admin',            'pages/admin.php'],
        ['GET',  '/admin/payments',   'pages/admin_payments.php'],
        ['GET',  '/admin/settings',   'pages/admin_settings.php'],
        ['GET',  '/admin/sources',    'pages/admin_sources.php'],

        ['POST', '/pay/create',       'pages/pay_create.php'],
        ['POST', '/pay/submit',       'pages/pay_submit.php'],
        ['POST', '/pay/review',       'pages/pay_review.php'],
        ['POST', '/admin/settings',   'pages/admin_settings_save.php'],
        ['POST', '/admin/sources',    'pages/admin_sources_save.php'],

        ['GET',  '/api/assets',       'api/assets.php'],
        ['GET',  '/api/pay/status',   'api/pay_status.php'],
        ['POST', '/api/bookmark',     'api/bookmark.php'],
    ];
}

function dispatch(string $method, string $path): void
{
    $path = '/' . trim($path, '/');
    if ($path === '/') {
        $path = '/';
    }

    $match = null;
    $params = [];

    foreach (routes() as [$routeMethod, $pattern, $file]) {
        if ($routeMethod !== $method) {
            continue;
        }
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (preg_match($regex, $path, $matches) === 1) {
            $match = $file;
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            break;
        }
    }

    if ($match === null) {
        http_response_code(404);
        $content = render_fragment(__DIR__ . '/pages/not_found.php', ['requested' => $path]);
        page_layout('', 'Page not found', $content);
        return;
    }

    if (str_starts_with($match, 'api/')) {
        require __DIR__ . '/' . $match;
        return;
    }

    $page_layout = true;
    $page_nav = '';
    $page_title = (string)config('app_name');

    ob_start();
    require __DIR__ . '/' . $match;
    $content = (string)ob_get_clean();

    if ($page_layout) {
        page_layout($page_nav, (string)$page_title, $content);
    } else {
        echo $content;
    }
}

/** Runs a view with extracted data and returns its HTML. */
function render_fragment(string $view, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    include $view;

    return (string)ob_get_clean();
}

/** Renders a full page (layout + content) and sends it. */
function page_layout(string $nav, string $title, string $content): void
{
    $user = current_user();
    $flashes = take_flashes();
    $siteName = setting('site_name', (string)config('app_name'));

    include __DIR__ . '/views/layout.php';
}

/** Renders the styled 404 page from anywhere, then stops the page. */
function not_found(string $path): void
{
    http_response_code(404);
    echo render_fragment(__DIR__ . '/pages/not_found.php', ['requested' => $path]);
}
