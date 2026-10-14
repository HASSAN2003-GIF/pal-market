# Putting PAL Market online (Render + Vercel)

- **Render** runs the Laravel API and the PostgreSQL database (`render.yaml`, `Dockerfile`).
- **Vercel** serves the website (`vercel.json`, `npm run build:vercel`) and forwards `/api`, `/media` and the Google sign-in routes to Render. Visitors only ever see the Vercel address.

Both deploy from the `main` branch of `github.com/HASSAN2003-GIF/pal-market` and redeploy automatically on every push. Nothing secret is stored in the repository: you enter secrets in the Render dashboard.

## 1. Render: API and database (about 10 minutes)

1. Sign in at <https://dashboard.render.com> with your GitHub account.
2. **New → Blueprint**, then pick the `pal-market` repository and the `main` branch. Render reads `render.yaml` and shows two resources: `pal-market-db` (PostgreSQL) and `pal-market-api` (web service).
3. Fill in the values Render asks for:
   - `ADMIN_EMAIL`: your own email address. It becomes the administrator account.
   - `APP_URL`: the Vercel address. If you don't know it yet, use `https://pal-market.vercel.app` and correct it after step 2.
   - Leave `MEDIA_DISK`, `AWS_*` and `MAIL_*` empty for now (see section 4).
4. Click **Apply**. The first build takes 5–10 minutes.
5. When it is live, open `https://pal-market-api.onrender.com/up`. It should show a green "Application up" page.
   - If Render named the service differently (for example because the name was taken), note the real address and change the five `pal-market-api.onrender.com` entries in `vercel.json` to it.

## 2. Vercel: the website (about 5 minutes)

1. Sign in at <https://vercel.com> with your GitHub account.
2. **Add New → Project**, then import `pal-market`. Vercel reads `vercel.json`, so leave the framework preset as "Other" and change nothing else.
3. **Deploy**. Your site is at the address Vercel shows, for example `https://pal-market.vercel.app`.
4. If that address differs from the `APP_URL` you gave Render, go to Render → `pal-market-api` → **Environment**, correct `APP_URL` and save (the API restarts).

## 3. Your administrator password and secure sign-in

Nobody ever sees or sends your password, including Claude and the deploy log.

1. Render → `pal-market-api` → **Logs**. Find the block:

   ```
   ==================== PAL MARKET ADMIN SETUP ====================
   Open this link within 60 minutes to choose the admin password.
   https://<your site>/reset-password?token=…&email=…
   ```

   Every restart prints a fresh link until you have signed in once. If the link has expired, use **Manual Deploy → Restart service** to get a new one.
2. Open the link and choose a strong password (at least 10 characters with letters and numbers; a password manager is best).
3. Go to `https://<your site>/admin`. There is no admin button on the public site on purpose. Sign in with your email and the new password.
4. The console then asks you to turn on **two-step sign-in**. Scan the QR code with an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy), type the 6-digit code, and **save the eight backup codes** somewhere safe offline. Each works once if you lose your phone.
5. From then on, signing in needs your password plus the code from your phone. Admin pages refuse to work until two-step sign-in is on (`ADMIN_REQUIRE_2FA=true`).

The rest happens automatically. Five wrong codes end the sign-in attempt, sign-in attempts are rate-limited, sessions expire after 7 days, and every admin action goes into the activity log (`/admin/audit-log`). The setup link stops being printed after your first sign-in.

## 4. Before inviting real users

| Item | Why | How |
| --- | --- | --- |
| Photo storage | Render's free plan has no permanent disk, so uploaded photos disappear on each deploy, restart or sleep. | Create a free Cloudflare R2 bucket, then set `MEDIA_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET` and `AWS_ENDPOINT` on Render. |
| Email | Password reset for buyers and sellers needs email. | Any SMTP provider (Brevo, Mailgun, Postmark, Amazon SES): set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. |
| Database plan | Render's free PostgreSQL **stops working 30 days after it is created**, and is deleted 14 days later unless upgraded. | Upgrade `pal-market-db` to the smallest paid plan (about US$6/month) before relying on the data. Paid databases include backups and point-in-time recovery. |
| Always-on API | The free web service sleeps after 15 idle minutes; the next visit then waits about a minute. | Upgrade `pal-market-api` to the smallest paid instance (about US$7/month). |
| Own domain | e.g. `palmarket.co.tz` | Add it in Vercel → Project → Domains, then set `APP_URL` on Render to it. |
| Google sign-in | Optional. | Create OAuth credentials with redirect `https://<your site>/auth/google/callback` and set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` on Render. |
| Kiswahili review | The Kiswahili text has not been reviewed by a native speaker. | See `docs/DECISIONS.md`. |

Prices above are Render's published prices as of 2026 and may change; check their pricing page.

## 5. Updating the site later

Push to `main`. Render rebuilds the API and runs any new database migrations on start; Vercel rebuilds the website. Check `https://<your site>/up` afterwards.

To roll back: Render → `pal-market-api` → **Events** → pick the previous deploy → **Rollback**; Vercel → **Deployments** → previous deploy → **Promote to Production**. Migrations are additive, so the previous version still runs against the newer database.
