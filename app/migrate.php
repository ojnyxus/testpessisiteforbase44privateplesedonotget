<?php
declare(strict_types=1);

/**
 * One-shot schema + seed runner. Started by the `db-init` compose service, which
 * exits before the web service boots. Safe to run repeatedly: it only creates what
 * is missing and only seeds an empty catalogue.
 *
 * Usage: php app/migrate.php
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/schema.php';

function migrate_say(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

/**
 * Adds columns an older database is missing (MySQL 8 has no
 * "ADD COLUMN IF NOT EXISTS", so we check information_schema first).
 */
function ensure_columns(string $table, array $columns): void
{
    $existing = array_column(
        db_all(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        ),
        'COLUMN_NAME'
    );

    foreach ($columns as $name => $definition) {
        if (in_array($name, $existing, true)) {
            continue;
        }
        db_run('ALTER TABLE `' . $table . '` ADD COLUMN `' . $name . '` ' . $definition);
        migrate_say("column added: {$table}.{$name}");
    }
}

try {
    foreach (schema_statements() as $name => $sql) {
        db_run($sql);
        migrate_say("schema ok: {$name}");
    }

    // Links to real files on Modrinth / CurseForge (see app/sources.php).
    ensure_columns('assets', asset_source_columns());

    $existing = (int)db_value('SELECT COUNT(*) FROM settings');
    $inserted = 0;
    foreach (default_settings() as $key => $value) {
        if (db_value('SELECT COUNT(*) FROM settings WHERE setting_key = ?', [$key]) === 0) {
            db_run('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, (string)$value]);
            $inserted++;
        }
    }
    migrate_say($existing === 0 ? "settings seeded: {$inserted}" : "settings present: kept");

    if ((int)db_value('SELECT COUNT(*) FROM assets') === 0) {
        $count = 0;
        foreach (demo_assets() as $asset) {
            db_run(
                'INSERT INTO assets (slug, name, author, section, category, mc_version, summary, description, includes,
                                     impact, is_premium, price_usd, download_url, file_name, downloads, rating, created_at,
                                     `source`, source_project_id, source_project_slug, source_project_name)
                 VALUES (:slug, :name, :author, :section, :category, :mc_version, :summary, :description, :includes,
                         :impact, :is_premium, :price_usd, :download_url, :file_name, :downloads, :rating, :created_at,
                         :source, :source_project_id, :source_project_slug, :source_project_name)',
                $asset
            );
            $count++;
        }
        migrate_say("catalogue seeded: {$count} items");
    } else {
        migrate_say('catalogue present: kept');
    }

    migrate_say('migration complete');
} catch (Throwable $e) {
    fwrite(STDERR, 'migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
