<?php
declare(strict_types=1);

/** Lets the invoice page notice when the owner confirms a payment. */
$payment = payment_by_id((int)($_GET['id'] ?? 0));
if ($payment === null) {
    json_out(['ok' => false, 'error' => 'Unknown payment.'], 404);
}

json_out(['ok' => true, 'status' => (string)$payment['status']]);
