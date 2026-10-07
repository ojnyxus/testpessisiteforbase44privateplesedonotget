<?php
declare(strict_types=1);

/**
 * Crypto payment helpers.
 *
 * The hub is self-hosted and non-custodial: the owner stores receiving addresses in
 * settings, the app converts the USD price at the owner's own (manually maintained)
 * rate and shows an invoice with the exact amount and a reference code. The buyer
 * sends the coins from any wallet and pastes the transaction id; the owner confirms
 * it in the admin panel, which is what unlocks the file or books the tip revenue.
 *
 * No third-party payment API keys are needed for this flow.
 */
function coins(): array
{
    return [
        'btc' => ['label' => 'Bitcoin', 'symbol' => 'BTC', 'scheme' => 'bitcoin', 'color' => '#F7931A', 'decimals' => 8],
        'xmr' => ['label' => 'Monero', 'symbol' => 'XMR', 'scheme' => 'monero', 'color' => '#FF6600', 'decimals' => 6],
        'ltc' => ['label' => 'Litecoin', 'symbol' => 'LTC', 'scheme' => 'litecoin', 'color' => '#345D9D', 'decimals' => 6],
    ];
}

function coin_meta(string $coin): ?array
{
    return coins()[strtolower($coin)] ?? null;
}

function coin_address(string $coin): string
{
    return (string)setting('address_' . strtolower($coin), '');
}

/** USD per 1 coin, maintained by the owner in Admin -> Settings. */
function coin_rate(string $coin): float
{
    return (float)setting('rate_' . strtolower($coin), '1');
}

function usd_to_crypto(float $usd, string $coin): float
{
    $rate = coin_rate($coin);

    return $rate > 0 ? $usd / $rate : 0.0;
}

function payment_reference(string $prefix): string
{
    return strtoupper($prefix) . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/** Wallet-app deep link, e.g. bitcoin:ADDRESS?amount=0.0012 */
function crypto_uri(string $coin, string $address, float $amount, string $label = ''): string
{
    $meta = coin_meta($coin);
    if ($meta === null) {
        return '';
    }

    $query = ['amount' => crypto_amount($amount)];
    if ($label !== '') {
        $query['label'] = $label;
    }

    return $meta['scheme'] . ':' . $address . '?' . http_build_query($query);
}
