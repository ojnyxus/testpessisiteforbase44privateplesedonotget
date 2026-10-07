# BlockForge Hub — working notes

Minecraft modpack/shader directory + server-config marketplace with self-hosted crypto unlocks,
built as plain PHP 8.2 (Apache) + MySQL 8 + vanilla HTML/CSS/JS. No framework, no build step.

## Running it (Base44 sandbox / any Docker host)

```bash
docker compose -f docker-compose.base44.yml up -d --build
```

* `web` — PHP 8.2 + Apache, document root `public/`, served on host port **3000**.
* `db-init` — one-shot `php app/migrate.php`: creates tables, seeds settings and the demo
  catalogue. It runs to completion before `web` starts (and is idempotent — re-running keeps data).
* `db` — MySQL 8, data in the `db_data` volume.

Source is bind-mounted, so PHP/HTML/CSS/JS edits are live on refresh (no rebuild needed). A
change to `Dockerfile` or `docker-compose.base44.yml` needs `up -d --build` again. There is no
JS bundler, so nothing to rebuild for frontend changes.

Verify: `curl -fsS -o /dev/null http://localhost:3000/` (also the compose healthcheck — it
renders the home page, so it proves PHP *and* MySQL are answering).

## Layout

```
app/            bootstrap (config, db, helpers, auth, payments, queries), router, migrate, schema
app/pages/      one file per route: output a content fragment, set $page_nav/$page_title
app/views/      layout.php, partials.php (card + badge renderers), asset.php, pay.php
app/api/        JSON endpoints: /api/assets, /api/bookmark, /api/pay/status
public/         front controller (index.php), thumb.php, assets/css, assets/js
```

Routing has no `.htaccess` — the image sets `FallbackResource /index.php`, so any non-file path
(`/library`, `/asset/{slug}`, `/api/...`) reaches `app/router.php`.

## Things worth knowing

* **First account owns the hub.** There are no seeded credentials: the first user to register
  becomes `admin` and gets `/admin`. Register with an email *or* with just a wallet address
  (anonymous mode, used as a login handle only — no signature, no keys).
* **Payments are non-custodial and manual by design.** `app/payments.php` holds the coin
  metadata. An invoice shows the owner's address plus an amount converted at the owner's own
  rate; the buyer pastes a txid (`/pay/submit`) and the owner confirms it in
  `/admin/payments`, which is what grants the unlock or books tip revenue. No third-party
  payment API or credentials are involved — a processor (Coinbase Commerce, NOWPayments…) could
  be layered on later, but it is not needed for the flow to work.
* **Wallets live in the database, not env.** `settings.address_btc|address_xmr|address_ltc`,
  `rate_btc|rate_xmr|rate_ltc` (USD per coin, maintained by hand) and `tip_presets` are edited in
  `/admin/settings`. Seeded values are obviously fake placeholders — replace them with real
  addresses before taking payments. Rates are not fetched from a price API.
* **Catalogue preview art is generated.** `public/thumb.php` draws a deterministic isometric
  scene from each slug (kind chosen by category), so the repo ships no binary images. Real
  screenshots can replace it by pointing cards at image files.
* **Downloads are placeholders.** `downloads` rows and the per-asset counter are recorded on
  `/download/{id}`. With no `assets.download_url` set, the response is a generated text package;
  set a real URL in the database/admin to hand out the actual file. Premium items redirect to
  login/asset page unless the buyer has a confirmed unlock.
* **Money-critical rules** live in `app/pages/pay_create.php` (who may open an invoice) and
  `app/pages/pay_review.php` (admin-only confirmation). CSRF is required on every POST via
  `csrf_field()` / `X-CSRF-Token`.

## Sandbox-specific behaviour

* `BASE44_PREVIEW_MODE=1` is passed into the services by the platform. The app compares it to
  exactly `'1'` and only then trusts `X-Forwarded-Proto` when deciding whether to mark the
  session cookie `Secure` (`is_https_request()` in `app/auth.php`) — the preview terminates TLS
  in front of Apache. With the flag unset or any other value, only the local connection is
  inspected, i.e. stock behaviour.
* All generated URLs are app-relative, so the app works on localhost, behind the preview proxy,
  and on a real domain without any host configuration.
* No host/origin allowlist needed: Apache does not gate on `Host`, and there is no separate
  frontend origin or CORS layer (single origin, server-rendered pages).

## Checks

```bash
docker compose -f docker-compose.base44.yml exec web sh -c 'find /var/www/html/app /var/www/html/public -name "*.php" -exec php -l {} \;'
docker compose -f docker-compose.base44.yml logs --tail=40 db-init web
```
