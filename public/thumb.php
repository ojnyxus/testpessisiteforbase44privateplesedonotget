<?php
declare(strict_types=1);

/**
 * Procedural preview art for catalogue cards.
 *
 * The repo ships no binary image assets: each item gets a unique, deterministic
 * isometric "block" scene generated from its slug. Swap in real screenshots later by
 * pointing the card at your own image files instead of this endpoint.
 */
$seed = substr((string)($_GET['seed'] ?? 'block'), 0, 60);
$kinds = ['shader', 'pack', 'config', 'schematic', 'datapack'];
$requestedKind = (string)($_GET['kind'] ?? 'shader');
$kind = in_array($requestedKind, $kinds, true) ? $requestedKind : 'shader';

$w = max(240, min(1280, (int)($_GET['w'] ?? 640)));
$h = max(160, min(800, (int)($_GET['h'] ?? 360)));

$hash = crc32($seed . '|' . $kind);
mt_srand($hash);

$hue = match ($kind) {
    'shader'    => 158 + ($hash % 40) - 20,
    'pack'      => 196 + ($hash % 40) - 20,
    'config'    => 268 + ($hash % 50) - 25,
    'schematic' => 34 + ($hash % 24) - 12,
    'datapack'  => 12 + ($hash % 30) - 15,
    default     => $hash % 360,
};

$accent = sprintf('hsl(%d, %d%%, %d%%)', $hue, 62, 52);

/** Draws one isometric cube centred on a grid point. */
function cube(float $cx, float $cy, float $a, float $depth, int $hue, int $light): string
{
    $top   = sprintf('hsl(%d, %d%%, %d%%)', $hue, 30, $light);
    $right = sprintf('hsl(%d, %d%%, %d%%)', $hue, 32, max(8, $light - 13));
    $left  = sprintf('hsl(%d, %d%%, %d%%)', $hue, 34, max(5, $light - 22));

    $t  = [$cx, $cy - $a - $depth];
    $r  = [$cx + $a, $cy - $a / 2 - $depth];
    $b  = [$cx, $cy - $depth];
    $l  = [$cx - $a, $cy - $a / 2 - $depth];
    $rl = [$cx + $a, $cy - $a / 2];
    $bl = [$cx, $cy];
    $ll = [$cx - $a, $cy - $a / 2];

    $p = static fn(array $points): string => implode(' ', array_map(
        static fn(array $pt): string => round($pt[0], 1) . ',' . round($pt[1], 1),
        $points
    ));

    return '<polygon points="' . $p([$t, $r, $b, $l]) . '" fill="' . $top . '"/>'
        . '<polygon points="' . $p([$r, $rl, $bl, $b]) . '" fill="' . $right . '"/>'
        . '<polygon points="' . $p([$l, $b, $bl, $ll]) . '" fill="' . $left . '"/>';
}

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?= $w ?>" height="<?= $h ?>" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img">
    <defs>
        <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="hsl(<?= $hue ?>, 45%, 16%)"/>
            <stop offset="55%" stop-color="hsl(<?= $hue ?>, 38%, 9%)"/>
            <stop offset="100%" stop-color="hsl(<?= $hue ?>, 30%, 5%)"/>
        </linearGradient>
        <radialGradient id="glow" cx="70%" cy="30%" r="70%">
            <stop offset="0%" stop-color="<?= $accent ?>" stop-opacity="0.55"/>
            <stop offset="100%" stop-color="<?= $accent ?>" stop-opacity="0"/>
        </radialGradient>
        <radialGradient id="vignette" cx="50%" cy="50%" r="75%">
            <stop offset="55%" stop-color="#000" stop-opacity="0"/>
            <stop offset="100%" stop-color="#000" stop-opacity="0.55"/>
        </radialGradient>
        <pattern id="grid" width="24" height="24" patternUnits="userSpaceOnUse">
            <path d="M24 0H0V24" fill="none" stroke="hsl(<?= $hue ?>, 40%, 60%)" stroke-opacity="0.10" stroke-width="1"/>
        </pattern>
    </defs>

    <rect width="<?= $w ?>" height="<?= $h ?>" fill="url(#sky)"/>
    <rect width="<?= $w ?>" height="<?= $h ?>" fill="url(#grid)"/>
    <rect width="<?= $w ?>" height="<?= $h ?>" fill="url(#glow)"/>

    <?php if ($kind === 'shader'): ?>
        <?php for ($i = 0; $i < 5; $i++): ?>
            <?php $x = ($w / 5) * $i + mt_rand(0, 30); ?>
            <polygon points="<?= $x ?>,0 <?= $x + 46 ?>,0 <?= $x + 170 ?>,<?= $h ?> <?= $x + 60 ?>,<?= $h ?>"
                     fill="#fff" opacity="0.05"/>
        <?php endfor; ?>
        <ellipse cx="<?= $w * 0.72 ?>" cy="<?= $h * 0.3 ?>" rx="<?= $w * 0.11 ?>" ry="<?= $w * 0.11 ?>"
                 fill="hsl(<?= $hue ?>, 70%, 72%)" opacity="0.5"/>
    <?php endif; ?>

    <?php
    $horizon = $h * 0.66;
    $unit = max(14.0, min(30.0, $w / 22));
    $cols = (int)ceil($w / ($unit * 2)) + 3;
    for ($i = -2; $i < $cols; $i++) {
        $cx = $i * $unit * 2 + $unit;
        $lift = mt_rand(0, (int)($unit * 1.6));
        $light = mt_rand(24, 34);
        echo cube($cx, $horizon + $lift, $unit, $unit * 1.15, $hue, $light);
    }
    ?>

    <?php for ($i = 0; $i < 3; $i++): ?>
        <?php
        $cx = mt_rand((int)($w * 0.15), (int)($w * 0.85));
        $cy = mt_rand((int)($h * 0.28), (int)($h * 0.5));
        $size = $unit * 0.75;
        ?>
        <g opacity="0.9"><?= cube($cx, $cy, $size, $size * 1.1, $hue, mt_rand(38, 52)) ?></g>
    <?php endfor; ?>

    <?php if ($kind === 'datapack' || $kind === 'config'): ?>
        <?php for ($i = 0; $i < 7; $i++): ?>
            <?php
            $x = mt_rand((int)($w * 0.6), (int)($w * 0.92));
            $y = mt_rand((int)($h * 0.12), (int)($h * 0.42));
            $len = mt_rand(30, 90);
            ?>
            <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $len ?>" height="4" rx="2"
                  fill="<?= $accent ?>" opacity="0.45"/>
        <?php endfor; ?>
    <?php endif; ?>

    <rect width="<?= $w ?>" height="<?= $h ?>" fill="url(#vignette)"/>
</svg>
