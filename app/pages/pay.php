<?php
declare(strict_types=1);

$payment = payment_by_id((int)($params['id'] ?? 0));
$user = current_user();

if ($payment === null) {
    not_found('/pay/' . (string)($params['id'] ?? ''));
    return;
}

$owner = $payment['user_id'] === null ? null : (int)$payment['user_id'];
if ($owner !== null && !is_admin() && ($user === null || (int)$user['id'] !== $owner)) {
    http_response_code(403);
    include __DIR__ . '/forbidden.php';
    return;
}

$page_nav = '';
$page_title = 'Payment ' . $payment['reference'];
$meta = coin_meta((string)$payment['coin']) ?? coins()['btc'];
$asset = $payment['asset_id'] === null ? null : asset_by_id((int)$payment['asset_id']);

include __DIR__ . '/../views/pay.php';
