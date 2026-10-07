<?php
declare(strict_types=1);

$page_nav = 'admin';
$page_title = 'Hub analytics';

require_admin();

$stats = stats_overview();
$series = downloads_by_day(14);
$peak = max(1, max(array_column($series, 'total')));
$categories = category_breakdown();
$categoryPeak = max(1, max(array_map(static fn(array $c): int => (int)$c['downloads'], $categories) ?: [1]));
$top = top_assets(5);
$recent = recent_downloads(6);
$pending = payments_feed(5, 'submitted');
?>
<section class="wrap page-head">
    <span class="eyebrow"><?= icon('chart', 15) ?> Owner dashboard</span>
    <h1>Hub analytics</h1>
    <p class="muted">Downloads, membership and every crypto payment that came in — confirm them below.</p>
    <div class="row-actions" style="margin-top:1rem">
        <a class="btn btn-ghost btn-sm" href="<?= url('/admin/payments') ?>"><?= icon('wallet', 15) ?> Payments</a>
        <a class="btn btn-ghost btn-sm" href="<?= url('/admin/settings') ?>"><?= icon('shield', 15) ?> Settings</a>
    </div>
</section>

<section class="wrap section" style="padding-top:1.4rem">
    <div class="stat-grid">
        <div class="stat"><div class="stat-label"><?= icon('download', 14) ?> Total downloads</div>
            <div class="stat-value"><?= e(human_number((int)$stats['downloads_total'])) ?></div>
            <div class="stat-sub"><?= e(human_number((int)$stats['downloads_7d'])) ?> in the last 7 days</div></div>
        <div class="stat"><div class="stat-label"><?= icon('users', 14) ?> Members</div>
            <div class="stat-value"><?= (int)$stats['users_total'] ?></div>
            <div class="stat-sub"><?= (int)$stats['users_active_30d'] ?> active in 30 days · <?= (int)$stats['users_wallet'] ?> wallet-only</div></div>
        <div class="stat gold"><div class="stat-label"><?= icon('cup', 14) ?> Tip revenue</div>
            <div class="stat-value gold"><?= e(usd((float)$stats['revenue_usd'])) ?></div>
            <div class="stat-sub">confirmed tips</div></div>
        <div class="stat gold"><div class="stat-label"><?= icon('lock', 14) ?> Premium revenue</div>
            <div class="stat-value gold"><?= e(usd((float)$stats['premium_usd'])) ?></div>
            <div class="stat-sub"><?= (int)$stats['confirmed_payments'] ?> payments settled</div></div>
    </div>
</section>

<section class="wrap section">
    <div class="pay-layout">
        <div class="panel">
            <div class="panel-head">
                <h3>Downloads · last 14 days</h3>
                <span class="muted small">peak <?= (int)$peak ?>/day</span>
            </div>
            <div class="chart">
                <?php foreach ($series as $point): ?>
                    <?php $height = max(4, (int)round(((int)$point['total'] / $peak) * 100)); ?>
                    <div class="chart-bar" style="height:<?= $height ?>%"
                         data-label="<?= e(date('M j', strtotime((string)$point['day']))) ?>: <?= (int)$point['total'] ?>"></div>
                <?php endforeach; ?>
            </div>
            <div class="muted small" style="display:flex;justify-content:space-between;margin-top:.6rem">
                <span><?= e(date('M j', strtotime((string)$series[0]['day']))) ?></span>
                <span>today</span>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Catalogue mix</h3><span class="muted small"><?= (int)$stats['assets_total'] ?> items</span></div>
            <?php foreach ($categories as $category): ?>
                <?php $share = (int)round(((int)$category['downloads'] / $categoryPeak) * 100); ?>
                <div style="margin-bottom:.8rem">
                    <div style="display:flex;justify-content:space-between;font-size:.86rem">
                        <span><?= e(category_label((string)$category['category'])) ?></span>
                        <span class="muted"><?= e(human_number((int)$category['downloads'])) ?> dl · <?= (int)$category['total'] ?> items</span>
                    </div>
                    <div style="height:7px;border-radius:99px;background:var(--surface-2);margin-top:.35rem;overflow:hidden">
                        <div style="height:100%;width:<?= $share ?>%;border-radius:99px;background:linear-gradient(90deg,var(--emerald),var(--emerald-dk))"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="wrap section">
    <div class="panel">
        <div class="panel-head">
            <h3><?= icon('wallet', 16) ?> Payments waiting on you</h3>
            <a class="btn btn-ghost btn-sm" href="<?= url('/admin/payments') ?>">All payments <?= icon('arrow', 15) ?></a>
        </div>
        <?php if ($pending === []): ?>
            <p class="muted small" style="margin:0">
                Nothing in review. <?= (int)$stats['pending_payments'] ?> invoice<?= (int)$stats['pending_payments'] === 1 ? '' : 's' ?>
                still awaiting a transaction id.
            </p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ref</th><th>Buyer</th><th>Item</th><th>Amount</th><th>Txid</th><th>Decision</th></tr></thead>
                    <tbody>
                    <?php foreach ($pending as $payment): ?>
                        <tr>
                            <td class="mono"><a href="<?= url('/pay/' . $payment['id']) ?>"><?= e((string)$payment['reference']) ?></a></td>
                            <td><?= e($payment['user_name'] ?? 'anonymous') ?></td>
                            <td><?= e($payment['asset_name'] ?? $payment['label']) ?></td>
                            <td><?= e(crypto_amount((float)$payment['amount_crypto'])) ?> <?= e(strtoupper((string)$payment['coin'])) ?></td>
                            <td class="mono small" style="max-width:180px;overflow:hidden;text-overflow:ellipsis"><?= e((string)($payment['txid'] ?? '—')) ?></td>
                            <td>
                                <div class="row-actions">
                                    <form method="post" action="<?= url('/pay/review') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                                        <input type="hidden" name="decision" value="approve">
                                        <button class="btn btn-ok btn-sm" type="submit"><?= icon('check', 14) ?> Confirm</button>
                                    </form>
                                    <form method="post" action="<?= url('/pay/review') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                                        <input type="hidden" name="decision" value="reject">
                                        <button class="btn btn-danger btn-sm" type="submit">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="wrap section">
    <div class="pay-layout">
        <div class="panel">
            <div class="panel-head"><h3>Most downloaded</h3></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Item</th><th>Category</th><th>Version</th><th>Downloads</th></tr></thead>
                    <tbody>
                    <?php foreach ($top as $asset): ?>
                        <tr>
                            <td><a href="<?= url('/asset/' . $asset['slug']) ?>"><?= e($asset['name']) ?></a>
                                <?php if ((bool)$asset['is_premium']): ?><span class="badge badge-gold" style="margin-left:.4rem">Premium</span><?php endif; ?>
                            </td>
                            <td class="muted"><?= e(category_label((string)$asset['category'])) ?></td>
                            <td class="muted"><?= e((string)$asset['mc_version']) ?></td>
                            <td><?= e(human_number((int)$asset['downloads'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Latest downloads</h3></div>
            <?php if ($recent === []): ?>
                <p class="muted small" style="margin:0">No downloads recorded yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Item</th><th>Who</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent as $row): ?>
                            <tr>
                                <td><?= e($row['name']) ?></td>
                                <td class="muted"><?= e($row['user_name'] ?? 'guest') ?></td>
                                <td class="muted"><?= e(time_ago((string)$row['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
