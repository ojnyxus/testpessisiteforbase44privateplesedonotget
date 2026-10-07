<?php
declare(strict_types=1);

/** Toggles a bookmark for the signed-in user. */
$user = current_user();
if ($user === null) {
    json_out(['ok' => false, 'error' => 'Sign in to bookmark items.'], 401);
}

require_csrf();

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$assetId = (int)($payload['asset_id'] ?? 0);
if (asset_by_id($assetId) === null) {
    json_out(['ok' => false, 'error' => 'Unknown item.'], 404);
}

$bookmarked = toggle_bookmark((int)$user['id'], $assetId);

json_out(['ok' => true, 'bookmarked' => $bookmarked]);
