<?php
declare(strict_types=1);
?>
<section class="wrap" style="padding:4rem 0">
    <div class="panel" style="max-width:560px;margin:0 auto;text-align:center">
        <span class="eyebrow gold"><?= icon('lock', 15) ?> 403</span>
        <h1 style="font-size:2rem">Owners only</h1>
        <p class="muted">The admin dashboard belongs to the account that created this hub.</p>
        <a class="btn btn-primary" href="<?= url('/') ?>">Back to the hub</a>
    </div>
</section>
