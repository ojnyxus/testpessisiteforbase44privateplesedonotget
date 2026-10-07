<?php
declare(strict_types=1);

/**
 * Reusable server-side rendering helpers. The same card markup is mirrored in
 * public/assets/js/app.js so filtered results look identical to the initial render.
 */

function bookmarked_id_set(): array
{
    static $ids = null;

    if ($ids === null) {
        $user = current_user();
        $ids = $user === null ? [] : bookmark_ids((int)$user['id']);
    }

    return $ids;
}

function asset_card(array $asset, bool $showUnlock = true): string
{
    $bookmarked = in_array((int)$asset['id'], bookmarked_id_set(), true);
    $premium = (bool)$asset['is_premium'];
    $user = current_user();
    $unlocked = $premium && $user !== null && has_unlocked((int)$user['id'], (int)$asset['id']);
    $href = url('/asset/' . $asset['slug']);

    ob_start();
    ?>
    <article class="asset-card<?= $premium ? ' is-premium' : '' ?>" data-card-id="<?= (int)$asset['id'] ?>">
        <a class="asset-thumb" href="<?= e($href) ?>">
            <img src="<?= e(thumb_url($asset['slug'], thumb_kind($asset), 640, 360)) ?>" alt="" loading="lazy" width="640" height="360">
            <?php if ($premium): ?>
                <span class="badge badge-gold">Premium</span>
            <?php endif; ?>
            <span class="badge badge-impact impact-<?= e($asset['impact']) ?>"><?= e(impact_label($asset['impact'])) ?></span>
        </a>
        <div class="asset-body">
            <div class="chip-row">
                <span class="chip"><?= e(category_label($asset['category'])) ?></span>
                <span class="chip chip-ghost"><?= e($asset['mc_version']) ?></span>
            </div>
            <h3 class="asset-title"><a href="<?= e($href) ?>"><?= e($asset['name']) ?></a></h3>
            <p class="asset-summary"><?= e($asset['summary']) ?></p>
            <div class="asset-meta">
                <span>by <?= e($asset['author']) ?></span>
                <span class="dot"></span>
                <span><?= icon('star', 13) ?> <?= e(number_format((float)$asset['rating'], 1)) ?></span>
                <span class="dot"></span>
                <span><?= e(human_number((int)$asset['downloads'])) ?> dl</span>
            </div>
            <div class="asset-actions">
                <?php if ($premium && !$unlocked && $showUnlock): ?>
                    <a class="btn btn-gold btn-sm grow" href="<?= e($href) ?>">
                        <?= icon('lock', 15) ?> Unlock <?= e(usd((float)$asset['price_usd'])) ?>
                    </a>
                <?php else: ?>
                    <a class="btn btn-primary btn-sm grow" href="<?= url('/download/' . $asset['id']) ?>" data-download="<?= (int)$asset['id'] ?>">
                        <?= icon('download', 15) ?> Download
                    </a>
                <?php endif; ?>
                <button class="btn btn-ghost btn-sm icon-btn<?= $bookmarked ? ' is-on' : '' ?>"
                        data-bookmark="<?= (int)$asset['id'] ?>"
                        aria-pressed="<?= $bookmarked ? 'true' : 'false' ?>"
                        title="<?= $bookmarked ? 'Remove bookmark' : 'Bookmark this' ?>">
                    <?= icon('bookmark', 15) ?>
                </button>
            </div>
        </div>
    </article>
    <?php

    return (string)ob_get_clean();
}

/** Maps a catalogue category to a generated preview style. */
function thumb_kind(array $asset): string
{
    return match ($asset['category']) {
        'shaders'              => 'shader',
        'texture_packs'        => 'pack',
        'performance_modpacks' => 'pack',
        'configs'              => 'config',
        'schematics'           => 'schematic',
        'datapacks'            => 'datapack',
        default                => 'shader',
    };
}

function asset_grid(array $assets): string
{
    if ($assets === []) {
        return '<p class="empty-state">' . icon('cube', 28) . ' Nothing matches those filters yet. Try widening them.</p>';
    }

    return '<div class="asset-grid">' . implode('', array_map(
        static fn(array $asset): string => asset_card($asset),
        $assets
    )) . '</div>';
}

function status_badge(string $status): string
{
    $labels = [
        'pending'   => ['Awaiting payment', 'warn'],
        'submitted' => ['In review', 'warn'],
        'confirmed' => ['Confirmed', 'ok'],
        'rejected'  => ['Rejected', 'bad'],
    ];
    [$label, $tone] = $labels[$status] ?? [ucfirst($status), 'warn'];

    return '<span class="status status-' . e($tone) . '">' . e($label) . '</span>';
}
