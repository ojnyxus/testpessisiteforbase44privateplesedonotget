<?php
declare(strict_types=1);

$status = (string)$payment['status'];
$isUnlock = $payment['kind'] === 'unlock';
$uri = crypto_uri($payment['coin'], (string)$payment['address'], (float)$payment['amount_crypto'], (string)$payment['label']);
$confirmed = $status === 'confirmed';
$rejected = $status === 'rejected';
?>
<div class="wrap">
    <div class="breadcrumb">
        <a href="<?= url($asset !== null ? '/asset/' . $asset['slug'] : '/') ?>">
            <?= $asset !== null ? e($asset['name']) : 'Home' ?>
        </a>
        <span>/</span>
        <span><?= $isUnlock ? 'Unlock' : 'Tip' ?> invoice</span>
    </div>

    <div class="detail-layout" style="margin-top:.6rem">
        <div class="panel" id="pay-status" data-id="<?= (int)$payment['id'] ?>" data-status="<?= e($status) ?>">
            <?php if ($confirmed): ?>
                <span class="eyebrow"><?= icon('check', 14) ?> Payment confirmed</span>
                <h2><?= $isUnlock ? 'Unlocked — enjoy it' : 'Thank you for the tip' ?></h2>
                <p class="muted">
                    <?= $isUnlock
                        ? 'Your file is attached to your hub permanently.'
                        : 'The hub owner has confirmed your tip on-chain. Genuinely appreciated.' ?>
                </p>
                <div class="row-actions" style="margin-top:1rem">
                    <?php if ($isUnlock && $asset !== null): ?>
                        <a class="btn btn-primary" href="<?= url('/download/' . $asset['id']) ?>"><?= icon('download', 16) ?> Download the file</a>
                    <?php endif; ?>
                    <a class="btn btn-ghost" href="<?= url('/dashboard') ?>"><?= icon('users', 16) ?> Go to my hub</a>
                </div>

            <?php elseif ($rejected): ?>
                <span class="eyebrow gold"><?= icon('close', 14) ?> Payment rejected</span>
                <h2>That payment could not be verified</h2>
                <p class="muted">
                    The hub owner could not match your transaction id on-chain. Double-check it and send the details
                    again, or open a new invoice.
                </p>
                <div class="row-actions">
                    <a class="btn btn-ghost" href="<?= url($asset !== null ? '/asset/' . $asset['slug'] : '/tip') ?>">Try again</a>
                </div>

            <?php elseif ($status === 'submitted'): ?>
                <span class="eyebrow gold"><?= icon('wallet', 14) ?> In review</span>
                <h2>Waiting for confirmation</h2>
                <p class="muted">
                    Transaction id received. The hub owner checks it on-chain and confirms it — this page updates by
                    itself, so you can leave it open.
                </p>
                <div class="address-box"><span class="muted">txid</span><span><?= e((string)$payment['txid']) ?></span></div>
                <div class="fact" style="margin-top:1rem"><span>Status</span><strong><?= status_badge($status) ?></strong></div>

            <?php else: ?>
                <span class="eyebrow gold"><?= icon('wallet', 14) ?> Step 2 of 3 · send the coins</span>
                <h2>Pay <?= e(crypto_amount((float)$payment['amount_crypto'])) ?> <?= e($meta['symbol']) ?></h2>
                <p class="muted">
                    Send the exact amount to the address below from any <?= e($meta['label']) ?> wallet, then paste the
                    transaction id so the owner can verify it.
                </p>

                <div class="address-box">
                    <span class="muted">to</span>
                    <span style="flex:1"><?= e((string)$payment['address']) ?></span>
                    <button class="btn btn-ghost btn-sm icon-btn" type="button" data-copy="<?= e((string)$payment['address']) ?>"
                            title="Copy address"><?= icon('copy', 15) ?></button>
                </div>
                <div class="address-box">
                    <span class="muted">amount</span>
                    <span style="flex:1"><?= e(crypto_amount((float)$payment['amount_crypto'])) ?> <?= e($meta['symbol']) ?></span>
                    <button class="btn btn-ghost btn-sm icon-btn" type="button"
                            data-copy="<?= e(crypto_amount((float)$payment['amount_crypto'])) ?>" title="Copy amount"><?= icon('copy', 15) ?></button>
                </div>
                <div class="address-box">
                    <span class="muted">reference</span>
                    <span style="flex:1"><?= e((string)$payment['reference']) ?></span>
                    <button class="btn btn-ghost btn-sm icon-btn" type="button" data-copy="<?= e((string)$payment['reference']) ?>"
                            title="Copy reference"><?= icon('copy', 15) ?></button>
                </div>

                <?php if ($uri !== ''): ?>
                    <a class="btn btn-ghost btn-block" style="margin-top:1rem" href="<?= e($uri) ?>">
                        <?= icon('wallet', 16) ?> Open in a <?= e($meta['label']) ?> wallet
                    </a>
                <?php endif; ?>

                <hr style="border:0;border-top:1px solid var(--border);margin:1.4rem 0">

                <span class="eyebrow"><?= icon('check', 14) ?> Step 3 of 3 · confirm</span>
                <h3>Paste your transaction id</h3>
                <form method="post" action="<?= url('/pay/submit') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                    <div class="field">
                        <label for="txid">Transaction id / hash</label>
                        <input class="input mono" type="text" id="txid" name="txid" required
                               placeholder="e.g. 4f1c9a…" value="">
                    </div>
                    <button class="btn btn-primary btn-block" type="submit" style="margin-top:.7rem">
                        <?= icon('upload', 16) ?> Submit for confirmation
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <aside class="sticky-side">
            <div class="panel">
                <span class="eyebrow"><?= icon('cube', 14) ?> Order summary</span>
                <h3><?= e((string)$payment['label']) ?></h3>
                <div class="pay-facts" style="margin-top:.8rem">
                    <div class="fact"><span>Type</span><strong><?= $isUnlock ? 'Premium unlock' : 'Crypto tip' ?></strong></div>
                    <div class="fact"><span>Coin</span><strong><?= e($meta['symbol']) ?></strong></div>
                    <div class="fact"><span>Price</span><strong><?= e(usd((float)$payment['amount_usd'])) ?></strong></div>
                    <div class="fact"><span>Rate used</span><strong><?= e(usd(coin_rate((string)$payment['coin']))) ?>/<?= e($meta['symbol']) ?></strong></div>
                    <div class="fact"><span>Status</span><strong><?= status_badge($status) ?></strong></div>
                    <div class="fact"><span>Created</span><strong><?= e(time_ago((string)$payment['created_at'])) ?></strong></div>
                </div>
                <?php if (!empty($payment['message'])): ?>
                    <p class="muted small" style="margin-top:1rem">“<?= e((string)$payment['message']) ?>”</p>
                <?php endif; ?>
            </div>

            <div class="panel">
                <h3>How confirmation works</h3>
                <p class="muted small" style="margin:0">
                    The hub owner verifies your transaction on-chain and confirms it here — there is no third-party
                    processor in the loop. Amounts use the owner's published rate, so send exactly what the invoice shows.
                </p>
            </div>
        </aside>
    </div>
</div>
