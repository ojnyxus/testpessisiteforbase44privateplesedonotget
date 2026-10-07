<?php
declare(strict_types=1);

/**
 * Admin: link catalogue items to their real project on Modrinth or CurseForge.
 * See app/sources.php for the API layer and /download/{id} for the serving side.
 */
$page_nav = 'admin';
$page_title = 'Download sources';

require_admin();

$assetId = (int)($_GET['asset'] ?? 0);
$asset = $assetId > 0 ? asset_by_id($assetId) : null;

$source = (string)($_GET['source'] ?? 'modrinth');
if (!source_known($source)) {
    $source = 'modrinth';
}

$query       = trim((string)($_GET['q'] ?? ''));
$projectRef  = trim((string)($_GET['project'] ?? ''));
$projectSlug = trim((string)($_GET['project_slug'] ?? ''));
$projectName = trim((string)($_GET['project_name'] ?? ''));

$searchResults = null;
$versionList   = null;
$verify        = null;

if ($asset !== null) {
    if (isset($_GET['verify'])) {
        $verify = source_resolve($asset, true);
    }

    if ($projectRef !== '') {
        $versionList = $source === 'modrinth' ? modrinth_versions($projectRef) : curseforge_files((int)$projectRef);
    } elseif ($query !== '') {
        $searchResults = $source === 'modrinth' ? modrinth_search($query) : curseforge_search($query);
    }
}

$versions = [];
if ($versionList !== null && $versionList['ok']) {
    $versions = $versionList['versions'];
    usort($versions, static fn(array $a, array $b): int => strcmp((string)$b['published'], (string)$a['published']));
}

$binding = $asset !== null ? asset_source($asset) : null;

$catalogue = [];
$linkedCount = 0;
if ($asset === null) {
    $catalogue = db_all('SELECT * FROM assets ORDER BY section, name');
    foreach ($catalogue as $item) {
        if (asset_source($item) !== null) {
            $linkedCount++;
        }
    }
}

/** App-relative link back to this screen. */
$link = static fn(array $params): string => url('/admin/sources?' . http_build_query($params));
$curseforgeBlocked = $source === 'curseforge' && !curseforge_available();
?>

