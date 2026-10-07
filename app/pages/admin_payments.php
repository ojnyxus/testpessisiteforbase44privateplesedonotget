<?php
declare(strict_types=1);

$page_nav = 'admin';
$page_title = 'Crypto payments';

require_admin();

$status = trim((string)($_GET['status'] ?? ''));
$allowed = ['pending', 'submitted', 'confirmed', 'rejected'];
if (!in_array($status, $allowed, true)) {
    $status = '';
}

$payments = payments_feed(60, $status);
$stats = stats_overview();
?>
<section class="wrap page-head">
    <span class="eyebrow"><?= icon('wallet', 15) ?> Crypto payments</span>
    <h1>Confirm incoming payments</h1>
    <p class="muted" style="max-width:70ch">
        Check each transaction id against the address on-chain, then confirm it here. Confirming an unlock gives the
        buyer their download; confirming a tip books the revenue in your analytics.
    </p>
    <div class="row-actions" style="margin-top:1rem">
        <a class="btn btn-ghost btn-sm" href="<?= url('/admin') ?>"><?= icon('chart', 15) ?> Overview</a>
        <a class="btn btn-ghost btn-sm" href="<?= url('/admin/settings') ?>"><?= icon('shield', 15) ?> Wallet &amp; settings</a>
    </div>
</section>

<section class="wrap section" style="padding-top:1.4rem">
    <div class="stat-grid">
        <div class="stat"><div class="stat-label">Awaiting confirmation</div>
            <div class="stat-value"><?= (int)db_value('SELECT COUNT(*) FROM payments WHERE status = "submitted"') ?></div>
            <div class="stat-sub">buyer submitted a txid</div></div>
        <div class="stat"><div class="stat-label">No txid yet</div>
            <div class="stat-value"><?= (int)db_value('SELECT COUNT(*) FROM payments WHERE status = "pending"') ?></div>
            <div class="stat-sub">invoices still open</div></div>
        <div class="stat gold"><div class="stat-label">Settled revenue</div>
            <div class="stat-value gold"><?= e(usd((float)$stats['revenue_usd'] + (float)$stats['premium_usd'])) ?></div>
            <div class="stat-sub">tips + premium unlocks</div></div>
    </div>
</section>

<section class="wrap section">
    <div class="panel">
        <div class="panel-head">
            <h3><?= $status === '' ? 'All payments' : ucfirst($status) . ' payments' ?></h3>
            <div class="row-actions">
                <a class="btn btn-ghost btn-sm<?= $status === '' ? ' is-on' : '' ?>" href="<?= url('/admin/payments') ?>">All</a>
                <?php foreach ($allowed as $option): ?>
                    <a class="btn btn-ghost btn-sm<?= $status === $option ? ' is-on' : '' ?>"
                       href="<?= url('/admin/payments?status=' . $option) ?>"><?= e(ucfirst($option)) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($payments === []): ?>
            <p class="muted small" style="margin:0">No payments here yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>Ref</th><th>Type</th><th>Buyer</th><th>Item</th><th>Coin</th><th>Amount</th><th>Status</th><th>Decision</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="mono"><a href="<?= url('/pay/' . $payment['id']) ?>"><?= e((string)$payment['reference']) ?></a>
                                <div class="muted small"><?= e(time_ago((string)$payment['created_at'])) ?></div></td>
                            <td><?= $payment['kind'] === 'unlock' ? 'Unlock' : 'Tip' ?></td>
                            <td><?= e($payment['user_name'] ?? 'anonymous') ?></td>
                            <td><?= e($payment['asset_name'] ?? $payment['label']) ?></td>
                            <td><?= e(strtoupper((string)$payment['coin'])) ?></td>
                            <td><?= e(crypto_amount((float)$payment['amount_crypto'])) ?>
                                <div class="muted small"><?= e(usd((float)$payment['amount_usd'])) ?></div></td>
                            <td><?= status_badge((string)$payment['status']) ?>
                                <?php if (!empty($payment['txid'])): ?>
                                    <div class="muted small mono" style="max-width:170px;overflow:hidden;text-overflow:ellipsis">
                                        <?= e((string)$payment['txid']) ?>
                                    </div>
                                <?php endif; ?></td>
                            <td>
                                <?php if (in_array($payment['status'], ['pending', 'submitted'], true)): ?>
                                    <div class="row-actions">
                                        <form method="post" action="<?= url('/pay/review') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <button class="btn btn-ok btn-sm" type="submit">Confirm</button>
                                        </form>
                                        <form method="post" action="<?= url('/pay/review') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <button class="btn btn-danger btn-sm" type="submit">Reject</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="muted small"><?= e($payment['settled_at'] !== null ? time_ago((string)$payment['settled_at']) : '—') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
