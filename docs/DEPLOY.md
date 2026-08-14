# Deploying to aaPanel

Laravel 13 · PHP 8.3+ · MySQL · Livewire. No queue worker, no cron, no mail — the app needs a web
server and a database and nothing else.

> **The app has never run against MySQL.** Every migration and test so far has been on SQLite. Run
> the migration step early and on its own, not five minutes before you want people using it.

---

## 1. Commit the code first

The repository currently has **no commits**. Before anything reaches a server there should be a
baseline to deploy from and roll back to.

`.env` is correctly git-ignored. Confirm it stays that way — it now holds the QCXIS client ID.

## 2. PHP: the two aaPanel traps

**Version.** `composer.json` requires `^8.3`. aaPanel often defaults a site to an older PHP, and the
CLI used by `php artisan` can differ from the one serving the site. Set **both** to 8.3+.

```bash
php -v && php -m | grep -E "pdo_mysql|mbstring|openssl|tokenizer|ctype|fileinfo|curl|bcmath"
```

**Disabled functions.** This is the one that wastes an afternoon. aaPanel ships a `disable_functions`
list that usually includes `proc_open`, `putenv`, `pcntl_signal` and `symlink`. Composer needs
`proc_open`; Laravel needs `putenv`. Remove at least those two in
*Website → PHP settings → Disable functions*, or `composer install` fails with messages that don't
mention the cause.

## 3. Site setup

- **Document root must point at `/public`** (aaPanel: "Running directory" → `/public`), not the
  project root. Getting this wrong exposes `.env` over HTTP.
- Enable HTTPS. The app sets no `SESSION_SECURE_COOKIE` by default, so add it (step 5) once TLS works.

### nginx rewrite — the app is blank without it

aaPanel's default nginx template assumes a static site: any URL that isn't a real file on disk gets a
404 from nginx before Laravel is ever called. Two symptoms come from this one cause:

- every path except `/` returns 404 (`/login`, `/roster`, …)
- the page loads but no button works, with `livewire.js … 404` in the browser console

In **Website → Settings → URL Rewrite**, choose the **laravel** template, or paste:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

Livewire's JavaScript is published to a real static file at `public/vendor/livewire/livewire.js`
(step 8 does this), so the default `.js` rule serves it fine — no special Livewire location needed.
Behind Cloudflare, hard-refresh (Cmd/Ctrl+Shift+R) after any nginx change, since a cached 404 sticks.

## 4. Database

Create a MySQL database with **utf8mb4 / utf8mb4_unicode_ci** — staff names contain accented and
Malay characters.

No migration uses anything MySQL-specific: all date arithmetic is in PHP (`App\Support\ChallengeWeek`),
and the only raw SQL is `lower(email)` and `count(*)`, both portable.

## 5. `.env` on the server

Copy `.env.example` and set:

```dotenv
APP_ENV=production
APP_DEBUG=false                      # true would leak stack traces and the SSO setup hint
APP_URL=https://your-domain
APP_TIMEZONE=Asia/Kuala_Lumpur
APP_KEY=                             # php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true           # once HTTPS is on

CHALLENGE_START_MONDAY=2026-08-10

SSO_DRIVER=qcxis
QCXIS_CLIENT_ID=c_…
QCXIS_REDIRECT_URI=https://your-domain/auth/sso/callback
QCXIS_REQUIRE_VERIFIED_EMAIL=…       # decide — see below
```

**Two values that are local-only and must not be copied across:**

- `QCXIS_REQUIRE_VERIFIED_EMAIL=false` was set locally because QCXIS reports `email_verified: false`.
  Decide deliberately for production. Leaving it `false` means an unverified account claiming a
  colleague's address could sign in as them, and the roster match is on email alone.
- `SSO_DRIVER=stub` must never appear. It refuses to run outside local/testing anyway, but the whole
  point is that it would otherwise let anyone sign in as anyone.

## 6. Register the production redirect URI with QCXIS

Add `https://your-domain/auth/sso/callback` to the app at
`https://qcxis.com/console/settings/apps`. It is matched by **exact string** — scheme, host, port,
path and trailing slash all count.

Add it *alongside* the localhost URI rather than replacing it, so local testing keeps working.

## 7. Build the frontend

`public/build` is git-ignored, and aaPanel servers frequently have no Node. Easiest path is to build
locally and upload the result:

```bash
npm ci && npm run build      # locally
# then upload public/build/ to the server
```

Nothing in the build reaches the network — fonts are self-hosted via `@fontsource`, so it works
offline and on an intranet.

## 8. Install and migrate

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate          # only if APP_KEY is empty
php artisan migrate --force
php artisan db:seed --class=TeamSeeder --force
php artisan vendor:publish --tag=livewire:assets --force   # static livewire.js
```

The zip already contains the published Livewire assets, so that last line only matters if you deploy
by pulling from git or after upgrading Livewire. Re-run it whenever `composer update` touches
Livewire.

**Only `TeamSeeder` is safe in production.** `DatabaseSeeder` creates ~37 fictional staff and a
10-week weigh-in history. `TeamSeeder` creates only TAH and BKN, which must exist before anyone can
be imported or assigned.

## 9. Permissions

```bash
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

`storage/app/private/livewire-tmp` is created on the first CSV upload and must be writable, or the
roster import fails at the upload step.

## 10. Cache for production

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Once config is cached, `.env` edits do nothing until you re-run `config:cache`.** That catches
everyone at least once — including when fixing the SSO values.

## 11. Create the first admin

```bash
php artisan challenge:admin you@qcxis.com --password='…'
```

The email must match the QCXIS account exactly, or SSO rejects it as "not on the challenge roster".
The password is the fallback login for when SSO breaks.

## 12. Load the real roster

Sign in, then **Roster → Import CSV**. Export from Google Sheets as
*File → Download → Comma-separated values*. Needs `name` and `email` columns at minimum.

Then check the roster header: **"N never signed in"** is the count of people whose email doesn't
match their QCXIS account. It should fall to zero as people sign in during week one.

---

## Smoke test after deploying

1. `https://your-domain/login` renders with the **Sign in with QCXIS** button
2. Sign in as yourself → lands on the home page as an admin
3. **`https://your-domain/auth/sso/stub` returns 404** — it lists every staff name and email and must
   never be reachable in production
4. Roster loads; add one person by hand
5. Import a two-row CSV, confirm the preview, confirm the count
6. Record a weigh-in for the current week, then check it appears in Analytics
7. `APP_DEBUG=false` verified by visiting a bad URL — you should see a plain 404, not a stack trace

## Not implemented — don't promise these

- **Scale photos.** `weigh_ins.photo_path` exists in the schema, but nothing writes or reads it.
- **Reminders / notifications** of any kind.
- **RP-initiated logout** — signing out ends the local session only; the QCXIS browser session persists.
