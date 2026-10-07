<?php
declare(strict_types=1);

require_admin();
if (!csrf_ok()) {
    flash('Your session expired — try again.', 'error');
    redirect('/admin/settings');
}

$text = ['site_name', 'site_tagline', 'address_btc', 'address_xmr', 'address_ltc', 'coffee_url', 'tip_presets'];
foreach ($text as $key) {
    if (array_key_exists($key, $_POST)) {
        save_setting($key, trim((string)$_POST[$key]));
    }
}

foreach (['rate_btc', 'rate_xmr', 'rate_ltc'] as $key) {
    $value = (float)($_POST[$key] ?? 0);
    if ($value > 0) {
        save_setting($key, rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'));
    }
}

flash('Settings saved — new invoices use these values.', 'success');
redirect('/admin/settings');
