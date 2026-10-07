<?php
declare(strict_types=1);

/**
 * All database reads/writes for the hub, kept in one place so the pages stay thin.
 */

/* ------------------------------------------------------------- settings */

function all_settings(): array
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $settings = all_settings();

    return array_key_exists($key, $settings) && $settings[$key] !== '' ? $settings[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    db_run(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
}

/* --------------------------------------------------------------- assets */

const CATEGORY_LABELS = [
    'shaders'              => 'Shaders',
    'texture_packs'        => 'Texture Packs',
    'performance_modpacks' => 'Performance Modpacks',
    'configs'              => 'Plugin Configs',
    'datapacks'            => 'Data Packs',
    'schematics'           => 'Schematics',
];

const IMPACT_LABELS = ['low' => 'Low PC', 'medium' => 'Medium PC', 'high' => 'High PC'];

function category_label(string $category): string
{
    return CATEGORY_LABELS[$category] ?? ucfirst(str_replace('_', ' ', $category));
}

function impact_label(string $impact): string
{
    return IMPACT_LABELS[$impact] ?? ucfirst($impact);
}

function mc_versions(): array
{
    return array_column(db_all('SELECT DISTINCT mc_version FROM assets ORDER BY mc_version DESC'), 'mc_version');
}

/**
 * Catalogue query used by both the server-rendered grid and the /api/assets filter endpoint.
 */
function find_assets(array $filters = []): array
{
    $where = [];
    $params = [];

    if (!empty($filters['section'])) {
        $where[] = 'section = ?';
        $params[] = $filters['section'];
    }
    if (!empty($filters['category'])) {
        $where[] = 'category = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['version'])) {
        $where[] = 'mc_version = ?';
        $params[] = $filters['version'];
    }
    if (!empty($filters['impact'])) {
        $where[] = 'impact = ?';
        $params[] = $filters['impact'];
    }
    if (isset($filters['premium']) && $filters['premium'] !== '') {
        $where[] = 'is_premium = ?';
        $params[] = (int)$filters['premium'];
    }
    if (!empty($filters['search'])) {
        $where[] = '(name LIKE ? OR author LIKE ? OR summary LIKE ?)';
        $term = '%' . $filters['search'] . '%';
        array_push($params, $term, $term, $term);
    }

    $sorts = [
        'popular' => 'downloads DESC',
        'newest'  => 'created_at DESC',
        'rating'  => 'rating DESC, downloads DESC',
        'name'    => 'name ASC',
        'price'   => 'is_premium ASC, price_usd DESC',
    ];
    $order = $sorts[$filters['sort'] ?? 'popular'] ?? $sorts['popular'];

    $sql = 'SELECT * FROM assets';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $order . ' LIMIT 60';

    return db_all($sql, $params);
}

function asset_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM assets WHERE slug = ?', [$slug]);
}

function asset_by_id(int $id): ?array
{
    return db_one('SELECT * FROM assets WHERE id = ?', [$id]);
}

function related_assets(array $asset, int $limit = 3): array
{
    return db_all(
        'SELECT * FROM assets WHERE section = ? AND category = ? AND id <> ? ORDER BY downloads DESC LIMIT ' . (int)$limit,
        [$asset['section'], $asset['category'], $asset['id']]
    );
}

function includes_list(array $asset): array
{
    return array_values(array_filter(array_map('trim', explode("\n", (string)$asset['includes']))));
}

/* ------------------------------------------------------------ bookmarks */

function bookmark_ids(int $userId): array
{
    return array_map('intval', array_column(
        db_all('SELECT asset_id FROM bookmarks WHERE user_id = ?', [$userId]),
        'asset_id'
    ));
}

function toggle_bookmark(int $userId, int $assetId): bool
{
    if (db_one('SELECT 1 FROM bookmarks WHERE user_id = ? AND asset_id = ?', [$userId, $assetId]) !== null) {
        db_run('DELETE FROM bookmarks WHERE user_id = ? AND asset_id = ?', [$userId, $assetId]);

        return false;
    }

    db_run('INSERT INTO bookmarks (user_id, asset_id, created_at) VALUES (?, ?, NOW())', [$userId, $assetId]);

    return true;
}

function bookmarked_assets(int $userId): array
{
    return db_all(
        'SELECT a.* FROM bookmarks b JOIN assets a ON a.id = b.asset_id WHERE b.user_id = ? ORDER BY b.created_at DESC',
        [$userId]
    );
}

/* ------------------------------------------------------------ downloads */

function record_download(int $assetId, ?int $userId, string $ip): void
{
    db_run(
        'INSERT INTO downloads (asset_id, user_id, ip_hash, created_at) VALUES (?, ?, ?, NOW())',
        [$assetId, $userId, sha1($ip . '|blockforge')]
    );
    db_run('UPDATE assets SET downloads = downloads + 1 WHERE id = ?', [$assetId]);
}

function user_downloads(int $userId, int $limit = 15): array
{
    return db_all(
        'SELECT d.created_at, a.name, a.slug, a.id AS asset_id
         FROM downloads d JOIN assets a ON a.id = d.asset_id
         WHERE d.user_id = ? ORDER BY d.created_at DESC LIMIT ' . (int)$limit,
        [$userId]
    );
}

/* ------------------------------------------------------------- payments */

