<?php
declare(strict_types=1);

/**
 * Schema + demo catalogue.
 *
 * `assets` holds both hubs (directory and marketplace) — the `section` column decides
 * which page lists it and `is_premium` drives the free/premium tabs and paywall.
 * `payments` holds every crypto transaction: premium unlocks (kind=unlock, linked to an
 * asset) and tips (kind=tip). They start as `pending`, then the owner confirms or rejects
 * them from the admin panel — nothing is granted before a human confirms on-chain receipt.
 */
function schema_statements(): array
{
    return [
        'users' => <<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                email VARCHAR(190) NULL UNIQUE,
                wallet_address VARCHAR(160) NULL UNIQUE,
                password_hash VARCHAR(255) NULL,
                role ENUM('user','admin') NOT NULL DEFAULT 'user',
                created_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                INDEX idx_last_seen (last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        'settings' => <<<SQL
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(64) PRIMARY KEY,
                setting_value TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        'assets' => <<<SQL
            CREATE TABLE IF NOT EXISTS assets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(150) NOT NULL UNIQUE,
                name VARCHAR(150) NOT NULL,
                author VARCHAR(80) NOT NULL,
                section ENUM('library','marketplace') NOT NULL,
                category ENUM('shaders','texture_packs','performance_modpacks','configs','datapacks','schematics') NOT NULL,
                mc_version VARCHAR(16) NOT NULL,
                summary VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                includes TEXT NOT NULL,
                impact ENUM('low','medium','high') NOT NULL DEFAULT 'low',
                is_premium TINYINT(1) NOT NULL DEFAULT 0,
                price_usd DECIMAL(8,2) NOT NULL DEFAULT 0,
                download_url VARCHAR(500) NULL,
                file_name VARCHAR(160) NOT NULL,
                downloads INT UNSIGNED NOT NULL DEFAULT 0,
                rating DECIMAL(3,2) NOT NULL DEFAULT 5.00,
                created_at DATETIME NOT NULL,
                INDEX idx_section (section),
                INDEX idx_filters (section, category, mc_version, impact),
                INDEX idx_premium (is_premium)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        // The `source*` columns that link an item to Modrinth/CurseForge are added
        // by asset_source_columns() — see the note there.
        'bookmarks' => <<<SQL
            CREATE TABLE IF NOT EXISTS bookmarks (
                user_id INT UNSIGNED NOT NULL,
                asset_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, asset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        'downloads' => <<<SQL
            CREATE TABLE IF NOT EXISTS downloads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                asset_id INT UNSIGNED NOT NULL,
                ip_hash CHAR(40) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_created (created_at),
                INDEX idx_asset (asset_id),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        'payments' => <<<SQL
            CREATE TABLE IF NOT EXISTS payments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                kind ENUM('unlock','tip') NOT NULL,
                user_id INT UNSIGNED NULL,
                asset_id INT UNSIGNED NULL,
                label VARCHAR(120) NOT NULL,
                message VARCHAR(255) NULL,
                coin ENUM('btc','xmr','ltc') NOT NULL,
                address VARCHAR(200) NOT NULL,
                amount_crypto DECIMAL(24,8) NOT NULL,
                amount_usd DECIMAL(8,2) NOT NULL,
                reference VARCHAR(24) NOT NULL UNIQUE,
                status ENUM('pending','submitted','confirmed','rejected') NOT NULL DEFAULT 'pending',
                txid VARCHAR(200) NULL,
                created_at DATETIME NOT NULL,
                settled_at DATETIME NULL,
                INDEX idx_status (status),
                INDEX idx_kind (kind),
                INDEX idx_user (user_id),
                INDEX idx_asset (asset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
    ];
}

/**
 * Columns linking an asset to a real file on Modrinth or CurseForge. They are
 * added with ALTER (see ensure_columns() in migrate.php) so an existing
 * database picks them up on the next `up`, without a re-seed.
 *
 * `source_version_id` is the admin's pin (optional); `source_file_id` is the
 * version/file the hub last resolved, so an unpinned link keeps following the
 * newest matching release.
 */
function asset_source_columns(): array
{
    return [
        'source'               => "VARCHAR(16) NOT NULL DEFAULT ''",
        'source_project_id'    => 'VARCHAR(64) NULL',
        'source_project_slug'  => 'VARCHAR(150) NULL',
        'source_project_name'  => 'VARCHAR(150) NULL',
        'source_version_id'    => 'VARCHAR(64) NULL',
        'source_file_id'       => 'VARCHAR(64) NULL',
        'source_version_label' => 'VARCHAR(64) NULL',
        'source_file_name'     => 'VARCHAR(200) NULL',
        'source_file_url'      => 'VARCHAR(700) NULL',
        'source_checked_at'    => 'DATETIME NULL',
    ];
}

/**
 * Demo items that exist for real on Modrinth, so a freshly seeded catalogue can
 * serve actual jars and archives. [asset slug => [project slug, project name]].
 */
function demo_sources(): array
{
    return [
        'complementary-reimagined' => ['complementary-reimagined', 'Complementary Shaders - Reimagined'],
        'bsl-shaders'              => ['bsl-shaders', 'BSL Shaders'],
        'photon-shaders'           => ['photon-shader', 'Photon Shaders'],
        'faithful-32x'             => ['faithful-32x', 'Faithful 32x'],
        'sodium-lithium-boost'     => ['sodium', 'Sodium'],
        'fabulously-optimized'     => ['fabulously-optimized', 'Fabulously Optimized'],
        'distant-horizons'         => ['distanthorizons', 'Distant Horizons'],
    ];
}

/** Placeholder receiving addresses — clearly marked, replaced by the owner in Admin -> Settings. */
function default_settings(): array
{
    return [
        'site_name'      => env_value('APP_NAME', 'BlockForge Hub'),
        'site_tagline'   => env_value('APP_TAGLINE', 'Modpacks, shaders and server configs for Minecraft'),
        'address_btc'    => 'bc1qreplace-with-your-own-btc-address-000000',
        'address_xmr'    => '4ReplaceWithYourOwnMoneroAddress0000000000000000000000000000000000',
        'address_ltc'    => 'ltc1qreplace-with-your-own-ltc-address-000000',
        'rate_btc'       => '62000',
        'rate_xmr'       => '165',
        'rate_ltc'       => '85',
        'coffee_url'     => '',
        'confirmations'  => '1',
        'tip_presets'    => '3,5,10,25',
    ];
}

function demo_assets(): array
{
    $rows = [];

    $library = [
        ['Complementary Reimagined', 'Complementary', 'shaders', '1.21', 'high', 0, 0,
            'Soft, filmic lighting with believable water and a strong day/night mood.',
            'A community favourite shader that keeps a natural look instead of over-saturating the world. Good performance scaling and a huge set of in-game toggles.',
            "Custom sky & clouds\nWave and reflection system\nVolumetric light shafts\nQuality presets up to Ultra"],
        ['BSL Shaders', 'CaptTatsu', 'shaders', '1.20', 'medium', 0, 0,
            'Balanced shaders tuned for mid-range GPUs — the safe pick for survival servers.',
            'Well-known balanced shader pack with clean shadows and readable lighting for building and PvP alike. Runs comfortably on older cards at reduced quality.',
            "Config preset: Fast / Balanced / Quality\nBloom and DOF toggles\nRain and wet-surface effects\nWorks with Iris and OptiFine"],
        ['Photon Shaders', 'SixthSurge', 'shaders', '1.21', 'high', 0, 0,
            'Ray-traced look with colour-graded sunsets and heavy volumetric fog.',
            'High-end shader aimed at modern GPUs, built for cinematic screenshots. Expect a real frame cost, especially with volumetric clouds enabled.',
            "Ray-traced style reflections\nVolumetric clouds and fog\nColour grading per dimension\nScreenshot-ready defaults"],
        ['Bare Bones', 'RiskyRuby', 'texture_packs', '1.21', 'low', 0, 0,
            'Minimal, flat-texture pack that sharpens the classic look.',
            'Strips textures down to flat colours for a clean, modern-fresh aesthetic. Also a great base for creators building their own pack.',
            "1.21 block coverage\nFlat colour palette\nNo GUI changes needed\nCC-BY licensed pieces"],
        ['Faithful 32x', 'Faithful Team', 'texture_packs', '1.21', 'low', 0, 0,
            'The classic double-resolution pack — familiar, just crisper.',
            'Doubles vanilla resolution without changing the art direction. The safest way to make the game look sharper on a 1440p or 4K display.',
            "32x block and item textures\nGUI and font coverage\nMod patch pack included\nFrequently updated"],
        ['Stay True', 'Yuruze', 'texture_packs', '1.20', 'low', 0, 0,
            'Subtle hand-painted pack that keeps the vanilla silhouette.',
            'A gentle rework of vanilla textures with soft painting and better foliage. Looks native to the game, which makes it perfect for a long-term world.',
            "Hand-painted block set\nImproved foliage and crops\nSubtle animated water\n16x resolution"],
        ['Sodium + Lithium Boost', 'CaffeineMC', 'performance_modpacks', '1.21', 'low', 0, 0,
            'The standard FPS stack: renderer + server + network optimisations.',
            'A curated bundle of Sodium, Lithium, FerriteCore and friends. This is the single biggest performance win you can make without changing visuals.',
            "Sodium renderer\nLithium server logic fixes\nFerriteCore memory savings\nPre-tuned video settings"],
        ['Fabulously Optimized', 'Robitobi01', 'performance_modpacks', '1.20', 'low', 0, 0,
            'Performance pack that also unlocks shader support through Iris.',
            'Ships performance mods plus Iris, so you keep shaders while gaining frames. Includes fixes for common multiplayer quirks.',
            "Iris shader support\nZoom, controller and QoL mods\nMultiplayer-safe config\nOne-click installer"],
        ['Distant Horizons', 'James Seibel', 'performance_modpacks', '1.21', 'high', 0, 0,
            'Draws terrain far past the vanilla render distance.',
            'Pre-renders level-of-detail chunks so you can see kilometres of landscape. Needs a decent CPU and patience on first load, but the payoff is huge.',
            "LOD terrain engine\nConfigurable render distance\nSQLite data cache\nWorks alongside Sodium"],
        ['Chunky Pixels 128x', 'PixelForge', 'texture_packs', '1.19', 'medium', 0, 0,
            'Bold, cartoonish 128x pack with heavy outlines.',
            'A high-resolution stylised pack for players who want the world to look like a game, not a photo. Needs a mid-range GPU for smooth play.',
            "128x block textures\nOutlined mob and item art\nCustom sky overlay\nAnimated ores"],
        ['Ultra Realism HD', 'Northlight Studio', 'texture_packs', '1.21', 'high', 1, 12.00,
            'Photoreal 256x pack with PBR maps for shiny metal, wet stone and fabric.',
            'A premium photoreal pack with normal, specular and roughness maps for every block. Built for shader users on high-end hardware.',
            "256x PBR texture set\nCustom 3D models\nAnimated liquids\nLifetime updates"],
    ];

    $freeMarketplace = [
        ['EssentialsX Starter Kit', 'IronKitchen', 'configs', '1.21', 'low', 0, 0,
            'Sane homes, warps, kits and economy defaults for a fresh survival server.',
            'A commented EssentialsX configuration you can drop in and adjust. Covers home limits, warmups, cooldowns and a starter economy without the usual guesswork.',
            "config.yml with comments\nkits.yml included\nEconomy starting balance tuned\nAnti-abuse cooldown set"],
        ['LuckPerms Rank Ladder', 'IronKitchen', 'configs', '1.20', 'low', 0, 0,
            'A clean 6-tier rank ladder with inheritance and chat prefixes.',
            'Ready-made permission groups from Default to Owner with sensible inheritance, prefix colours and per-rank command access.',
            "6 inherited groups\nChat prefix colours\nPer-rank command sets\nImport commands included"],
        ['MythicMobs Starter Bosses', 'DungeonDweller', 'configs', '1.21', 'low', 0, 0,
            'Five beginner-friendly custom bosses with skills and drops.',
            'Learn the MythicMobs syntax from working examples: phased bosses, custom skills, spawn conditions and loot tables.',
            "5 boss definitions\nPhase-based skills\nCustom drop tables\nSpawn triggers"],
        ['Vanilla+ Data Pack', 'RedstoneRae', 'datapacks', '1.21', 'low', 0, 0,
            'Quality-of-life recipes and tweaks without adding mods.',
            'A vanilla-compatible data pack that unlocks quality-of-life crafting and adjusts a few annoying drop rates. Server-side only, so vanilla clients can join.',
            "QoL crafting recipes\nMob drop rebalance\nDatapack load-safe\nNo client mods needed"],
        ['Anti-Grief Essentials Rules', 'GuardrailGus', 'configs', '1.20', 'low', 0, 0,
            'WorldGuard regions and flags tuned for public survival worlds.',
            'A worldguard/region ruleset that blocks the usual griefing vectors while keeping building freedom. Ships with a spawn-region template.',
            "Spawn region template\nPvP and build flags\nContainer protection\nExplosion rules"],
        ['Skyblock Starter Schematic', 'SkyForge', 'schematics', '1.20', 'low', 0, 0,
            'A tidy starter island with a working crop and mob farm.',
            'Schematic file for a compact skyblock spawn island including farms, storage and a small shop area. Paste it with WorldEdit or the plugin of your choice.',
            "Starter island .schem\nCrop and mob farm\nStorage room\nShop stalls"],
    ];

    $premiumMarketplace = [
        ['MythicMobs Boss Collection Pro', 'DungeonDweller', 'configs', '1.21', 'high', 1, 24.00,
            'Thirty boss encounters with mechanics, arenas and loot tables.',
            'A production-ready boss library for servers that need real endgame content: multi-phase fights, custom skills, spawn logic and balanced loot.',
            "30 bosses with phases\nArena build instructions\nBalanced loot tables\nSkill library\nDiscord support"],
        ['LuckPerms Network Ranks Pro', 'PermissionPete', 'configs', '1.21', 'low', 1, 18.00,
            'Multi-server permission setup for a lobby plus survival, skyblock and creative.',
            'Permissions built for a network: per-server contexts, staff hierarchy, and a donator ladder that never leaks admin commands.',
            "Multi-server contexts\nDonator ladder\nStaff hierarchy\nAudit checklist\nFree updates"],
        ['Mega Base Schematic Pack', 'SkyForge', 'schematics', '1.21', 'medium', 1, 32.00,
            'Six detailed mega builds in .schem and .litematic formats.',
            'Six hand-built structures from a nether hub to a floating island base, exported in both WorldEdit and Litematica formats.',
            "6 builds, 2 formats\nWorldEdit paste guides\nMaterial lists\nInterior detail pass\nCommercial licence"],
        ['Survival Economy Config Suite', 'IronKitchen', 'configs', '1.21', 'low', 1, 15.00,
            'Shop, jobs, auction house and sell-wand configs that work together.',
            'Four plugins pre-tuned as one economy: balanced prices, sane job payouts, anti-dupe checks and a working auction flow.',
            "Shop GUI layout\nJobs payout table\nAuction house rules\nAnti-dupe config\nPrice spreadsheet"],
        ['Dungeon Data Pack Deluxe', 'RedstoneRae', 'datapacks', '1.21', 'medium', 1, 20.00,
            'Procedurally assembled dungeons with custom loot and mob scaling.',
            'A data pack that stitches rooms into random dungeons per seed, with difficulty scaling by distance from spawn.',
            "Random room assembly\nCustom loot tables\nDistance-based scaling\nBoss rooms\nVanilla-compatible"],
        ['High-End RTX Graphics Pack', 'Northlight Studio', 'shaders', '1.21', 'high', 1, 22.00,
            'Cinematic shader preset with path-traced lighting and pro colour grading.',
            'Premium shader configuration for content creators: tuned exposure, film grain, bloom and per-dimension grades ready for recording.',
            "Path-traced lighting preset\nCreator colour grades\nPer-dimension looks\nCapture settings guide\nPriority support"],
    ];

    $map = [
        'library'     => $library,
        'marketplace' => array_merge($freeMarketplace, $premiumMarketplace),
    ];

    $downloads = ['low' => 1200, 'medium' => 640, 'high' => 210];
    $sources = demo_sources();

    foreach ($map as $section => $items) {
        foreach ($items as $index => [$name, $author, $category, $version, $impact, $premium, $price, $summary, $description, $includes]) {
            $slug = slugify($name);
            $linked = $sources[$slug] ?? null;
            $rows[] = [
                'slug'         => $slug,
                'name'         => $name,
                'author'       => $author,
                'section'      => $section,
                'category'     => $category,
                'mc_version'   => $version,
                'summary'      => $summary,
                'description'  => $description,
                'includes'     => $includes,
                'impact'       => $impact,
                'is_premium'   => $premium,
                'price_usd'    => $price,
                'download_url' => null,
                'file_name'    => $slug . '-' . $version . '.txt',
                'downloads'    => $downloads[$impact] + (($index * 37) % 190),
                'rating'       => 4.2 + (($index % 8) / 10),
                'created_at'   => date('Y-m-d H:i:s', strtotime('-' . (3 + $index) . ' days')),
                // Demo items that exist for real on Modrinth download the actual file.
                'source'             => $linked !== null ? 'modrinth' : '',
                'source_project_id'  => $linked[0] ?? null,
                'source_project_slug' => $linked[0] ?? null,
                'source_project_name' => $linked[1] ?? null,
            ];
        }
    }

    return $rows;
}

function slugify(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));

    return $slug === '' ? 'item' : $slug;
}
