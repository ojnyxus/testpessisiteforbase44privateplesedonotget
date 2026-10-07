<?php
declare(strict_types=1);

$asset = asset_by_slug((string)($params['slug'] ?? ''));
if ($asset === null) {
    not_found('/asset/' . (string)($params['slug'] ?? ''));
    return;
}

$user = current_user();
$premium = (bool)$asset['is_premium'];
$unlocked = $premium && $user !== null && has_unlocked((int)$user['id'], (int)$asset['id']);
$pending = ($premium && $user !== null && !$unlocked)
    ? pending_unlock((int)$user['id'], (int)$asset['id'])
    : null;
$bookmarked = in_array((int)$asset['id'], bookmarked_id_set(), true);
$backPath = $asset['section'] === 'library' ? '/library' : '/marketplace';

$page_nav = $asset['section'];
$page_title = $asset['name'];
$related = related_assets($asset);

include __DIR__ . '/../views/asset.php';
