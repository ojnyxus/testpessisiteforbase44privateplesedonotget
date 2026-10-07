<?php
declare(strict_types=1);

/**
 * Real download sources: Modrinth and CurseForge.
 *
 * The hub does not mirror jars and archives. Instead an asset is *linked* to a
 * project on either platform (assets.source + source_project_*), and
 * /download/{id} resolves the real file and redirects the buyer to it:
 *
 *   Modrinth   — open API, no credentials. Version metadata already carries the
 *                CDN url, so it is cached for a few hours (SOURCE_URL_TTL).
 *   CurseForge — needs an API key (CURSEFORGE_API_KEY); its download links are
 *                signed and short-lived, so they are resolved per download.
 *
 * A link without a pinned version follows the newest file that matches the
 * asset's Minecraft version (plus a loader when the category implies one) and
 * falls back to the newest file overall, so a link never silently goes dead.
 */

const SOURCE_LABELS = [
    'modrinth'   => 'Modrinth',
    'curseforge' => 'CurseForge',
];

const MODRINTH_API = 'https://api.modrinth.com/v2';
const CURSEFORGE_API = 'https://api.curseforge.com/v1';
const CURSEFORGE_MINECRAFT = 432;

/** How long a resolved direct file url is reused before asking the API again. */
const SOURCE_URL_TTL = 21600;         // 6 hours — Modrinth CDN links are stable
const SOURCE_URL_TTL_SIGNED = 900;    // 15 minutes — CurseForge links are signed

/* ------------------------------------------------------------------ config */

function curseforge_api_key(): string
{
    return (string)(config('curseforge_key') ?? '');
}

function curseforge_available(): bool
{
    return curseforge_api_key() !== '';
}

/** Modrinth asks for a user agent that identifies the app. */
function source_user_agent(): string
{
    return (string)(config('modrinth_agent') ?? 'BlockForge-Hub/1.0');
}

function source_label(string $source): string
{
    return SOURCE_LABELS[$source] ?? ucfirst($source);
}

function source_known(string $source): bool
{
    return isset(SOURCE_LABELS[$source]);
}

/* -------------------------------------------------------------------- http */

/**
 * GETs a URL and decodes the JSON body. Never throws: a failure comes back as
 * ['ok' => false, 'error' => '…'] so the admin UI can show what went wrong.
 */
function http_json(string $url, array $headers = [], int $timeout = 15): array
{
    if (!ini_get('allow_url_fopen')) {
        return ['ok' => false, 'status' => 0, 'error' => 'This PHP build blocks outbound HTTP requests.'];
    }

    $context = stream_context_create(['http' => [
        'method'        => 'GET',
        'header'        => implode("\r\n", $headers),
        'timeout'       => $timeout,
        'ignore_errors' => true,
    ]]);

    $body = @file_get_contents($url, false, $context);

    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
            $status = (int)$match[1];
        }
    }

    $host = (string)parse_url($url, PHP_URL_HOST);
    if ($body === false) {
        return ['ok' => false, 'status' => $status, 'error' => 'Could not reach ' . $host . '.'];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'error' => $host . ' answered with HTTP ' . $status . ' and no JSON.'];
    }

    if ($status >= 400) {
        $message = (string)($decoded['description'] ?? $decoded['error'] ?? ('HTTP ' . $status));
        return ['ok' => false, 'status' => $status, 'error' => trim($message)];
    }

    return ['ok' => true, 'status' => $status, 'data' => $decoded];
}

/* ---------------------------------------------------------------- modrinth */

function modrinth_request(string $path): array
{
    return http_json(MODRINTH_API . $path, [
        'User-Agent: ' . source_user_agent(),
        'Accept: application/json',
    ]);
}

/** Normalises a Modrinth version object to the shape the picker works with. */
function modrinth_version(array $version): array
{
    $files = array_values((array)($version['files'] ?? []));
    $primary = null;
    foreach ($files as $file) {
        if (!empty($file['primary'])) {
            $primary = $file;
            break;
        }
    }
    $primary ??= $files[0] ?? [];

    return [
        'id'            => (string)($version['id'] ?? ''),
        'label'         => (string)($version['version_number'] ?? $version['name'] ?? ''),
        'game_versions' => array_values((array)($version['game_versions'] ?? [])),
        'loaders'       => array_values((array)($version['loaders'] ?? [])),
        'channel'       => (string)($version['version_type'] ?? ''),
        'published'     => (string)($version['date_published'] ?? ''),
        'file_name'     => (string)($primary['filename'] ?? ''),
        'file_url'      => (string)($primary['url'] ?? ''),
        'file_size'     => (int)($primary['size'] ?? 0),
    ];
}

