<?php
declare(strict_types=1);

$page_nav = 'home';
$page_title = 'Minecraft hub for players & server creators';

$stats = stats_overview();
$premiumPicks = db_all('SELECT * FROM assets WHERE is_premium = 1 ORDER BY rating DESC, downloads DESC LIMIT 3');
$communityPicks = db_all('SELECT * FROM assets WHERE section = "marketplace" AND is_premium = 0 ORDER BY downloads DESC LIMIT 4');
$heroAsset = db_all('SELECT * FROM assets WHERE category = "shaders" ORDER BY downloads DESC LIMIT 1')[0] ?? null;
?>

<section class="hero">
    <div class="wrap hero-inner">
        <div>
            <span class="eyebrow"><?= icon('cube', 15) ?> Player &amp; server-creator hub</span>
            <h1>Everything your world needs,<br><span class="grad">unlocked with crypto.</span></h1>
            <p class="hero-lead">
                Browse shaders, texture packs and performance modpacks, then pick up production-ready
                server configs — free from the community, or premium files unlocked anonymously with
                Bitcoin, Monero or Litecoin.
            </p>
            <div class="hero-cta">
                <a class="btn btn-primary btn-lg" href="<?= url('/library') ?>"><?= icon('cube', 18) ?> Browse the directory</a>
                <a class="btn btn-ghost btn-lg" href="<?= url('/marketplace') ?>">Server configs <?= icon('arrow', 18) ?></a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat"><strong data-count="<?= (int)$stats['downloads_total'] ?>">0</strong><span>downloads tracked</span></div>
                <div class="hero-stat"><strong data-count="<?= (int)$stats['assets_total'] ?>">0</strong><span>curated items</span></div>
                <div class="hero-stat"><strong data-count="<?= (int)$stats['users_total'] ?>">0</strong><span>members</span></div>
                <div class="hero-stat"><strong class="stat-value gold" data-count="<?= (float)$stats['revenue_usd'] ?>" data-decimals="2">0</strong><span>crypto tips settled</span></div>
            </div>
        </div>

        <?php if ($heroAsset !== null): ?>
            <div class="hero-visual">
                <img src="<?= e(thumb_url($heroAsset['slug'], 'shader', 900, 560)) ?>" alt="" width="900" height="560">
                <span class="hero-float one"><?= icon('bolt', 15) ?> <?= e(human_number((int)$heroAsset['downloads'])) ?> downloads today</span>
                <span class="hero-float two"><?= icon('wallet', 15) ?> BTC · XMR · LTC accepted</span>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="feature-row">
            <div class="feature">
                <span class="icon-wrap"><?= icon('cube', 20) ?></span>
                <h3>Visual asset directory</h3>
                <p>Filter by Minecraft version, category and PC impact to find a shader or pack your rig can actually run.</p>
            </div>
            <div class="feature gold">
                <span class="icon-wrap"><?= icon('shield', 20) ?></span>
                <h3>Configs that ship working</h3>
                <p>EssentialsX, LuckPerms and MythicMobs setups written by server admins, with the free ones ready to drop in.</p>
            </div>
            <div class="feature">
                <span class="icon-wrap"><?= icon('wallet', 20) ?></span>
                <h3>Anonymous crypto unlocks</h3>
                <p>No accounts on a payment processor, no card details — send coins, get the file.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div>
                <span class="eyebrow gold"><?= icon('star', 14) ?> Premium line-up</span>
                <h2>Worth the coins</h2>
                <p class="muted">Top-rated premium packs and configs, unlocked in a couple of clicks.</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= url('/marketplace') ?>">See premium configs <?= icon('arrow', 15) ?></a>
        </div>
        <?= asset_grid($premiumPicks) ?>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <div>
                <span class="eyebrow"><?= icon('users', 14) ?> Free &amp; community</span>
                <h2>Fresh from the community</h2>
                <p class="muted">Free configurations and data packs other admins shared this week.</p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= url('/library') ?>">Browse everything <?= icon('arrow', 15) ?></a>
        </div>
        <?= asset_grid($communityPicks) ?>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <span class="eyebrow"><?= icon('lock', 14) ?> How unlocking works</span>
        <h2>Buy anonymous, keep it simple</h2>
        <div class="steps" style="margin-top:1.2rem">
            <div class="step">
                <h3>Pick a coin</h3>
                <p>Every premium item shows a live amount in BTC, XMR or LTC converted from the price.</p>
            </div>
            <div class="step">
                <h3>Send and paste the tx id</h3>
                <p>Pay from any wallet to the address on the invoice, then drop in your transaction id.</p>
            </div>
            <div class="step">
                <h3>Get the file</h3>
                <p>The hub owner confirms the payment and your download unlocks on the spot.</p>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="panel" style="display:flex;align-items:center;justify-content:space-between;gap:1.4rem;flex-wrap:wrap">
            <div>
                <span class="eyebrow gold"><?= icon('cup', 14) ?> Buy me a coffee</span>
                <h3 style="margin-bottom:.2rem">Like someone's work? Send a tip.</h3>
                <p class="muted" style="margin:0">Tipping goes straight to the hub owner's wallet — no processor, no fees taken in the middle.</p>
            </div>
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
                <div class="coin-row">
                    <?php foreach (coins() as $coin): ?>
                        <span class="coin-pill" style="--coin:<?= e($coin['color']) ?>"><?= e($coin['symbol']) ?></span>
                    <?php endforeach; ?>
                </div>
                <a class="btn btn-gold" href="<?= url('/tip') ?>"><?= icon('cup', 16) ?> Tip with crypto</a>
            </div>
        </div>
    </div>
</section>
