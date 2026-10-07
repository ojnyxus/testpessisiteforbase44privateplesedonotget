<?php
declare(strict_types=1);

/** Shared shell: header, page content, footer, toast container. */
$user = current_user();
$siteName = setting('site_name', config('app_name'));
$navItems = [
    'home'        => ['/', 'Home'],
    'library'     => ['/library', 'Mods & Shaders'],
    'marketplace' => ['/marketplace', 'Configs'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($siteName) ?></title>
    <meta name="description" content="<?= e(setting('site_tagline', config('tagline'))) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" href="<?= url('/assets/img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
    <script defer src="<?= url('/assets/js/app.js') ?>"></script>
</head>
<body data-csrf="<?= e(csrf_token()) ?>" data-user="<?= $user ? (int)$user['id'] : 0 ?>">
<div class="bg-decor" aria-hidden="true"></div>

<header class="site-header" id="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?= url('/') ?>">
            <span class="brand-mark"><?= icon('cube', 22) ?></span>
            <span class="brand-name"><?= e($siteName) ?></span>
        </a>

        <nav class="main-nav" id="main-nav" aria-label="Main">
            <?php foreach ($navItems as $key => [$href, $label]): ?>
                <a class="nav-link<?= $nav === $key ? ' is-active' : '' ?>" href="<?= url($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($user !== null): ?>
                <a class="nav-link<?= $nav === 'dashboard' ? ' is-active' : '' ?>" href="<?= url('/dashboard') ?>">My Hub</a>
            <?php endif; ?>
            <?php if (is_admin()): ?>
                <a class="nav-link<?= $nav === 'admin' ? ' is-active' : '' ?>" href="<?= url('/admin') ?>">Admin</a>
            <?php endif; ?>
        </nav>

        <div class="header-actions">
            <a class="btn btn-gold btn-sm" href="<?= url('/tip') ?>"><?= icon('cup', 16) ?> Tip a creator</a>
            <?php if ($user !== null): ?>
                <div class="user-chip">
                    <span class="avatar" aria-hidden="true"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
                    <span class="user-name"><?= e($user['name']) ?></span>
                    <a class="user-exit" href="<?= url('/logout?token=' . csrf_token()) ?>" title="Sign out"><?= icon('close', 14) ?></a>
                </div>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?= url('/login') ?>">Sign in</a>
                <a class="btn btn-primary btn-sm" href="<?= url('/register') ?>">Join free</a>
            <?php endif; ?>
            <button class="nav-toggle" id="nav-toggle" aria-label="Menu" aria-expanded="false"><?= icon('menu', 20) ?></button>
        </div>
    </div>
</header>

<main class="site-main">
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="wrap footer-inner">
        <div class="footer-col">
            <span class="brand brand-sm">
                <span class="brand-mark"><?= icon('cube', 18) ?></span>
                <span class="brand-name"><?= e($siteName) ?></span>
            </span>
            <p class="muted"><?= e(setting('site_tagline', config('tagline'))) ?></p>
        </div>
        <div class="footer-col">
            <h4>Browse</h4>
            <a href="<?= url('/library') ?>">Mods, shaders &amp; packs</a>
            <a href="<?= url('/marketplace') ?>">Server configurations</a>
            <a href="<?= url('/tip') ?>">Tip a creator</a>
        </div>
        <div class="footer-col">
            <h4>Accepted crypto</h4>
            <div class="coin-row">
                <?php foreach (coins() as $key => $coin): ?>
                    <span class="coin-pill" style="--coin:<?= e($coin['color']) ?>"><?= e($coin['symbol']) ?></span>
                <?php endforeach; ?>
            </div>
            <p class="muted small">Payments are confirmed manually by the hub owner — no third-party processor
                holds your coins.</p>
        </div>
    </div>
    <div class="wrap footer-bottom">
        <span class="muted small">&copy; <?= date('Y') ?> <?= e($siteName) ?></span>
        <span class="muted small">Not affiliated with Mojang or Microsoft.</span>
    </div>
</footer>

<div class="toasts" id="toasts" aria-live="polite">
    <?php foreach ($flashes as $flash): ?>
        <?php
        $tone = match ($flash['type']) {
            'warning' => 'gold',
            'error'   => 'bad',
            default   => 'ok',
        };
        ?>
        <div class="toast <?= e($tone) ?>" data-toast><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
</div>
</body>
</html>