function modrinth_search(string $query, int $limit = 10): array
{
    $result = modrinth_request('/search?' . http_build_query([
        'query' => $query,
        'limit' => max(1, min(20, $limit)),
        'index' => 'relevance',
    ]));
    if (!$result['ok']) {
        return ['ok' => false, 'error' => 'Modrinth: ' . $result['error'], 'results' => []];
    }

    $results = [];
    foreach ($result['data']['hits'] ?? [] as $hit) {
        $results[] = [
            'id'        => (string)($hit['project_id'] ?? ''),
            'slug'      => (string)($hit['slug'] ?? ''),
            'name'      => (string)($hit['title'] ?? ''),
            'author'    => (string)($hit['author'] ?? ''),
            'summary'   => (string)($hit['description'] ?? ''),
            'type'      => (string)($hit['project_type'] ?? ''),
            'downloads' => (int)($hit['downloads'] ?? 0),
            'icon'      => (string)($hit['icon_url'] ?? ''),
        ];
    }

    return ['ok' => true, 'error' => '', 'results' => $results];
}

function modrinth_versions(string $idOrSlug): array
{
    $result = modrinth_request('/project/' . rawurlencode($idOrSlug) . '/version');
    if (!$result['ok']) {
        return ['ok' => false, 'error' => 'Modrinth: ' . $result['error'], 'versions' => []];
    }

    $versions = [];
    foreach ($result['data'] as $version) {
        $versions[] = modrinth_version((array)$version);
    }

    return ['ok' => true, 'error' => '', 'versions' => $versions];
}

function modrinth_resolve(array $asset, array $bound): array
{
    if ($bound['version_id'] !== '') {
        $result = modrinth_request('/version/' . rawurlencode($bound['version_id']));
        if (!$result['ok']) {
            return source_failure('Modrinth', $result['error']);
        }
        $version = modrinth_version((array)$result['data']);
    } else {
        $result = modrinth_versions($bound['project_id'] !== '' ? $bound['project_id'] : $bound['project_slug']);
        if (!$result['ok']) {
            return source_failure('Modrinth', $result['error']);
        }
        $version = source_pick($result['versions'], $asset);
        if ($version === null) {
            return source_failure('Modrinth', 'that project has no published files.');
        }
    }

    if ($version['file_url'] === '') {
        return source_failure('Modrinth', 'that version has no downloadable file.');
    }

    return [
        'ok'              => true,
        'error'           => '',
        'cached'          => false,
        'url'             => $version['file_url'],
        'file'            => $version['file_name'],
        'version_id'      => $version['id'],
        'version_label'   => $version['label'],
    ];
}

/* -------------------------------------------------------------- curseforge */

function curseforge_request(string $path): array
{
    if (!curseforge_available()) {
        return ['ok' => false, 'status' => 0, 'error' => 'no CurseForge API key is configured.'];
    }

    return http_json(CURSEFORGE_API . $path, [
        'Accept: application/json',
        'x-api-key: ' . curseforge_api_key(),
        'User-Agent: ' . source_user_agent(),
    ]);
}

/** Normalises a CurseForge file object; gameVersions mixes MC versions and loaders. */
function curseforge_file(array $file): array
{
    $gameVersions = array_values((array)($file['gameVersions'] ?? []));
    $versions = [];
    $loaders = [];
    foreach ($gameVersions as $entry) {
        if (preg_match('/^\d/', (string)$entry) === 1) {
            $versions[] = (string)$entry;
        } else {
            $loaders[] = (string)$entry;
        }
    }

    return [
        'id'            => (string)($file['id'] ?? ''),
        'label'         => (string)($file['displayName'] ?? $file['fileName'] ?? ''),
        'game_versions' => $versions,
        'loaders'       => $loaders,
        'channel'       => (int)($file['releaseType'] ?? 0) === 1 ? 'release' : 'beta',
        'published'     => (string)($file['fileDate'] ?? ''),
        'file_name'     => (string)($file['fileName'] ?? ''),
        'file_url'      => (string)($file['downloadUrl'] ?? ''),
        'file_size'     => (int)($file['fileLength'] ?? 0),
    ];
}

function curseforge_search(string $query, int $limit = 10): array
{
    $result = curseforge_request('/mods/search?' . http_build_query([
        'gameId'       => CURSEFORGE_MINECRAFT,
        'searchFilter' => $query,
        'pageSize'     => max(1, min(20, $limit)),
        'sortField'    => 2, // popularity
        'sortOrder'    => 'desc',
    ]));
    if (!$result['ok']) {
        return ['ok' => false, 'error' => 'CurseForge: ' . $result['error'], 'results' => []];
    }

    $results = [];
    foreach ($result['data']['data'] ?? [] as $item) {
        $results[] = [
            'id'        => (string)($item['id'] ?? ''),
            'slug'      => (string)($item['slug'] ?? ''),
            'name'      => (string)($item['name'] ?? ''),
            'author'    => (string)($item['authors'][0]['name'] ?? ''),
            'summary'   => (string)($item['summary'] ?? ''),
            'type'      => 'mod',
            'downloads' => (int)($item['downloadCount'] ?? 0),
            'icon'      => (string)($item['logo']['thumbnailUrl'] ?? ''),
        ];
    }

    return ['ok' => true, 'error' => '', 'results' => $results];
}

