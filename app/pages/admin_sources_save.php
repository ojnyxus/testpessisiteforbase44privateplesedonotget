<?php
declare(strict_types=1);

/** Links (or unlinks) a catalogue item to a Modrinth / CurseForge project. */

require_admin();

$assetId = (int)($_POST['asset_id'] ?? 0);
$back = '/admin/sources' . ($assetId > 0 ? '?asset=' . $assetId : '');

if (!csrf_ok()) {
    flash('Your session expired — try again.', 'error');
    redirect($back);
}

$asset = $assetId > 0 ? asset_by_id($assetId) : null;
if ($asset === null) {
    flash('That item no longer exists.', 'error');
    redirect('/admin/sources');
}

if ((string)($_POST['action'] ?? '') === 'detach') {
    clear_asset_source($assetId);
    flash($asset['name'] . ' is back on the generated placeholder file.', 'success');
    redirect($back);
}

$source = (string)($_POST['source'] ?? '');
if (!source_known($source)) {
    flash('Pick Modrinth or CurseForge.', 'error');
    redirect($back);
}

$projectId = trim((string)($_POST['project_id'] ?? ''));
if ($projectId === '') {
    flash('Choose a project first.', 'error');
    redirect($back . '&source=' . $source);
}

$resolved = bind_asset_source($assetId, [
    'source'        => $source,
    'project_id'    => $projectId,
    'project_slug'  => trim((string)($_POST['project_slug'] ?? '')),
    'project_name'  => trim((string)($_POST['project_name'] ?? '')),
    'version_id'    => trim((string)($_POST['version_id'] ?? '')),
    'version_label' => trim((string)($_POST['version_label'] ?? '')),
    'file_name'     => trim((string)($_POST['file_name'] ?? '')),
    'file_url'      => trim((string)($_POST['file_url'] ?? '')),
]);

if ($resolved['ok']) {
    flash($asset['name'] . ' now serves ' . $resolved['file'] . ' from ' . source_label($source) . '.', 'success');
} else {
    flash('Linked to ' . source_label($source) . ', but no file could be resolved: ' . $resolved['error'], 'warning');
}

redirect($back . '&source=' . $source);
