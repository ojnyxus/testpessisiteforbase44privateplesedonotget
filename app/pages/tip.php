<?php
declare(strict_types=1);

$page_nav = 'tip';
$page_title = 'Tip with crypto';

$presets = array_values(array_filter(array_map('trim', explode(',', setting('tip_presets', '3,5,10,25')))));
$coffeeUrl = setting('coffee_url');
$stats = stats_overview();
?>

<section class="wrap page-head">
    <span class="eyebrow gold"><?= icon('cup', 15) ?> Buy me a coffee</span>
    <h1>Send a crypto tip</h1>
    <p class="muted" style="max-width:68ch">
        Tips go to the hub owner's own wallet in Bitcoin, Monero or Litecoin. Pick an amount, pay from any wallet,
        and paste the transaction id — anonymous by default.
    </p>
</section>

<section class="wrap section" style="padding-top:1.6rem">
    <div class="pay-layout">
        <form class="panel" method="post" action="<?= url('/pay/create') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="kind" value="tip">

            <h3>1. Choose an amount</h3>
            <div class="preset-row" style="margin:.6rem 0 1rem">
                <?php foreach ($presets as $index => $preset): ?>
                    <button class="preset<?= $index === 1 ? ' is-active' : '' ?>" type="button" data-amount="<?= e($preset) ?>">
                        $<?= e($preset) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="field">
                <label for="amount-input">Custom amount (USD)</label>
                <input class="input" type="number" id="amount-input" name="amount" min="1" max="5000" step="0.5"
                       value="<?= e($presets[1] ?? '5') ?>" required>
            </div>

            <h3 style="margin-top:1.4rem">2. Pick a coin</h3>
            <div class="coin-picker" style="margin:.6rem 0 1rem">
                <?php foreach (coins() as $key => $coin): ?>
                    <label class="coin-option" style="--coin:<?= e($coin['color']) ?>">
                        <input type="radio" name="coin" value="<?= e($key) ?>"<?= $key === 'btc' ? ' checked' : '' ?>>
                        <span class="coin-dot" style="--coin:<?= e($coin['color']) ?>"></span>
                        <span>
                            <strong><?= e($coin['label']) ?></strong>
                            <small>1 <?= e($coin['symbol']) ?> ≈ <?= e(usd(coin_rate($key))) ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="field">
                <label for="message">Message (optional)</label>
                <textarea class="textarea" id="message" name="message" maxlength="250"
                          placeholder="Thanks for the shader pack!"></textarea>
            </div>

            <button class="btn btn-gold btn-lg btn-block" type="submit" style="margin-top:.8rem">
                <?= icon('cup', 18) ?> Create the invoice
            </button>
            <p class="muted small" style="margin:.8rem 0 0">No account needed — tips are anonymous unless you sign in.</p>
        </form>

        <div class="sticky-side">
            <div class="panel">
                <span class="eyebrow"><?= icon('chart', 14) ?> Impact so far</span>
                <div class="stat-grid">
                    <div class="stat gold">
                        <div class="stat-label">Tips settled</div>
                        <div class="stat-value gold"><?= e(usd((float)$stats['revenue_usd'])) ?></div>
                        <div class="stat-sub">confirmed on-chain payments</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Members</div>
                        <div class="stat-value"><?= e((string)$stats['users_total']) ?></div>
                        <div class="stat-sub"><?= e((string)$stats['users_active_30d']) ?> active this month</div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h3>Where the coins go</h3>
                <p class="muted small" style="margin:0">
                    Straight to the owner's wallet. The hub is non-custodial: it only displays an address and
                    records the transaction id you paste.
                </p>
                <div class="coin-row" style="margin-top:.9rem">
                    <?php foreach (coins() as $key => $coin): ?>
                        <span class="coin-pill" style="--coin:<?= e($coin['color']) ?>"><?= e($coin['symbol']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if ($coffeeUrl !== ''): ?>
                    <a class="btn btn-ghost btn-block" style="margin-top:1rem" href="<?= e($coffeeUrl) ?>"
                       target="_blank" rel="noopener"><?= icon('cup', 16) ?> Buy me a coffee page</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