function curseforge_files(int $modId): array
{
    $result = curseforge_request('/mods/' . $modId . '/files?' . http_build_query(['pageSize' => 50]));
    if (!$result['ok']) {
        return ['ok' => false, 'error' => 'CurseForge: ' . $result['error'], 'versions' => []];
    }

    $versions = [];
    foreach ($result['data']['data'] ?? [] as $file) {
        $versions[] = curseforge_file((array)$file);
    }

    return ['ok' => true, 'error' => '', 'versions' => $versions];
}

function curseforge_download_url(int $modId, int $fileId): array
{
    $result = curseforge_request('/mods/' . $modId . '/files/' . $fileId . '/download-url');
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error']];
    }

    $url = (string)($result['data']['data'] ?? '');
    if ($url === '') {
        return ['ok' => false, 'error' => 'the author does not allow API downloads for that file.'];
    }

    return ['ok' => true, 'error' => '', 'url' => $url];
}

function curseforge_resolve(array $asset, array $bound): array
{
    if (!curseforge_available()) {
        return source_failure('CurseForge', 'no API key is configured — add CURSEFORGE_API_KEY.');
    }

    $modId = (int)$bound['project_id'];
    if ($modId <= 0) {
        return source_failure('CurseForge', 'that link has no CurseForge mod id.');
    }

    if ($bound['version_id'] !== '') {
        $result = curseforge_request('/mods/' . $modId . '/files/' . (int)$bound['version_id']);
        if (!$result['ok']) {
            return source_failure('CurseForge', $result['error']);
        }
        $file = curseforge_file((array)($result['data']['data'] ?? []));
    } else {
        $result = curseforge_files($modId);
        if (!$result['ok']) {
            return source_failure('CurseForge', $result['error']);
        }
        $file = source_pick($result['versions'], $asset);
        if ($file === null) {
            return source_failure('CurseForge', 'that project has no published files.');
        }
    }

    $url = $file['file_url'];
    if ($url === '') {
        $link = curseforge_download_url($modId, (int)$file['id']);
        if (!$link['ok']) {
            return source_failure('CurseForge', $link['error']);
        }
        $url = $link['url'];
    }

    return [
        'ok'            => true,
        'error'         => '',
        'cached'        => false,
        'url'           => $url,
        'file'          => $file['file_name'],
        'version_id'    => $file['id'],
        'version_label' => $file['label'],
    ];
}

/* ------------------------------------------------------------------ picker */

/** Loaders worth preferring for a category (empty = accept any file). */
function source_loader_hint(string $category): array
{
    return match ($category) {
        'shaders'              => ['iris', 'optifine', 'canvas'],
        'performance_modpacks' => ['fabric', 'quilt', 'neoforge', 'forge'],
        default                => [],
    };
}

/**
 * Picks the file to serve: newest release that matches the asset's Minecraft
 * version and loader, degrading to the newest file overall when nothing matches.
 */
function source_pick(array $versions, array $asset): ?array
{
    if ($versions === []) {
        return null;
    }

    $wantVersion = (string)($asset['mc_version'] ?? '');
    $wantLoaders = array_map('strtolower', source_loader_hint((string)($asset['category'] ?? '')));

    // Newest first, so the first best-scoring file wins its ties.
    usort($versions, static fn(array $a, array $b): int => strcmp((string)$b['published'], (string)$a['published']));

    $best = null;
    $bestScore = -1.0;

    foreach ($versions as $version) {
        $score = 0.0;

        if ($wantVersion !== '') {
            if (in_array($wantVersion, $version['game_versions'], true)) {
                $score += 2;
            } else {
                foreach ($version['game_versions'] as $candidate) {
                    if (str_starts_with((string)$candidate, $wantVersion . '.')) {
                        $score += 1;
                        break;
                    }
                }
            }
        }

        if ($wantLoaders !== [] && array_intersect($wantLoaders, array_map('strtolower', $version['loaders'])) !== []) {
            $score += 1;
        }

        if ($version['channel'] === 'release') {
            $score += 0.5;
        }

        if ($score > $bestScore) {
            $best = $version;
            $bestScore = $score;
        }
    }

    return $best;
}

