<?php
declare(strict_types=1);

$page_nav = 'dashboard';
$page_title = 'My hub';

$user = require_login();
$bookmarks = bookmarked_assets((int)$user['id']);
$downloads = user_downloads((int)$user['id']);
$unlocks = unlocked_assets((int)$user['id']);
$payments = payments_for_user((int)$user['id']);
$tips = array_values(array_filter($payments, static fn(array $p): bool => $p['kind'] === 'tip'));
?>

<section class="wrap page-head">
    <span class="eyebrow"><?= icon('users', 15) ?> My hub</span>
    <h1>Hey <?= e($user['name']) ?></h1>
    <p class="muted">
        <?= $user['wallet_address'] !== null
            ? 'Signed in with wallet ' . e(substr((string)$user['wallet_address'], 0, 10)) . '…'
            : 'Signed in as ' . e((string)$user['email']) ?>
        · joined <?= e(time_ago((string)$user['created_at'])) ?>
    </p>
</section>

<section class="wrap section" style="padding-top:1.4rem">
    <div class="stat-grid">
        <div class="stat"><div class="stat-label"><?= icon('bookmark', 14) ?> Bookmarks</div>
            <div class="stat-value"><?= count($bookmarks) ?></div><div class="stat-sub">shortlisted items</div></div>
        <div class="stat"><div class="stat-label"><?= icon('download', 14) ?> Downloads</div>
            <div class="stat-value"><?= count($downloads) ?></div><div class="stat-sub">recent activity</div></div>
        <div class="stat gold"><div class="stat-label"><?= icon('lock', 14) ?> Unlocks</div>
            <div class="stat-value gold"><?= count($unlocks) ?></div><div class="stat-sub">premium files owned</div></div>
        <div class="stat gold"><div class="stat-label"><?= icon('cup', 14) ?> Tips sent</div>
            <div class="stat-value gold"><?= e(usd(array_sum(array_map(
                static fn(array $p): float => $p['status'] === 'confirmed' ? (float)$p['amount_usd'] : 0.0,
                $tips
            )))) ?></div><div class="stat-sub">confirmed support</div></div>
    </div>
</section>

<?php if ($unlocks !== []): ?>
    <section class="wrap section">
        <div class="section-head">
            <div>
                <span class="eyebrow gold"><?= icon('lock', 14) ?> Premium library</span>
                <h2 style="font-size:1.3rem;margin:0">Unlocked files</h2>
            </div>
        </div>
        <?= asset_grid($unlocks) ?>
    </section>
<?php endif; ?>

<section class="wrap section">
    <div class="section-head">
        <div>
            <span class="eyebrow"><?= icon('bookmark', 14) ?> Saved</span>
            <h2 style="font-size:1.3rem;margin:0">Your bookmarks</h2>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= url('/library') ?>">Find more <?= icon('arrow', 15) ?></a>
    </div>
    <?= asset_grid($bookmarks) ?>
</section>

<section class="wrap section">
    <div class="pay-layout">
        <div class="panel">
            <div class="panel-head"><h3>Recent downloads</h3><span class="muted small">last <?= count($downloads) ?></span></div>
            <?php if ($downloads === []): ?>
                <p class="muted small" style="margin:0">Nothing yet — grab something from the directory.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Item</th><th>When</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($downloads as $row): ?>
                            <tr>
                                <td><?= e($row['name']) ?></td>
                                <td class="muted"><?= e(time_ago((string)$row['created_at'])) ?></td>
                                <td><a class="btn btn-ghost btn-sm" href="<?= url('/asset/' . $row['slug']) ?>">Open</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-head"><h3>Payments</h3><span class="muted small"><?= count($payments) ?></span></div>
            <?php if ($payments === []): ?>
                <p class="muted small" style="margin:0">No crypto payments yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Ref</th><th>Item</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td class="mono"><a href="<?= url('/pay/' . $payment['id']) ?>"><?= e((string)$payment['reference']) ?></a></td>
                                <td><?= e($payment['asset_name'] ?? $payment['label']) ?></td>
                                <td><?= e(crypto_amount((float)$payment['amount_crypto'])) ?> <?= e(strtoupper((string)$payment['coin'])) ?></td>
                                <td><?= status_badge((string)$payment['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
