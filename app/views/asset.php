<?php
declare(strict_types=1);

/** $bound: the Modrinth / CurseForge project this item downloads from, when linked. */
$bound = asset_source($asset);
?>
<div class="wrap">
    <div class="breadcrumb">
        <a href="<?= url($backPath) ?>"><?= $asset['section'] === 'library' ? 'Directory' : 'Marketplace' ?></a>
        <span>/</span>
        <span><?= e(category_label($asset['category'])) ?></span>
    </div>

    <div class="detail-layout">
        <div>
            <div class="detail-visual">
                <img src="<?= e(thumb_url($asset['slug'], thumb_kind($asset), 1000, 560)) ?>" alt="" width="1000" height="560">
            </div>

            <div class="section-head" style="margin-top:1.4rem;align-items:flex-start">
                <div>
                    <div class="chip-row" style="margin-bottom:.6rem">
                        <span class="chip"><?= e(category_label($asset['category'])) ?></span>
                        <span class="chip chip-ghost">Minecraft <?= e($asset['mc_version']) ?></span>
                        <span class="badge badge-impact impact-<?= e($asset['impact']) ?>"><?= e(impact_label($asset['impact'])) ?></span>
                        <?php if ($premium): ?><span class="badge badge-gold">Premium</span><?php endif; ?>
                        <?php if ($bound !== null): ?>
                            <span class="chip chip-ghost"><?= e(source_label($bound['source'])) ?> files</span>
                        <?php endif; ?>
                    </div>
                    <h1 style="font-size:clamp(1.8rem,3.4vw,2.4rem)"><?= e($asset['name']) ?></h1>
                    <p class="muted">by <?= e($asset['author']) ?> · added <?= e(time_ago($asset['created_at'])) ?></p>
                </div>
            </div>

            <div class="detail-stats">
                <div class="hero-stat"><strong><?= e(human_number((int)$asset['downloads'])) ?></strong><span>downloads</span></div>
                <div class="hero-stat"><strong><?= e(number_format((float)$asset['rating'], 1)) ?></strong><span>rating</span></div>
                <div class="hero-stat"><strong><?= e(impact_label($asset['impact'])) ?></strong><span>PC impact</span></div>
            </div>

            <div class="prose">
                <h3>What it is</h3>
                <p><?= e($asset['description']) ?></p>
                <h3>What's included</h3>
                <ul class="check-list">
                    <?php foreach (includes_list($asset) as $line): ?>
                        <li><?= icon('check', 16) ?><span><?= e($line) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <aside class="sticky-side">
            <div class="panel">
                <?php if ($premium && !$unlocked): ?>
                    <span class="eyebrow gold"><?= icon('lock', 14) ?> Premium unlock</span>
                    <h3 style="font-size:1.6rem;margin-bottom:.2rem"><?= e(usd((float)$asset['price_usd'])) ?></h3>
                    <p class="muted small">One-time payment. The file is added to your hub permanently.</p>

                    <?php if ($pending !== null): ?>
                        <div class="fact" style="margin-bottom: .8rem">
                            <span>Payment in progress</span>
                            <strong><?= e(crypto_amount((float)$pending['amount_crypto'])) ?> <?= e(strtoupper($pending['coin'])) ?></strong>
                            <div style="margin-top:.5rem"><?= status_badge($pending['status']) ?></div>
                        </div>
                        <a class="btn btn-gold btn-block" href="<?= url('/pay/' . $pending['id']) ?>">View the invoice <?= icon('arrow', 16) ?></a>
                    <?php elseif ($user === null): ?>
                        <p class="muted small">Sign in (email or just a wallet address) so the unlock can be attached to your account.</p>
                        <a class="btn btn-primary btn-block" href="<?= url('/login') ?>"><?= icon('users', 16) ?> Sign in to unlock</a>
                        <a class="btn btn-ghost btn-block" style="margin-top:.5rem" href="<?= url('/register') ?>">Create an account</a>
                    <?php else: ?>
                        <form method="post" action="<?= url('/pay/create') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="kind" value="unlock">
                            <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                            <div class="coin-picker" style="margin:.6rem 0 1rem">
                                <?php foreach (coins() as $key => $coin): ?>
                                    <label class="coin-option" style="--coin:<?= e($coin['color']) ?>">
                                        <input type="radio" name="coin" value="<?= e($key) ?>"<?= $key === 'btc' ? ' checked' : '' ?>>
                                        <span class="coin-dot" style="--coin:<?= e($coin['color']) ?>"></span>
                                        <span>
                                            <strong><?= e($coin['label']) ?></strong>
                                            <small><?= e(crypto_amount(usd_to_crypto((float)$asset['price_usd'], $key))) ?> <?= e($coin['symbol']) ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <button class="btn btn-gold btn-block" type="submit"><?= icon('lock', 16) ?> Generate invoice</button>
                        </form>
                        <p class="muted small" style="margin:.8rem 0 0">
                            You pay from your own wallet — the hub never touches your keys.
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="eyebrow"><?= icon('download', 14) ?> Ready to install</span>
                    <h3>Download this <?= e($premium ? 'premium pack' : 'item') ?></h3>
                    <p class="muted small">
                        <?= $premium && $unlocked ? 'Thanks for supporting the creator — your unlock is confirmed.' : 'Free to download, no account needed to grab the file.' ?>
                    </p>
                    <a class="btn btn-primary btn-block" href="<?= url('/download/' . $asset['id']) ?>"><?= icon('download', 16) ?> Download now</a>
                <?php endif; ?>

                <button class="btn btn-ghost btn-block<?= $bookmarked ? ' is-on' : '' ?>" style="margin-top:.6rem"
                        data-bookmark="<?= (int)$asset['id'] ?>" aria-pressed="<?= $bookmarked ? 'true' : 'false' ?>">
                    <?= icon('bookmark', 16) ?> <?= $bookmarked ? 'In your hub' : 'Bookmark' ?>
                </button>
            </div>

            <div class="panel">
                <div class="pay-facts">
                    <div class="fact"><span>Version</span><strong><?= e($asset['mc_version']) ?></strong></div>
                    <div class="fact"><span>Category</span><strong><?= e(category_label($asset['category'])) ?></strong></div>
                    <div class="fact"><span>Impact</span><strong><?= e(impact_label($asset['impact'])) ?></strong></div>
                    <div class="fact"><span>Author</span><strong><?= e($asset['author']) ?></strong></div>
                    <?php if ($bound !== null): ?>
                        <div class="fact"><span>Files</span><strong><?= e(source_label($bound['source'])) ?></strong></div>
                    <?php endif; ?>
                </div>
                <?php if ($bound !== null): ?>
                    <p class="muted small" style="margin:1rem 0 0">
                        The download is served live from
                        <?= e($bound['project_name'] !== '' ? $bound['project_name'] : source_label($bound['source'])) ?>
                        <?= $bound['version_id'] !== '' && $bound['version_label'] !== ''
                            ? '(' . e($bound['version_label']) . ') — the exact file the owner pinned.'
                            : '— always the newest release that matches this Minecraft version.' ?>
                    </p>
                <?php else: ?>
                    <p class="muted small" style="margin:1rem 0 0">
                        Demo catalogue: this item still serves a generated placeholder — the owner can link it to a
                        real Modrinth or CurseForge project from Admin &rarr; Download sources.
                    </p>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <?php if ($related !== []): ?>
        <section class="section">
            <div class="section-head">
                <h2 style="font-size:1.3rem;margin:0">More <?= e(category_label($asset['category'])) ?></h2>
                <a class="btn btn-ghost btn-sm" href="<?= url($backPath) ?>">Browse all <?= icon('arrow', 15) ?></a>
            </div>
            <?= asset_grid($related) ?>
        </section>
    <?php endif; ?>
</div>
