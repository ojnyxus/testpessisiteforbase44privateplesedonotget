<?php
declare(strict_types=1);

/** Records the transaction id the buyer pasted, then sends them back to the invoice. */
$payment = payment_by_id((int)($_POST['payment_id'] ?? 0));

if ($payment === null) {
    flash('Payment not found.', 'error');
    redirect('/');
}

if (!csrf_ok()) {
    flash('Your session expired — refresh and try again.', 'error');
    redirect('/pay/' . $payment['id']);
}

$user = current_user();
$owner = $payment['user_id'] === null ? null : (int)$payment['user_id'];
if ($owner !== null && ($user === null || (int)$user['id'] !== $owner)) {
    flash('That invoice belongs to another account.', 'error');
    redirect('/');
}

$txid = trim((string)($_POST['txid'] ?? ''));
if ($txid === '') {
    flash('Paste the transaction id from your wallet.', 'warning');
    redirect('/pay/' . $payment['id']);
}

submit_txid((int)$payment['id'], substr($txid, 0, 200));
flash('Transaction id received — the hub owner will confirm it shortly.', 'success');
redirect('/pay/' . $payment['id']);
