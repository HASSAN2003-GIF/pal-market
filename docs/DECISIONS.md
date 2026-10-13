# Design decisions and known limitations

Decisions taken during the production-readiness pass (October 2026), with the reasoning, so they can be revisited deliberately.

## Business rules chosen where the code was silent or inconsistent

| Decision | Why |
| --- | --- |
| Registration accepts `role` of `buyer` or `supplier`; anything else becomes `buyer`. | The register page offers both; the existing tests required that no one can self-assign `admin`. |
| Emails are not marked verified at registration. | Nothing verifies them. Marking them verified let someone pre-register another person's address and later share that account when the owner signed in with Google. Sign-in does not require verification; completing a password reset or Google sign-in marks the address verified. |
| Supplier TIN is optional at sign-up. | The form never asked for one, so the backend generated a random TIN and a "<name> Hardware" business name. Admins review TINs during approval. |
| Publishing a request needs at least one item; submitting a quotation needs at least one priced line. | Empty requests/quotations carry no information. The old UI already disabled "publish" for empty requests. |
| Published requests close after a buyer-chosen window (1–60 days, default 14). | `expires_at` and an `expire()` transition existed, but nothing set or ran them, so requests stayed open forever. |
| Buyers never see suppliers' draft quotations. | A draft is the supplier's unfinished work. |
| Cancelling a request marks its live quotations `cancelled`. | Suppliers otherwise saw "awaiting decision" on a request that no longer existed. |
| Accepting a quotation can raise the purchase order in the same transaction. | The old UI claimed acceptance created an order, but it needed a second call; a failure between them left accepted quotes without orders. |
| Unapproved suppliers can browse the market but cannot set up catalog, prices, stock or locations. | Kept the existing rule (covered by existing tests). The UI says so instead of offering controls that fail. |
| A suspended business profile can read its history but cannot change anything. Account-level suspension blocks all API access. | Two different tools: pause trading vs. lock someone out. |
| Admin user deletion is refused for accounts with quotations or orders. | Foreign-key cascades would otherwise erase the other party's records. |
| Pagination envelopes keep the original keys (`buyer_requests`, `users`, …) plus `meta`. | Backwards compatible with existing clients and tests. New endpoints use `{ data, meta }`. |

## Technical choices

- **Tests run on in-memory SQLite** (`phpunit.xml`) so they need no Docker; set `DB_CONNECTION=mysql` to run against MySQL. The one MySQL-only migration was rewritten with `Schema::change()`.
- **Bearer tokens in `localStorage`** remain (existing architecture). Mitigations: strict CSP in non-local environments, 7-day expiry, revocation on password change/reset, suspension and role change. Moving to Sanctum's cookie-based SPA auth would remove token exposure to scripts; that is a larger change touching every request and was not made.
- **Self-hosted fonts** (`@fontsource`) so the CSP allows only `'self'` for fonts.
- **Lazy-loaded workspaces**: public visitors do not download buyer/supplier/admin code.
- **`Model::preventLazyLoading()` and `preventSilentlyDiscardingAttributes()`** outside production, to surface N+1 queries and typos in tests.

## Language, theme, area and brand (second pass, October 2026)

| Decision | Why |
| --- | --- |
| Language order: `X-Locale` from the SPA, then the account's saved language, then `Accept-Language`, then English. | What the person just picked must win; a saved choice beats a browser default. |
| Only interface text is translated. Product, category and business names, notes and audit-log entries are shown as entered. | Translating what people typed changes its meaning, and the brief forbids it. |
| Emails use the account's saved language (`User::preferredLocale()`), otherwise the language of the request. | Password-reset emails are sent before sign-in. |
| The area is a region name, chosen from a list or suggested from a one-off, low-accuracy browser position. The nearest regional capital is computed in the browser; coordinates never leave the device and are never stored. | Matches the data (supplier locations and delivery are recorded by region) and the brief's privacy rule. Near borders the suggestion can be wrong, so the person always confirms it. |
| "Near me first" sorts by the number of approved sellers with stock at an active location in that region; "only my area" filters to those. Unknown region values are ignored. | Uses real stock data; a typo never hides the whole catalog. |
| The theme is stored per device, not on the account. | Light/dark is usually a property of the screen and time of day. |
| `/admin` stays a normal SPA route with no public link; `/#admin` forwards to it; a signed-in non-admin sees a "no access" page instead of a silent redirect. | All protection is the API; the page shell holds no data. Covered by `AdminEntryTest`. |
| The logo was redrawn from the supplied photo using Manrope ExtraBold outlines; headings moved to Manrope 800 to match it. | The photo is small (103×61 px), so tracing it would be blurry; the logo's letterforms are geometric and match that typeface closely. |
| English UI says "seller", "price offer" and "order" instead of "supplier", "quotation" and "purchase order". API field names are unchanged. | Plain words for people new to online buying; no API break. |

## Known limitations

- No email/SMS notifications for new quotations or order updates. Users see changes when they open the app. `VerificationService` (OTP) and the empty `app/Services/Sms` driver stubs are left unused from earlier work.
- No file uploads (product images, supplier documents). Product rows show no imagery rather than placeholders.
- Quotation currency is fixed to TZS.
- `TRUSTED_PROXIES` is read in `bootstrap/app.php` and therefore must be a real environment variable when configuration is cached.
- No browser end-to-end test suite; flows were verified with feature tests and a manual browser walkthrough.
- The Kiswahili text was written for this release and has not been reviewed by a native-speaking editor. Have it reviewed before launch (files under `resources/js/i18n/locales/sw/`, `lang/sw/`, `lang/sw.json`).
- Validation rules not listed in `lang/sw/validation.php` fall back to English.
- Supplier locations created before this release may use region spellings outside the 31-region list; they still display, but do not count as "in your area" unless they match.
- The Amazon "Deliver to" pattern was studied from third-party documentation, because amazon.com could not be fetched (see `docs/UX-RESEARCH.md`).
