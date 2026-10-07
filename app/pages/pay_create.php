<?php
declare(strict_types=1);

/** Creates an unlock or tip invoice, then hands the buyer to the payment page. */
if (!csrf_ok()) {
    flash('Your session expired — please try again.', 'error');
    redirect('/');
}

$kind = ($_POST['kind'] ?? '') === 'tip' ? 'tip' : 'unlock';
$coin = (string)($_POST['coin'] ?? 'btc');
$user = current_user();
$back = '/';

if ($kind === 'unlock') {
    $asset = asset_by_id((int)($_POST['asset_id'] ?? 0));
    if ($asset === null) {
        flash('That item no longer exists.', 'error');
        redirect('/marketplace');
    }
    if (!(bool)$asset['is_premium']) {
        flash('That item is free — download it directly.', 'warning');
        redirect('/asset/' . $asset['slug']);
    }
    if ($user === null) {
        flash('Sign in so the unlock lands on your account.', 'warning');
        redirect('/login');
    }
    if (has_unlocked((int)$user['id'], (int)$asset['id'])) {
        flash('You already own that one.', 'warning');
        redirect('/asset/' . $asset['slug']);
    }

    $back = '/asset/' . $asset['slug'];
    $result = create_payment([
        'kind'      => 'unlock',
        'user_id'   => (int)$user['id'],
        'asset_id'  => (int)$asset['id'],
        'label'     => (string)$asset['name'],
        'coin'      => $coin,
        'amount_usd'=> (float)$asset['price_usd'],
    ]);
} else {
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    if ($amount < 1 || $amount > 5000) {
        flash('Pick a tip between $1 and $5000.', 'warning');
        redirect('/tip');
    }

    $back = '/tip';
    $result = create_payment([
        'kind'      => 'tip',
        'user_id'   => $user === null ? null : (int)$user['id'],
        'label'     => 'Tip for the hub owner',
        'message'   => substr(trim((string)($_POST['message'] ?? '')), 0, 250) ?: null,
        'coin'      => $coin,
        'amount_usd'=> $amount,
    ]);
}

if (isset($result['error'])) {
    flash($result['error'], 'error');
    redirect($back);
}

redirect('/pay/' . $result['payment_id']);
