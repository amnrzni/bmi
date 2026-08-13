# Deploying to cPanel

This zip is **self-contained**: `vendor/` and the compiled front-end are already inside, so you do
not need Composer or Node on the server.

> **You still need a shell** — cPanel → **Terminal**, or SSH — for four commands (steps 5–7). There is
> no way to run database migrations from the cPanel UI. If Terminal is disabled on your plan, ask
> your host to enable it before starting.

> **The app has never run against MySQL.** Do step 5 early and on its own.

---

## 1. PHP version — do this first

cPanel → **MultiPHP Manager** → set the domain to **PHP 8.3 or newer**. The app will not run on 8.2.

Then cPanel → **Select PHP Version** → **Extensions**, and make sure these are ticked:

`pdo_mysql` · `mbstring` · `openssl` · `tokenizer` · `xml` · `ctype` · `json` · `bcmath` · `fileinfo` · `curl`

## 2. Upload and unzip

Upload the zip via **File Manager** and extract it somewhere **outside** `public_html` — for example
`/home/<user>/bmi`. The application code must not be web-reachable; only the `public` folder should be.

## 3. Point the domain at `public/`

Two ways, best first:

**Preferred — change the document root.** cPanel → **Domains** → edit the domain → set the document
root to `/home/<user>/bmi/public`. Nothing else to do.

**If your host won't allow that**, move the contents of `bmi/public/` into `public_html/` and edit
`public_html/index.php`, changing the two `__DIR__.'/../'` paths to point at `/home/<user>/bmi/`.
Fiddlier, and it breaks on the next upload — prefer the first.

⚠️ **If the whole folder ends up inside `public_html`, your `.env` becomes downloadable over the web.**
Check by visiting `https://your-domain/.env` — it must 404.

## 4. Database

cPanel → **MySQL Databases**:

1. Create a database
2. Create a user with a strong password
3. **Add the user to the database with ALL PRIVILEGES** — easy to miss, and the failure looks like a
   connection error

Note the full names: cPanel prefixes them, e.g. `cpuser_bmi` and `cpuser_bmiapp`.

## 5. Configure and migrate

In **Terminal**, from the app folder:

```bash
cd ~/bmi
cp .env.example .env
nano .env          # fill in the values below
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=TeamSeeder --force
```

`.env` needs at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
APP_TIMEZONE=Asia/Kuala_Lumpur

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=cpuser_bmi
DB_USERNAME=cpuser_bmiapp
DB_PASSWORD=…

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

CHALLENGE_START_MONDAY=2026-08-10

SSO_DRIVER=qcxis
QCXIS_CLIENT_ID=c_019ff95b24777932823e48809dacbcb5
QCXIS_REDIRECT_URI=https://your-domain/auth/sso/callback
QCXIS_REQUIRE_VERIFIED_EMAIL=false
```

**Only `TeamSeeder` may be run.** `db:seed` on its own creates ~37 fictional staff and a fake
weigh-in history.

**Two values to think about rather than copy:**

- `QCXIS_REQUIRE_VERIFIED_EMAIL=false` — needed because QCXIS reports your account's email as
  unverified. Left `true`, nobody can sign in. Left `false`, an unverified account claiming a
  colleague's address could sign in as them. Your call.
- `SSO_DRIVER` must never be `stub`. It refuses to run outside local anyway.

## 6. Permissions

```bash
chmod -R 775 storage bootstrap/cache
```

On most cPanel hosts PHP already runs as your user, so no `chown` is needed.

## 7. Cache and create your admin

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan challenge:admin you@qcxis.com --password='choose-one'
```

⚠️ **After `config:cache`, edits to `.env` do nothing until you run it again.** This catches everyone
once, usually while fixing the SSO settings.

The admin email must match the QCXIS account you sign in with — the roster match is on email alone.

## 8. Register the production redirect URI with QCXIS

At `https://qcxis.com/console/settings/apps`, add:

```
https://your-domain/auth/sso/callback
```

Matched by **exact string** — scheme, host, path, trailing slash all count. Add it *alongside* the
localhost URI rather than replacing it, so local testing keeps working.

## 9. Load the roster

Sign in → **Roster → Import CSV**. From Google Sheets: *File → Download → Comma-separated values*.
Needs `name` and `email` columns at minimum.

Then watch the roster header: **"N never signed in"** counts people whose email doesn't match their
QCXIS account. It should fall to zero during week one.

---

## Smoke test

1. `https://your-domain/.env` → **404** (if it downloads, stop and fix step 3)
2. `https://your-domain/auth/sso/stub` → **404** — it lists every staff name and email
3. `/login` shows **Sign in with QCXIS**
4. Sign in as yourself → home page, as an admin
5. Visit a nonsense URL → plain 404, not a stack trace (proves `APP_DEBUG=false`)
6. Record a weigh-in, then check Analytics shows it

## When something breaks

```bash
tail -50 ~/bmi/storage/logs/laravel.log
```

SSO failures log the specific reason — state mismatch, the OAuth error code, or a missing email claim.

## Updating later

Upload a new zip over the folder, keeping your `.env`, then:

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The last line matters — stale caches after an upload produce confusing errors.
