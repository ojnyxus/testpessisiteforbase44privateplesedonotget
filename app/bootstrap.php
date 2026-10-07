<?php
declare(strict_types=1);

if (!function_exists('env_value')) {
    /** Reads an environment variable, treating empty strings as "not set". */
    function env_value(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        return ($value === false || $value === '') ? $default : $value;
    }
}

$GLOBALS['app_config'] = require __DIR__ . '/config.php';

/** Dotted config access: config('db.host'). */
function config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'];

    if ($key === null) {
        return $config;
    }

    foreach (explode('.', $key) as $segment) {
        if (!is_array($config) || !array_key_exists($segment, $config)) {
            return $default;
        }
        $config = $config[$segment];
    }

    return $config;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/sources.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/views/partials.php';

if (PHP_SAPI !== 'cli') {
    auth_start_session();
}