function source_failure(string $platform, string $message): array
{
    return [
        'ok'            => false,
        'error'         => rtrim($platform . ' could not serve this file: ' . $message, '.') . '.',
        'cached'        => false,
        'url'           => null,
        'file'          => null,
        'version_id'    => '',
        'version_label' => '',
    ];
}

/* ----------------------------------------------------------------- bindings */

/** The project an asset is linked to, or null when it has no source. */
function asset_source(array $asset): ?array
{
    $source = (string)($asset['source'] ?? '');
    $projectId = (string)($asset['source_project_id'] ?? '');

    if (!source_known($source) || $projectId === '') {
        return null;
    }

    return [
        'source'          => $source,
        'project_id'      => $projectId,
        'project_slug'    => (string)($asset['source_project_slug'] ?? ''),
        'project_name'    => (string)($asset['source_project_name'] ?? ''),
        'version_id'      => (string)($asset['source_version_id'] ?? ''),
        'version_label'   => (string)($asset['source_version_label'] ?? ''),
        'file_name'       => (string)($asset['source_file_name'] ?? ''),
        'file_url'        => (string)($asset['source_file_url'] ?? ''),
        'checked_at'      => (string)($asset['source_checked_at'] ?? ''),
    ];
}

function save_asset_source(int $assetId, array $binding): void
{
    db_run(
        'UPDATE assets SET `source` = ?, source_project_id = ?, source_project_slug = ?, source_project_name = ?,
                source_version_id = ?, source_version_label = ?, source_file_name = ?, source_file_url = ?,
                source_checked_at = NULL
         WHERE id = ?',
        [
            $binding['source'],
            $binding['project_id'] !== '' ? $binding['project_id'] : null,
            $binding['project_slug'] !== '' ? $binding['project_slug'] : null,
            $binding['project_name'] !== '' ? $binding['project_name'] : null,
            $binding['version_id'] !== '' ? $binding['version_id'] : null,
            $binding['version_label'] !== '' ? $binding['version_label'] : null,
            $binding['file_name'] !== '' ? $binding['file_name'] : null,
            $binding['file_url'] !== '' ? $binding['file_url'] : null,
            $assetId,
        ]
    );
}

function clear_asset_source(int $assetId): void
{
    db_run(
        'UPDATE assets SET `source` = "", source_project_id = NULL, source_project_slug = NULL, source_project_name = NULL,
                source_version_id = NULL, source_file_id = NULL, source_version_label = NULL, source_file_name = NULL,
                source_file_url = NULL, source_checked_at = NULL
         WHERE id = ?',
        [$assetId]
    );
}

/* ---------------------------------------------------------------- resolution */

/**
 * Resolves the real file behind an asset.
 * Returns ['ok' => bool, 'url' => ?string, 'file' => ?string, 'error' => string, 'cached' => bool].
 *
 * Pass $force to ignore the cached url (used by the admin "Verify" action).
 */
function source_resolve(array $asset, bool $force = false): array
{
    $bound = asset_source($asset);
    if ($bound === null) {
        return [
            'ok' => false, 'cached' => false, 'url' => null, 'file' => null,
            'error' => 'This item is not linked to a download source yet.',
        ];
    }

    if (!$force && $bound['file_url'] !== '') {
        $ttl = $bound['source'] === 'curseforge' ? SOURCE_URL_TTL_SIGNED : SOURCE_URL_TTL;
        $age = $bound['checked_at'] !== '' ? time() - (int)strtotime($bound['checked_at']) : PHP_INT_MAX;
        if ($age < $ttl) {
            return [
                'ok' => true, 'cached' => true, 'error' => '',
                'url' => $bound['file_url'],
                'file' => $bound['file_name'] !== '' ? $bound['file_name'] : 'download',
            ];
        }
    }

    $resolved = $bound['source'] === 'modrinth'
        ? modrinth_resolve($asset, $bound)
        : curseforge_resolve($asset, $bound);

    if ($resolved['ok']) {
        db_run(
            'UPDATE assets SET source_file_id = ?, source_version_label = ?, source_file_name = ?, source_file_url = ?,
                    source_checked_at = NOW()
             WHERE id = ?',
            [
                $resolved['version_id'] !== '' ? $resolved['version_id'] : null,
                $resolved['version_label'] !== '' ? $resolved['version_label'] : null,
                $resolved['file'],
                $resolved['url'],
                (int)$asset['id'],
            ]
        );
    }

    return $resolved;
}

/** Links an asset to a project, immediately resolving one file so the link is known-good. */
function bind_asset_source(int $assetId, array $binding): array
{
    save_asset_source($assetId, $binding);
    $asset = asset_by_id($assetId);

    return $asset === null
        ? ['ok' => false, 'error' => 'That item no longer exists.']
        : source_resolve($asset, true);
}
