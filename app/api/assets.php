<?php
declare(strict_types=1);

/** JSON catalogue feed used by the live filters on /library and /marketplace. */
$filters = [
    'section'  => (string)($_GET['section'] ?? 'library'),
    'search'   => trim((string)($_GET['search'] ?? '')),
    'version'  => trim((string)($_GET['version'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'impact'   => trim((string)($_GET['impact'] ?? '')),
    'sort'     => trim((string)($_GET['sort'] ?? 'popular')),
];

if (isset($_GET['premium']) && $_GET['premium'] !== '') {
    $filters['premium'] = (int)$_GET['premium'] === 1 ? 1 : 0;
}

if (!in_array($filters['section'], ['library', 'marketplace'], true)) {
    $filters['section'] = 'library';
}

$assets = find_assets($filters);
$user = current_user();
$bookmarked = bookmarked_id_set();
$unlocked = $user === null
    ? []
    : array_map('intval', array_column(unlocked_assets((int)$user['id']), 'id'));

$items = array_map(static function (array $asset) use ($bookmarked, $unlocked): array {
    return [
        'id'             => (int)$asset['id'],
        'slug'           => (string)$asset['slug'],
        'name'           => (string)$asset['name'],
        'author'         => (string)$asset['author'],
        'summary'        => (string)$asset['summary'],
        'category'       => (string)$asset['category'],
        'category_label' => category_label((string)$asset['category']),
        'mc_version'     => (string)$asset['mc_version'],
        'impact'         => (string)$asset['impact'],
        'impact_label'   => impact_label((string)$asset['impact']),
        'is_premium'     => (int)$asset['is_premium'],
        'price'          => number_format((float)$asset['price_usd'], 2, '.', ''),
        'rating'         => number_format((float)$asset['rating'], 1),
        'downloads_label'=> human_number((int)$asset['downloads']),
        'thumb'          => thumb_url((string)$asset['slug'], thumb_kind($asset), 640, 360),
        'bookmarked'     => in_array((int)$asset['id'], $bookmarked, true),
        'unlocked'       => in_array((int)$asset['id'], $unlocked, true),
    ];
}, $assets);

json_out(['ok' => true, 'total' => count($items), 'items' => $items]);
