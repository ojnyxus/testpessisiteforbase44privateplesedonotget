<?php
declare(strict_types=1);

$page_nav = 'library';
$page_title = 'Modpack & shader directory';

$filters = [
    'section'  => 'library',
    'search'   => trim((string)($_GET['search'] ?? '')),
    'version'  => trim((string)($_GET['version'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'impact'   => trim((string)($_GET['impact'] ?? '')),
    'sort'     => trim((string)($_GET['sort'] ?? 'popular')),
];

$assets = find_assets($filters);
$versions = mc_versions();
$libraryCategories = [
    'shaders'              => CATEGORY_LABELS['shaders'],
    'texture_packs'        => CATEGORY_LABELS['texture_packs'],
    'performance_modpacks' => CATEGORY_LABELS['performance_modpacks'],
];
?>

<section class="wrap page-head">
    <span class="eyebrow"><?= icon('cube', 15) ?> Visual assets</span>
    <h1>Modpack &amp; shader directory</h1>
    <p class="muted" style="max-width:70ch">
        Visual previews, an honest PC impact rating and a direct download for every entry. Shaders, texture packs
        and performance modpacks — filter by the version you actually play.
    </p>
</section>

<section class="wrap section" style="padding-top:1.6rem">
    <form class="filter-bar" id="filter-form" data-section="library" method="get" action="<?= url('/library') ?>">
        <div class="field search-wrap">
            <label for="search">Search</label>
            <?= icon('star', 15) ?>
            <input class="input" type="search" id="search" name="search" placeholder="Shaders, packs, authors…"
                   value="<?= e($filters['search']) ?>">
        </div>
        <div class="field">
            <label for="version">Version</label>
            <select class="select" id="version" name="version">
                <option value="">Any</option>
                <?php foreach ($versions as $version): ?>
                    <option value="<?= e($version) ?>"<?= $filters['version'] === $version ? ' selected' : '' ?>><?= e($version) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="category">Category</label>
            <select class="select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($libraryCategories as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $filters['category'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="impact">PC impact</label>
            <select class="select" id="impact" name="impact">
                <option value="">Any impact</option>
                <?php foreach (IMPACT_LABELS as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $filters['impact'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="sort">Sort</label>
            <select class="select" id="sort" name="sort">
                <option value="popular"<?= $filters['sort'] === 'popular' ? ' selected' : '' ?>>Most downloaded</option>
                <option value="rating"<?= $filters['sort'] === 'rating' ? ' selected' : '' ?>>Best rated</option>
                <option value="newest"<?= $filters['sort'] === 'newest' ? ' selected' : '' ?>>Newest</option>
                <option value="name"<?= $filters['sort'] === 'name' ? ' selected' : '' ?>>A–Z</option>
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn-primary" type="submit">Apply filters</button>
        </div>
    </form>

    <div class="section-head">
        <h2 style="font-size:1.1rem;margin:0" id="result-count"><?= count($assets) ?> result<?= count($assets) === 1 ? '' : 's' ?></h2>
        <span class="muted small">Downloads are tracked per item so creators can see what lands.</span>
    </div>

    <div id="results"><?= asset_grid($assets) ?></div>
</section>
