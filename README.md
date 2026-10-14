# PAL Market

PAL Market is a marketplace for construction materials in Tanzania. Buyers post a materials list, approved suppliers send priced quotations, and the buyer accepts one to raise a purchase order that both sides track to delivery. Administrators approve suppliers, maintain the shared product catalog and oversee activity.

The marketplace does not take payments: order values are agreed amounts, and payment is arranged between buyer and supplier.

## Stack

| Layer | Technology |
| --- | --- |
| API | Laravel 13 (PHP 8.3+, developed on 8.5), Sanctum bearer tokens, Socialite (Google) |
| Database | MySQL 8 in development and production (via Sail); SQLite in memory for tests |
| Frontend | React 19 single-page app, React Router 7, TanStack Query 5, Tailwind CSS 4, built with Vite 8 |

The SPA is served by Laravel from the same origin as the API (`resources/views/welcome.blade.php`), so no CORS configuration is needed for it.

## Roles and main flows

| Role | Can do |
| --- | --- |
| Buyer | Create requests (title, delivery region/address, needed-by date, items), publish them for a quoting window, compare quotations, accept one (which raises the purchase order), confirm delivery and complete orders. |
| Supplier | Browse open requests (pending suppliers included). Once approved: maintain catalog (with photos; ask for products not yet listed), prices, stock and locations, send quotations, withdraw them, and confirm, prepare and dispatch orders. |
| Admin | Approve, suspend and reactivate suppliers and buyers; suspend, reactivate, re-role or delete user accounts (with safeguards); manage products (Kiswahili names, search aliases, photos), categories and brands; review product requests from suppliers; view all requests, orders and the audit log. |

Request: `draft → open → closed | cancelled | expired`. Quotation: `draft → submitted → accepted | rejected | expired | cancelled (withdrawn)`. Purchase order: `pending → confirmed → processing → shipped → delivered → completed`, or `cancelled` by the buyer before dispatch.

## Language, theme and area

- **English and Kiswahili.** Switch with EN / SW in the header. Interface text lives in `resources/js/i18n/locales/{en,sw}/*.js`; API messages, validation errors and the password-reset email in `lang/sw*`. The SPA sends `X-Locale`; the server falls back to the account's saved language, then `Accept-Language`. Text that people typed (product names, notes, business names) is never translated. Run `npm run check:i18n` after changing text.
- **Light / dark / same as device.** Saved on the device; `public/theme-init.js` applies it before first paint. Colours are tokens in `resources/css/app.css` with a dark value for each.
- **Your area.** A Tanzanian region chosen from a list, or suggested from the browser's location after the person allows it (nearest regional capital, computed on the device). Only the region name is stored (`localStorage` and `users.region`), never coordinates. It orders the catalog (`GET /api/products?region=…&only_local=1`), marks local offers and pre-fills new requests.
- **Administration** is at `/admin` (also `/#admin`). There is deliberately no link to it in public navigation; access is enforced by the API (`auth:sanctum`, `active`, `admin` middleware), not by hiding the link.
- **Brand assets**: see `scripts/brand/README.md`.

## Screen sizes

Layouts follow the available width (Tailwind breakpoints `sm` 640, `md` 768, `lg` 1024, `2xl` 1536 px), never the device name or user agent.

| Width | Public pages | Signed-in pages |
| --- | --- | --- |
| Phones (< 768) | Header: logo, EN/SW, theme, menu; search and area on their own rows; filters in a panel | Top bar with area, language and theme; a bottom tab bar with the four most used pages and "More"; tables become cards; price tables become stacked lines |
| Tablets (768–1023) | Same header with inline search | Side navigation; admin tables still shown as cards |
| Laptops and desktops (≥ 1024) | Area chip, search, account and navigation in the header; filters in a side column; sign-in pages gain an explainer column | Full tables, multi-column dashboards and forms |
| Large screens (≥ 1536) | Content widens to 80rem instead of stretching edge to edge | Same |

On short landscape screens the headers stop sticking and the tab bar gets shorter. Touch screens get 44px minimum controls. Zoom is never disabled.

## Local development (Sail)

