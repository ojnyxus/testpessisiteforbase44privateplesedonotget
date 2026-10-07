<?php
declare(strict_types=1);
?>
<section class="wrap" style="padding:4rem 0">
    <div class="panel" style="max-width:620px;margin:0 auto;text-align:center">
        <span class="eyebrow"><?= icon('cube', 15) ?> 404</span>
        <h1 style="font-size:2rem">That chunk isn't loaded</h1>
        <p class="muted">
            Nothing lives at <span class="mono"><?= e((string)($requested ?? '/')) ?></span>.
            Try the directory or the marketplace instead.
        </p>
        <div class="row-actions" style="justify-content:center;margin-top:1rem">
            <a class="btn btn-primary" href="<?= url('/library') ?>"><?= icon('cube', 16) ?> Directory</a>
            <a class="btn btn-ghost" href="<?= url('/marketplace') ?>"><?= icon('shield', 16) ?> Marketplace</a>
        </div>
    </div>
</section>
