<?php
declare(strict_types=1);

$page_nav = 'marketplace';
$page_title = 'Config & script marketplace';

$filters = [
    'section'  => 'marketplace',
    'search'   => trim((string)($_GET['search'] ?? '')),
    'version'  => trim((string)($_GET['version'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'impact'   => trim((string)($_GET['impact'] ?? '')),
    'sort'     => trim((string)($_GET['sort'] ?? 'popular')),
    'premium'  => '0',
];

$assets = find_assets($filters);
$versions = mc_versions();
$marketCategories = [
    'configs'    => CATEGORY_LABELS['configs'],
    'datapacks'  => CATEGORY_LABELS['datapacks'],
    'schematics' => CATEGORY_LABELS['schematics'],
];
?>

<section class="wrap page-head">
    <span class="eyebrow gold"><?= icon('shield', 15) ?> For server admins</span>
    <h1>Config &amp; script marketplace</h1>
    <p class="muted" style="max-width:72ch">
        Ready-to-use plugin configuration for EssentialsX, LuckPerms and MythicMobs, plus custom data packs and
        schematics. Community configs are free; premium files are locked behind an anonymous crypto unlock.
    </p>
</section>

<section class="wrap section" style="padding-top:1.6rem">
    <div class="section-head" style="align-items:center">
        <div class="tabs" role="tablist">
            <button class="tab is-active" data-tab="free" role="tab">Free community</button>
            <button class="tab" data-tab="premium" role="tab">Premium</button>
        </div>
        <span class="muted small"><?= (int)db_value('SELECT COUNT(*) FROM assets WHERE is_premium = 1') ?> premium items ·
            <?= (int)db_value('SELECT COUNT(*) FROM assets WHERE is_premium = 0') ?> free</span>
    </div>

    <p class="muted" id="tab-blurb" style="margin-top:-.6rem">
        Free, community-shared configurations and data packs. Install and go.
    </p>

    <form class="filter-bar" id="filter-form" data-section="marketplace" data-premium="0"
          method="get" action="<?= url('/marketplace') ?>">
        <div class="field search-wrap">
            <label for="search">Search</label>
            <?= icon('star', 15) ?>
            <input class="input" type="search" id="search" name="search" placeholder="EssentialsX, LuckPerms, dungeons…"
                   value="<?= e($filters['search']) ?>">
        </div>
        <div class="field">
            <label for="version">Version</label>
            <select class="select" id="version" name="version">
                <option value="">Any</option>
                <?php foreach ($versions as $version): ?>
                    <option value="<?= e($version) ?>"><?= e($version) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="category">Category</label>
            <select class="select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($marketCategories as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="impact">PC impact</label>
            <select class="select" id="impact" name="impact">
                <option value="">Any impact</option>
                <?php foreach (IMPACT_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="sort">Sort</label>
            <select class="select" id="sort" name="sort">
                <option value="popular">Most downloaded</option>
                <option value="rating">Best rated</option>
                <option value="newest">Newest</option>
                <option value="price">Premium first</option>
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">Apply filters</button>
        </div>
    </form>

    <div class="section-head">
        <h2 style="font-size:1.1rem;margin:0" id="result-count"><?= count($assets) ?> result<?= count($assets) === 1 ? '' : 's' ?></h2>
        <span class="pill-note"><?= icon('lock', 14) ?> Premium unlocks are settled in crypto</span>
    </div>

    <div id="results"><?= asset_grid($assets) ?></div>
</section>
