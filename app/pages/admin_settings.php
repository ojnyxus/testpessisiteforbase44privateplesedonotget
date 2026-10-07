<?php
declare(strict_types=1);

$page_nav = 'admin';
$page_title = 'Hub settings';

require_admin();
?>
<section class="wrap page-head">
    <span class="eyebrow"><?= icon('shield', 15) ?> Hub settings</span>
    <h1>Wallets, rates and branding</h1>
    <p class="muted" style="max-width:70ch">
        These addresses are what buyers pay. Replace the placeholders with your own wallet addresses before taking real
        payments, and keep the USD rates fresh so invoices convert correctly.
    </p>
    <div class="row-actions" style="margin-top:1rem">
        <a class="btn btn-ghost btn-sm" href="<?= url('/admin') ?>"><?= icon('chart', 15) ?> Overview</a>
        <a class="btn btn-ghost btn-sm" href="<?= url('/admin/payments') ?>"><?= icon('wallet', 15) ?> Payments</a>
    </div>
</section>

<section class="wrap section" style="padding-top:1.4rem">
    <form class="panel" method="post" action="<?= url('/admin/settings') ?>">
        <?= csrf_field() ?>

        <h3>Branding</h3>
        <div class="pay-facts" style="margin:.8rem 0 1.4rem">
            <div class="field">
                <label for="site_name">Hub name</label>
                <input class="input" type="text" id="site_name" name="site_name" maxlength="64"
                       value="<?= e(setting('site_name', config('app_name'))) ?>">
            </div>
            <div class="field">
                <label for="site_tagline">Tagline</label>
                <input class="input" type="text" id="site_tagline" name="site_tagline" maxlength="160"
                       value="<?= e(setting('site_tagline', config('tagline'))) ?>">
            </div>
        </div>

        <h3>Receiving addresses</h3>
        <p class="muted small">Leave an address empty to disable that coin — its invoices will be refused.</p>
        <div class="pay-facts" style="margin:.8rem 0 1.4rem">
            <?php foreach (coins() as $key => $coin): ?>
                <div class="field">
                    <label for="address_<?= e($key) ?>"><?= e($coin['label']) ?> address</label>
                    <input class="input mono" type="text" id="address_<?= e($key) ?>" name="address_<?= e($key) ?>"
                           value="<?= e(setting('address_' . $key)) ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <h3>Conversion rates</h3>
        <p class="muted small">USD per 1 coin. Update these manually — the hub does not call a price API.</p>
        <div class="pay-facts" style="margin:.8rem 0 1.4rem">
            <?php foreach (coins() as $key => $coin): ?>
                <div class="field">
                    <label for="rate_<?= e($key) ?>">USD per 1 <?= e($coin['symbol']) ?></label>
                    <input class="input" type="number" step="0.01" min="0.0001" id="rate_<?= e($key) ?>"
                           name="rate_<?= e($key) ?>" value="<?= e(setting('rate_' . $key)) ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <h3>Tipping</h3>
        <div class="pay-facts" style="margin:.8rem 0 1.4rem">
            <div class="field">
                <label for="tip_presets">Preset amounts (USD, comma separated)</label>
                <input class="input" type="text" id="tip_presets" name="tip_presets"
                       value="<?= e(setting('tip_presets', '3,5,10,25')) ?>">
            </div>
            <div class="field">
                <label for="coffee_url">External "Buy me a coffee" link (optional)</label>
                <input class="input" type="url" id="coffee_url" name="coffee_url" placeholder="https://…"
                       value="<?= e(setting('coffee_url')) ?>">
            </div>
        </div>

        <button class="btn btn-primary" type="submit"><?= icon('check', 16) ?> Save settings</button>
    </form>
</section>