Prerequisites: Docker Desktop (with WSL 2 integration on Windows), PHP 8.3+ and Composer on the host for the first install, Node 22+.

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed   # catalog + local demo accounts
npm install
npm run dev
```

Open http://localhost. Demo accounts (local and testing only, never seeded in production), password `password123`:

- `admin@palmarket.test`
- `buyer@palmarket.test`
- `supplier@palmarket.test` (approved, with a location, prices and stock)

Scheduled jobs (expiring requests and quotations, pruning expired tokens) run with `./vendor/bin/sail artisan schedule:work`.

### Without Docker

Set `DB_CONNECTION=sqlite` and `DB_DATABASE=/absolute/path/database.sqlite` (create the empty file first), then run `php artisan migrate --seed`, `php artisan serve` and `npm run dev`.

## Tests and checks

```bash
php artisan test                    # whole suite, SQLite in memory (see phpunit.xml)
DB_CONNECTION=mysql DB_DATABASE=testing php artisan test   # against MySQL
./vendor/bin/pint --test <files>    # code style
npm run check:i18n                  # English/Kiswahili dictionaries complete and consistent
npm run build                       # production frontend build
```

CI (`.github/workflows/ci.yml`) runs the suite, a style check on changed PHP files, the production build and dependency audits on every push to `main` and every pull request.

## API overview

All endpoints are under `/api` and return JSON. Authenticated endpoints need `Authorization: Bearer <token>` from `/api/login`, `/api/register` or the Google exchange. Tokens expire after 7 days (`SANCTUM_TOKEN_EXPIRATION`). Validation errors return `422` with `{ message, errors }`; a suspended account receives `403` with `code: "account_suspended"`.

| Area | Endpoints |
| --- | --- |
| Account | `POST register, login, logout, forgot-password, reset-password, auth/google/exchange`; `GET/PUT me`; `PUT me/password`; `PUT me/preferences` (`locale`, `region`); `GET workspace/summary` |
| Catalog (public) | `GET products` (`search`, `category_id`, `brand_id`, `sort=price_asc\|price_desc\|name`, `region`, `only_local`; the response's `search.mode` is `exact`, `close` with a `suggestion`, `related` or `none`), `GET products/suggest?q=` (type-ahead), `GET products/{id}` (`region` marks `in_region` offers; `images`), `GET catalog/filters`, `GET /media/products/{uuid}/{thumb\|large}.webp` |
| Buyer requests | `GET/POST buyer-requests`; `GET/PUT/DELETE buyer-requests/{id}`; `POST/PUT/DELETE buyer-requests/{id}/items[/{item}]`; `POST buyer-requests/{id}/publish`, `/cancel` |
| Market (suppliers) | `GET market-requests`, `GET market-requests/{id}` |
| Quotations | `POST buyer-requests/{id}/quotations` (optionally with `items` and `submit: true` for one atomic call); `GET quotations`, `GET/PUT quotations/{id}`; `POST quotations/{id}/items`, `DELETE …/items/{item}`; `POST quotations/{id}/submit`, `/withdraw`, `/accept` (`create_purchase_order: true` raises the order in the same transaction), `/reject` |
| Purchase orders | `POST quotations/{id}/purchase-order`; `GET purchase-orders`, `GET purchase-orders/{id}`; `POST purchase-orders/{id}/confirm`, `/process`, `/ship`, `/deliver`, `/complete`, `/cancel` |
| Order documents | `GET purchase-orders/{id}/document` (versions, availability); `GET purchase-orders/{id}/document/pdf?lang=en\|sw&version=&disposition=inline\|attachment` (buyer, supplier, admins); `PUT purchase-orders/{id}/document/signatories` (buyer) |
| Supplier catalog | REST resources `supplier-products`, `supplier-prices`, `supplier-inventory`, `supplier-locations`; `POST supplier-products/{id}/images` (multipart `images[]`), `POST …/images/{image}/primary`, `DELETE …/images/{image}`; `GET/POST product-proposals` |
| Admin | `GET admin/dashboard`; `admin/users` (+ `/role`, `/suspend`, `/reactivate`, delete); `admin/suppliers` (+ `/approve`, `/suspend`, `/reactivate`); `admin/buyers` (+ `/suspend`, `/reactivate`); `admin/catalog/products|categories|brands|taxonomy` (products take `name_sw` and `aliases[]`; `POST admin/catalog/products/{id}/images`, `/primary`, delete); `GET admin/product-proposals`, `POST …/{id}/approve`, `/reject`; `GET admin/buyer-requests`, `admin/purchase-orders`, `admin/audit-logs` |
| Health | `GET /up` (app boots), `GET /api/health` (database and cache reachable; `503` otherwise) |

List endpoints accept `page`, `per_page` (max 50 or 100) and endpoint-specific filters such as `status` and `search`, and return a `meta` block with pagination details.

## Administration

Create the first administrator on a server:

```bash
php artisan pal:create-admin you@example.com --name="Your Name"
```

Administrators cannot change their own role, suspend or delete themselves, or remove the last active administrator. Accounts with quotations or orders cannot be deleted; suspend them instead. Security-relevant actions are written to the audit log.

## Security notes

- Server-side authorization on every operation (policies plus role middleware); the UI only mirrors it.
- Rate limiting on sign-in, registration and password reset; per-account lockout after five failed sign-ins.
- Suspensions take effect immediately for existing tokens.
- Google sign-in validates OAuth state and never puts the API token in a URL.
- Security headers and a strict Content Security Policy outside local development.
- Tokens are kept in `localStorage`; the CSP is the main defence against script injection, so keep it strict.

See `docs/DEPLOYMENT.md` for production setup, `docs/DECISIONS.md` for design decisions and known limitations, and `docs/UX-RESEARCH.md` for the marketplace patterns that were reviewed.
