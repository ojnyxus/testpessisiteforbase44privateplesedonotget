<?php
declare(strict_types=1);

/**
 * Configuration, read from environment variables.
 *
 * Local (non-secret) defaults come from .env.base44-defaults, wired in through
 * docker-compose.base44.yml. The app owner's crypto wallet addresses and coin
 * rates are NOT environment values: they live in the `settings` table and are
 * edited from the admin panel (Admin -> Settings).
 */
return [
    'app_name' => env_value('APP_NAME', 'BlockForge Hub'),
    'tagline'  => env_value('APP_TAGLINE', 'Modpacks, shaders and server configs for Minecraft'),
    'debug'    => env_value('APP_DEBUG', '1') === '1',

    // Exact match on "1": the platform sets BASE44_PREVIEW_MODE=1 only in the
    // sandbox preview. Anything else (unset, "0", "") keeps stock behaviour.
    'preview_mode' => env_value('BASE44_PREVIEW_MODE') === '1',

    // Download sources. Modrinth is an open API; CurseForge needs an API key,
    // delivered by the platform to /run/base44/app.env. With no key the
    // CurseForge half of the admin screen simply reports that it is unavailable.
    'modrinth_agent' => env_value('MODRINTH_USER_AGENT', 'BlockForge-Hub/1.0 (self-hosted Minecraft directory)'),
    'curseforge_key' => env_value('CURSEFORGE_API_KEY', ''),

    'db' => [
        'host' => env_value('DB_HOST', 'db'),
        'port' => env_value('DB_PORT', '3306'),
        'name' => env_value('DB_NAME', 'mc_hub'),
        'user' => env_value('DB_USER', 'mc'),
        'pass' => env_value('DB_PASS', 'mc_hub_pw'),
    ],
];
