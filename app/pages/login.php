<?php
declare(strict_types=1);

$page_nav = '';
$page_title = 'Sign in';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = 'Your session expired — reload the page and try again.';
    } else {
        $result = attempt_login($_POST);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            flash('Welcome back — your hub is ready.', 'success');
            redirect(is_admin() ? '/admin' : '/dashboard');
        }
    }
}

if (current_user() !== null) {
    redirect('/dashboard');
}
?>

<section class="wrap auth-layout">
    <div class="panel">
        <span class="eyebrow"><?= icon('users', 14) ?> Sign in</span>
        <h2>Pick up where you left off</h2>
        <p class="muted">Bookmarks, downloads and premium unlocks are all tied to your account.</p>

        <?php if ($error !== null): ?>
            <div class="toast bad" style="margin-bottom:1rem"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('/login') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email" autocomplete="email" required>
            </div>
            <div class="field" style="margin-top:.7rem">
                <label for="password">Password</label>
                <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary btn-block" type="submit" style="margin-top:1rem">Sign in</button>
        </form>

        <div class="divider">or stay anonymous</div>

        <form method="post" action="<?= url('/login') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="wallet">Wallet address</label>
                <input class="input mono" type="text" id="wallet" name="wallet_address"
                       placeholder="bc1q… / 4… / ltc1q…" required>
            </div>
            <button class="btn btn-ghost btn-block" type="submit" style="margin-top:.7rem">
                <?= icon('wallet', 16) ?> sign in with my wallet
            </button>
        </form>

        <p class="muted small" style="margin-top:1rem">
            No account yet? <a href="<?= url('/register') ?>" style="color:var(--emerald)">Create one in seconds</a>.
        </p>
    </div>

    <div class="sticky-side">
        <div class="panel">
            <span class="eyebrow gold"><?= icon('lock', 14) ?> Why sign in at all?</span>
            <ul class="check-list">
                <li><?= icon('check', 16) ?><span>Premium unlocks are attached to your account, so you can re-download any time.</span></li>
                <li><?= icon('check', 16) ?><span>Bookmark the shaders and configs you want to install next.</span></li>
                <li><?= icon('check', 16) ?><span>See your download history in one place.</span></li>
                <li><?= icon('check', 16) ?><span>Wallet-style accounts need no email at all.</span></li>
            </ul>
        </div>
    </div>
</section>
