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

## Known limitations

- No email/SMS notifications for new quotations or order updates. Users see changes when they open the app. `VerificationService` (OTP) and the empty `app/Services/Sms` driver stubs are left unused from earlier work.
- No file uploads (product images, supplier documents). Product rows show no imagery rather than placeholders.
- Quotation currency is fixed to TZS.
- `TRUSTED_PROXIES` is read in `bootstrap/app.php` and therefore must be a real environment variable when configuration is cached.
- No browser end-to-end test suite; flows were verified with feature tests and a manual browser walkthrough.