<?php if ($asset === null): ?>
    <section class="wrap page-head">
        <span class="eyebrow"><?= icon('download', 15) ?> Download sources</span>
        <h1>Serve real jars and archives</h1>
        <p class="muted" style="max-width:76ch">
            Link a catalogue item to its project on Modrinth or CurseForge and buyers download the real file.
            Modrinth is open and needs no credentials; CurseForge needs an API key
            (<span class="mono">CURSEFORGE_API_KEY</span>). Items left unlinked keep serving the generated
            placeholder package.
        </p>
        <div class="row-actions" style="margin-top:1rem">
            <a class="btn btn-ghost btn-sm" href="<?= url('/admin') ?>"><?= icon('chart', 15) ?> Overview</a>
            <a class="btn btn-ghost btn-sm" href="<?= url('/admin/settings') ?>"><?= icon('shield', 15) ?> Wallet &amp; settings</a>
        </div>
    </section>

    <section class="wrap section" style="padding-top:1.4rem">
        <div class="panel">
            <div class="panel-head">
                <h3><?= icon('cube', 16) ?> Catalogue</h3>
                <span class="muted small"><?= $linkedCount ?> of <?= count($catalogue) ?> items linked</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Item</th><th>Where</th><th>Category</th><th>Files</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($catalogue as $item): ?>
                        <?php $itemSource = asset_source($item); ?>
                        <tr>
                            <td><a href="<?= url('/asset/' . $item['slug']) ?>"><?= e($item['name']) ?></a>
                                <div class="muted small">by <?= e($item['author']) ?></div></td>
                            <td class="muted small"><?= $item['section'] === 'library' ? 'Directory' : 'Marketplace' ?></td>
                            <td class="muted small"><?= e(category_label($item['category'])) ?> · <?= e($item['mc_version']) ?></td>
                            <td>
                                <?php if ($itemSource === null): ?>
                                    <span class="muted small">placeholder</span>
                                <?php else: ?>
                                    <span class="badge badge-gold"><?= e(source_label($itemSource['source'])) ?></span>
                                    <?php if ($itemSource['version_label'] !== ''): ?>
                                        <div class="muted small"><?= e($itemSource['version_label']) ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td><a class="btn btn-ghost btn-sm" href="<?= e($link(['asset' => $item['id']])) ?>">
                                <?= icon('download', 14) ?> Link files</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="wrap page-head">
        <div class="breadcrumb">
            <a href="<?= url('/admin/sources') ?>">Download sources</a>
            <span>/</span>
            <span>Link files</span>
        </div>
        <h1><?= e($asset['name']) ?></h1>
        <p class="muted"><?= e(category_label($asset['category'])) ?> · Minecraft <?= e($asset['mc_version']) ?>
            · by <?= e($asset['author']) ?></p>
        <div class="row-actions" style="margin-top:1rem">
            <a class="btn btn-ghost btn-sm" href="<?= url('/asset/' . $asset['slug']) ?>"><?= icon('cube', 15) ?> View item</a>
            <a class="btn btn-ghost btn-sm" href="<?= url('/admin/sources') ?>"><?= icon('arrow', 15) ?> All items</a>
        </div>
    </section>

    <section class="wrap section" style="padding-top:1.4rem">
        <div class="panel">
            <div class="panel-head"><h3><?= icon('lock', 16) ?> Current link</h3></div>
            <?php if ($binding === null): ?>
                <p class="muted small" style="margin:0">
                    Not linked yet — buyers get the generated placeholder package. Search for the project below.
                </p>
            <?php else: ?>
                <div class="pay-facts">
                    <div class="fact"><span>Platform</span><strong><?= e(source_label($binding['source'])) ?></strong></div>
                    <div class="fact"><span>Project</span><strong><?= e($binding['project_name'] !== '' ? $binding['project_name'] : $binding['project_slug']) ?></strong></div>
                    <div class="fact"><span>Version</span><strong><?= e($binding['version_id'] !== ''
                            ? trim($binding['version_label'] . ' (pinned)', ' ()')
                            : 'newest matching release') ?></strong></div>
                    <div class="fact"><span>Serving</span><strong><?= e($binding['file_name'] !== '' ? $binding['file_name'] : 'not resolved yet') ?></strong></div>
                </div>
                <div class="row-actions" style="margin-top:1rem">
                    <a class="btn btn-ghost btn-sm" href="<?= e($link(['asset' => $asset['id'], 'source' => $binding['source'], 'verify' => 1])) ?>">
                        <?= icon('check', 15) ?> Verify now</a>
                    <form method="post" action="<?= url('/admin/sources') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="asset_id" value="<?= (int)$asset['id'] ?>">
                        <input type="hidden" name="action" value="detach">
                        <button class="btn btn-danger btn-sm" type="submit">Unlink</button>
                    </form>
                </div>
                <?php if ($binding['checked_at'] !== ''): ?>
                    <p class="muted small" style="margin:1rem 0 0">Last resolved <?= e(time_ago($binding['checked_at'])) ?>.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($verify !== null): ?>
            <div class="panel" style="margin-top:1rem">
                <?php if ($verify['ok']): ?>
                    <p style="margin:0"><?= icon('check', 16) ?> Resolves to
                        <strong><?= e((string)$verify['file']) ?></strong>
                        <?= $verify['cached'] ? '(cached link)' : '(fresh from ' . e(source_label($binding['source'])) . ')' ?>.</p>
                <?php else: ?>
                    <p style="margin:0"><?= icon('close', 16) ?> <?= e($verify['error']) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="wrap section">
        <div class="panel">
            <div class="panel-head">
                <h3><?= icon('download', 16) ?> Pick a project</h3>
                <div class="row-actions">
                    <?php foreach (SOURCE_LABELS as $key => $label): ?>
                        <a class="btn btn-ghost btn-sm<?= $key === $source ? ' is-on' : '' ?>"
                           href="<?= e($link(['asset' => $asset['id'], 'source' => $key])) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($curseforgeBlocked): ?>
                <p class="muted small" style="margin:0 0 1rem">
                    No CurseForge API key is configured, so this tab cannot search yet. Add
                    <span class="mono">CURSEFORGE_API_KEY</span> to the app secrets — Modrinth works without one.
                </p>
            <?php endif; ?>

            <form class="row-actions" method="get" action="<?= url('/admin/sources') ?>">
                <input type="hidden" name="asset" value="<?= (int)$asset['id'] ?>">
                <input type="hidden" name="source" value="<?= e($source) ?>">
                <div class="field" style="flex:1">
                    <label for="q">Search <?= e(source_label($source)) ?></label>
                    <input class="input" type="search" id="q" name="q" value="<?= e($query) ?>"
                           placeholder="<?= e($asset['name']) ?>">
                </div>
                <button class="btn btn-primary" type="submit">Search</button>
            </form>

            <?php if ($searchResults !== null): ?>
                <div style="margin-top:1.2rem">
                    <?php if (!$searchResults['ok']): ?>
                        <p class="muted small" style="margin:0"><?= e($searchResults['error']) ?></p>
                    <?php elseif ($searchResults['results'] === []): ?>
                        <p class="muted small" style="margin:0">Nothing matched “<?= e($query) ?>”.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead><tr><th>Project</th><th>Author</th><th>Type</th><th>Downloads</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($searchResults['results'] as $result): ?>
                                    <tr>
                                        <td><strong><?= e($result['name']) ?></strong>
                                            <div class="muted small"><?= e($result['summary']) ?></div></td>
                                        <td class="muted"><?= e($result['author']) ?></td>
                                        <td class="muted small"><?= e($result['type']) ?></td>
                                        <td class="muted small"><?= e(human_number($result['downloads'])) ?></td>
                                        <td><a class="btn btn-ghost btn-sm" href="<?= e($link([
                                                'asset' => $asset['id'],
                                                'source' => $source,
                                                'q' => $query,
                                                'project' => $result['id'],
                                                'project_slug' => $result['slug'],
                                                'project_name' => $result['name'],
                                            ])) ?>">Files <?= icon('arrow', 14) ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($projectRef !== ''): ?>
                <?php
                $projectLabel = $projectName !== '' ? $projectName : ($projectSlug !== '' ? $projectSlug : $projectRef);
                $attachFields = [
                    'action'       => 'attach',
                    'asset_id'     => (int)$asset['id'],
                    'source'       => $source,
                    'project_id'   => $projectRef,
                    'project_slug' => $projectSlug,
                    'project_name' => $projectName,
                ];
                ?>
                <div class="panel-head" style="margin-top:1.6rem">
                    <h3><?= e($projectLabel) ?></h3>
                    <form method="post" action="<?= url('/admin/sources') ?>">
                        <?= csrf_field() ?>
                        <?php foreach ($attachFields as $name => $value): ?>
                            <input type="hidden" name="<?= e($name) ?>" value="<?= e((string)$value) ?>">
                        <?php endforeach; ?>
                        <button class="btn btn-primary btn-sm" type="submit" <?= $curseforgeBlocked ? 'disabled' : '' ?>>
                            <?= icon('bolt', 15) ?> Follow newest matching release</button>
                    </form>
                </div>
                <p class="muted small" style="margin:.4rem 0 0">
                    Follow-the-release keeps the item current on its own; pinning a file below freezes it.
                </p>

                <?php if ($versionList !== null && !$versionList['ok']): ?>
                    <p class="muted small" style="margin:1rem 0 0"><?= e($versionList['error']) ?></p>
                <?php elseif ($versions === []): ?>
                    <p class="muted small" style="margin:1rem 0 0">That project publishes no files.</p>
                <?php else: ?>
                    <div class="table-wrap" style="margin-top:1rem">
                        <table class="table">
                            <thead><tr><th>Version</th><th>Minecraft</th><th>Loaders</th><th>File</th><th>Published</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($versions, 0, 25) as $version): ?>
                                <tr>
                                    <td><strong><?= e($version['label']) ?></strong>
                                        <?php if ($version['channel'] !== ''): ?>
                                            <div class="muted small"><?= e($version['channel']) ?></div>
                                        <?php endif; ?></td>
                                    <td class="muted small"><?= e(implode(', ', array_slice($version['game_versions'], 0, 4))) ?></td>
                                    <td class="muted small"><?= e(implode(', ', array_slice($version['loaders'], 0, 3))) ?></td>
                                    <td class="small mono" style="max-width:230px;overflow:hidden;text-overflow:ellipsis">
                                        <?= e($version['file_name']) ?>
                                        <?php if ($version['file_size'] > 0): ?>
                                            <div class="muted small"><?= e(human_bytes($version['file_size'])) ?></div>
                                        <?php endif; ?></td>
                                    <td class="muted small"><?= e($version['published'] !== ''
                                            ? date('M j, Y', (int)strtotime($version['published']))
                                            : '—') ?></td>
                                    <td>
                                        <form method="post" action="<?= url('/admin/sources') ?>">
                                            <?= csrf_field() ?>
                                            <?php foreach ($attachFields as $name => $value): ?>
                                                <input type="hidden" name="<?= e($name) ?>" value="<?= e((string)$value) ?>">
                                            <?php endforeach; ?>
                                            <input type="hidden" name="version_id" value="<?= e($version['id']) ?>">
                                            <input type="hidden" name="version_label" value="<?= e($version['label']) ?>">
                                            <input type="hidden" name="file_name" value="<?= e($version['file_name']) ?>">
                                            <input type="hidden" name="file_url" value="<?= e($version['file_url']) ?>">
                                            <button class="btn btn-ok btn-sm" type="submit" <?= $curseforgeBlocked ? 'disabled' : '' ?>>
                                                Pin this file</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($versions) > 25): ?>
                        <p class="muted small" style="margin:1rem 0 0">Showing the 25 newest of
                            <?= count($versions) ?> published files.</p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
