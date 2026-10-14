# Deploying PAL Market

PAL Market has not been deployed by this work; no hosting provider, domain or credentials are configured. This runbook covers what any host needs: a PHP 8.3+ web server (Nginx + PHP-FPM, or a container), MySQL 8, a cron runner, outbound SMTP and HTTPS.

## 1. Server requirements

- PHP 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath`, `intl`, `gd` (built with WebP and JPEG support) and `exif` (photo uploads), `dom` (order PDFs).
- PHP `upload_max_filesize` ≥ 6M and `post_max_size` ≥ 45M (up to 8 photos of 5 MB in one upload), and the web server's request body limit to match (Nginx `client_max_body_size 45m`).
- Composer 2, Node 22+ (only where assets are built).
- MySQL 8 with a dedicated database user granted only `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES` on the PAL Market database.
- The web root must be `public/`. `storage/` and `bootstrap/cache/` writable by the PHP user; nothing else writable.

## 2. Environment

Copy `.env.example` to `.env` on the server (or set real environment variables) and set at least:

| Variable | Production value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | from `php artisan key:generate --show`; keep it stable across deploys |
| `APP_URL` | `https://your-domain` |
| `DB_*` | production database credentials |
| `SESSION_SECURE_COOKIE` | `true` |
| `MAIL_*` | a working mailer (password reset depends on it) |
| `LOG_STACK` / `LOG_LEVEL` | `daily` / `info`, or `stderr` on container platforms |
| `TRUSTED_PROXIES` | load balancer IPs when behind one (real env var, not only `.env`) |
| `GOOGLE_*` | optional; redirect URI `https://your-domain/auth/google/callback` |
| `MEDIA_DISK` | `public` on a single server (photos in `storage/app/public/products`); a shared disk such as `s3` when running several web servers. Back it up with the database. |
| `MEDIA_MAX_UPLOAD_KB` | optional, default `5120` |

## 3. Release steps

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build            # or build in CI and upload public/build
php artisan migrate --pretend      # review the SQL first
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Remove `public/hot` if it exists: its presence makes Laravel load assets from a Vite dev server.

Serve `public/theme-init.js` as a normal static file (it is loaded in `<head>` and allowed by the CSP as `'self'`). Do not inline it: inline scripts are blocked by the CSP. `Permissions-Policy` allows geolocation for the site's own origin only; it is requested only when a person presses "Use my location".

The release adding search, photos and order documents adds three migrations (`product_aliases`, `product_proposals`, `products.name_sw`; `product_images`; `order_documents`, `purchase_orders.confirmed_at`, `purchase_order_items.product_name`, which is back-filled from current product names). PDFs use the DejaVu Sans font bundled with dompdf; dompdf writes its font cache to `storage/fonts`, which must be writable. "Use my location" only works over HTTPS (browsers block geolocation on plain HTTP except on localhost).

The release adding language and area preferences includes one additive migration (`users.locale`, `users.region`). CI runs `npm run check:i18n`; a failing check means interface text is missing in one language.

First release only:

```bash
php artisan db:seed --class=CatalogSeeder --force   # starter catalog; demo accounts are never seeded in production
php artisan pal:create-admin admin@your-domain
```

## 4. Scheduler and queue

Cron entry (every minute):

```
* * * * * cd /var/www/pal-market && php artisan schedule:run >> /dev/null 2>&1
```

It expires requests and quotations whose window has passed, prunes expired API tokens daily and clears old password-reset tokens. `QUEUE_CONNECTION=database` is configured; nothing currently dispatches queued jobs, so a worker is optional today. If mail is moved to a queue later, run `php artisan queue:work --tries=3` under a process supervisor.

## 5. Web server

- Redirect HTTP to HTTPS at the proxy. The app sends HSTS once requests arrive over HTTPS.
- Send all non-file requests to `public/index.php` (standard Laravel config).
- Do not serve `.env`, `storage/` or the repository root.

## 6. Health checks and monitoring

- `GET /up`: PHP and the framework boot.
- `GET /api/health`: database and cache respond. Returns `503` with failing checks named; point the load balancer readiness probe here.
- Errors are logged through the configured log channel. Forward logs to your log service and alert on `ERROR` entries and on `Health check failed`.

## 7. Backups and recovery

- Take automated daily MySQL backups (e.g. `mysqldump --single-transaction`) and keep at least 14 days, stored off the server.
- Test a restore into a staging database regularly.
- `APP_KEY` must be backed up separately and kept secret: without it encrypted values and sessions cannot be read.

## 8. Rollback

1. Re-deploy the previous release directory or image.
2. If the failed release ran migrations, roll back only those: `php artisan migrate:rollback --step=N --force` after confirming with `migrate:status`. The migrations added in this release are additive (new columns, indexes and the `audit_logs` table); rolling them back drops that data.
3. `php artisan config:cache && php artisan route:cache`.

## 9. Before going live

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS enforced
- [ ] Mail delivery verified with a real password reset
- [ ] Cron running (`php artisan schedule:list` shows the jobs)
- [ ] First admin created; demo accounts absent
- [ ] Backups configured and a restore tested
- [ ] Kiswahili text reviewed by a native speaker
- [ ] `/admin` checked with a non-admin account (should show "no access") and with an admin account
- [ ] Suppliers created before this release reviewed: older registrations stored a randomly generated TIN and a "<name> Hardware" business name that the supplier never provided