function create_payment(array $data): array
{
    $coin = strtolower((string)$data['coin']);
    $meta = coin_meta($coin);
    if ($meta === null) {
        return ['error' => 'Unsupported coin.'];
    }

    $address = coin_address($coin);
    if ($address === '') {
        return ['error' => 'The owner has not configured a ' . $meta['label'] . ' address yet.'];
    }

    $usd = round((float)$data['amount_usd'], 2);
    if ($usd <= 0) {
        return ['error' => 'Enter an amount above zero.'];
    }

    $reference = payment_reference($data['kind'] === 'tip' ? 'TIP' : 'UNLOCK');
    db_run(
        'INSERT INTO payments (kind, user_id, asset_id, label, message, coin, address, amount_crypto, amount_usd,
                               reference, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW())',
        [
            $data['kind'],
            $data['user_id'] ?? null,
            $data['asset_id'] ?? null,
            $data['label'],
            $data['message'] ?? null,
            $coin,
            $address,
            usd_to_crypto($usd, $coin),
            $usd,
            $reference,
        ]
    );

    return ['payment_id' => (int)db()->lastInsertId(), 'reference' => $reference];
}

function payment_by_id(int $id): ?array
{
    return db_one(
        'SELECT p.*, a.name AS asset_name, a.slug AS asset_slug, u.name AS user_name
         FROM payments p
         LEFT JOIN assets a ON a.id = p.asset_id
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = ?',
        [$id]
    );
}

function payments_for_user(int $userId): array
{
    return db_all(
        'SELECT p.*, a.name AS asset_name, a.slug AS asset_slug
         FROM payments p LEFT JOIN assets a ON a.id = p.asset_id
         WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 40',
        [$userId]
    );
}

function submit_txid(int $paymentId, string $txid): void
{
    db_run(
        'UPDATE payments SET txid = ?, status = "submitted" WHERE id = ? AND status = "pending"',
        [$txid, $paymentId]
    );
}

function review_payment(int $paymentId, bool $approve): void
{
    db_run(
        'UPDATE payments SET status = ?, settled_at = NOW() WHERE id = ?',
        [$approve ? 'confirmed' : 'rejected', $paymentId]
    );
}

/** True when this user holds a confirmed unlock for the asset. */
function has_unlocked(int $userId, int $assetId): bool
{
    return db_one(
        'SELECT 1 FROM payments WHERE kind = "unlock" AND status = "confirmed" AND user_id = ? AND asset_id = ?',
        [$userId, $assetId]
    ) !== null;
}

function pending_unlock(int $userId, int $assetId): ?array
{
    return db_one(
        'SELECT * FROM payments WHERE kind = "unlock" AND user_id = ? AND asset_id = ?
         AND status IN ("pending","submitted") ORDER BY created_at DESC LIMIT 1',
        [$userId, $assetId]
    );
}

function unlocked_assets(int $userId): array
{
    return db_all(
        'SELECT a.*, p.settled_at FROM payments p JOIN assets a ON a.id = p.asset_id
         WHERE p.kind = "unlock" AND p.status = "confirmed" AND p.user_id = ?
         ORDER BY p.settled_at DESC',
        [$userId]
    );
}

function payments_feed(int $limit = 25, string $status = ''): array
{
    $sql = 'SELECT p.*, a.name AS asset_name, u.name AS user_name
            FROM payments p
            LEFT JOIN assets a ON a.id = p.asset_id
            LEFT JOIN users u ON u.id = p.user_id';
    $params = [];
    if ($status !== '') {
        $sql .= ' WHERE p.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY p.created_at DESC LIMIT ' . (int)$limit;

    return db_all($sql, $params);
}

/* ------------------------------------------------------------ analytics */

function stats_overview(): array
{
    return [
        'downloads_total'   => (int)db_value('SELECT COALESCE(SUM(downloads),0) FROM assets'),
        'downloads_7d'      => (int)db_value('SELECT COUNT(*) FROM downloads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
        'assets_total'      => (int)db_value('SELECT COUNT(*) FROM assets'),
        'premium_total'     => (int)db_value('SELECT COUNT(*) FROM assets WHERE is_premium = 1'),
        'users_total'       => (int)db_value('SELECT COUNT(*) FROM users'),
        'users_active_30d'  => (int)db_value('SELECT COUNT(*) FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
        'users_wallet'      => (int)db_value('SELECT COUNT(*) FROM users WHERE wallet_address IS NOT NULL'),
        'revenue_usd'       => (float)db_value('SELECT COALESCE(SUM(amount_usd),0) FROM payments WHERE status = "confirmed" AND kind = "tip"'),
        'premium_usd'       => (float)db_value('SELECT COALESCE(SUM(amount_usd),0) FROM payments WHERE status = "confirmed" AND kind = "unlock"'),
        'pending_payments'  => (int)db_value('SELECT COUNT(*) FROM payments WHERE status IN ("pending","submitted")'),
        'confirmed_payments'=> (int)db_value('SELECT COUNT(*) FROM payments WHERE status = "confirmed"'),
    ];
}

function downloads_by_day(int $days = 14): array
{
    $rows = db_all(
        'SELECT DATE(created_at) AS day, COUNT(*) AS total
         FROM downloads WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ' . ($days - 1) . ' DAY)
         GROUP BY DATE(created_at)'
    );
    $byDay = [];
    foreach ($rows as $row) {
        $byDay[$row['day']] = (int)$row['total'];
    }

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $series[] = ['day' => $day, 'total' => $byDay[$day] ?? 0];
    }

    return $series;
}

function category_breakdown(): array
{
    return db_all('SELECT category, COUNT(*) AS total, COALESCE(SUM(downloads),0) AS downloads FROM assets GROUP BY category ORDER BY downloads DESC');
}

function top_assets(int $limit = 5): array
{
    return db_all('SELECT * FROM assets ORDER BY downloads DESC LIMIT ' . (int)$limit);
}

function recent_downloads(int $limit = 8): array
{
    return db_all(
        'SELECT d.created_at, a.name, a.slug, u.name AS user_name
         FROM downloads d JOIN assets a ON a.id = d.asset_id LEFT JOIN users u ON u.id = d.user_id
         ORDER BY d.created_at DESC LIMIT ' . (int)$limit
    );
}
