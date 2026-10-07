<?php
declare(strict_types=1);

/** Admin decision on a pending crypto payment. */
require_admin();
if (!csrf_ok()) {
    flash('Your session expired — try again.', 'error');
    redirect('/admin/payments');
}

$payment = payment_by_id((int)($_POST['payment_id'] ?? 0));
if ($payment === null) {
    flash('Payment not found.', 'error');
    redirect('/admin/payments');
}

$approve = ($_POST['decision'] ?? '') === 'approve';
review_payment((int)$payment['id'], $approve);

flash(
    $approve
        ? 'Confirmed ' . $payment['reference'] . ' — the unlock/tip is now live.'
        : 'Rejected ' . $payment['reference'] . '.',
    $approve ? 'success' : 'warning'
);
redirect('/admin/payments');
