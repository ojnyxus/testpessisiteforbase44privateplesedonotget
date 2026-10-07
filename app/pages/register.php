<?php
declare(strict_types=1);

$page_nav = '';
$page_title = 'Create your account';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = 'Your session expired — reload the page and try again.';
    } else {
        $result = register_user($_POST);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $user = db_one('SELECT * FROM users WHERE id = ?', [$result['user_id']]);
            auth_login($user);
            flash(
                $user['role'] === 'admin'
                    ? 'Account created — you own this hub, so the admin dashboard is yours.'
                    : 'Account created. Happy building!',
                'success'
            );
            redirect($user['role'] === 'admin' ? '/admin' : '/dashboard');
        }
    }
}

if (current_user() !== null) {
    redirect('/dashboard');
}

$userCount = (int)db_value('SELECT COUNT(*) FROM users');
?>

<section class="wrap auth-layout">
    <div class="panel">
        <span class="eyebrow"><?= icon('users', 14) ?> Create your hub</span>
        <h2>Join in a few seconds</h2>
        <p class="muted">
            <?= $userCount === 0
                ? 'This is the first account on the hub, so it becomes the owner account with access to the admin dashboard.'
                : 'Email and password, or just a wallet address if you would rather stay anonymous.' ?>
        </p>

        <?php if ($error !== null): ?>
            <div class="toast bad" style="margin-bottom:1rem"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('/register') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Display name (optional)</label>
                <input class="input" type="text" id="name" name="name" maxlength="64" placeholder="Builder name">
            </div>
            <div class="field" style="margin-top:.7rem">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email" autocomplete="email" required>
            </div>
            <div class="field" style="margin-top:.7rem">
                <label for="password">Password (8+ characters)</label>
                <input class="input" type="password" id="password" name="password" autocomplete="new-password"
                       minlength="8" required>
            </div>
            <button class="btn btn-primary btn-block" type="submit" style="margin-top:1rem">Create account</button>
        </form>

        <div class="divider">or go anonymous</div>

        <form method="post" action="<?= url('/register') ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label for="wallet_address">Public wallet address</label>
                <input class="input mono" type="text" id="wallet_address" name="wallet_address"
                       placeholder="bc1q… / 4… / ltc1q…" required>
            </div>
            <div class="field" style="margin-top:.7rem">
                <label for="wallet_name">Display name (optional)</label>
                <input class="input" type="text" id="wallet_name" name="name" maxlength="64" placeholder="Leave blank for a random alias">
            </div>
            <button class="btn btn-ghost btn-block" type="submit" style="margin-top:1rem">
                <?= icon('wallet', 16) ?> Use my wallet as my identity
            </button>
            <p class="muted small" style="margin:.7rem 0 0">
                The address is only used as a login handle here — the hub never asks for a signature or your keys.
            </p>
        </form>

        <p class="muted small" style="margin-top:1rem">
            Already have an account? <a href="<?= url('/login') ?>" style="color:var(--emerald)">Sign in</a>.
        </p>
    </div>

    <div class="sticky-side">
        <div class="panel">
            <span class="eyebrow"><?= icon('shield', 14) ?> What you get</span>
            <div class="stat-grid">
                <div class="stat">
                    <div class="stat-label">Bookmarks</div>
                    <div class="stat-value"><?= e((string)(int)db_value('SELECT COUNT(*) FROM assets')) ?></div>
                    <div class="stat-sub">items to shortlist</div>
                </div>
                <div class="stat gold">
                    <div class="stat-label">Premium unlocks</div>
                    <div class="stat-value gold"><?= e((string)(int)db_value('SELECT COUNT(*) FROM assets WHERE is_premium = 1')) ?></div>
                    <div class="stat-sub">payable in crypto</div>
                </div>
            </div>
        </div>
    </div>
</section>
