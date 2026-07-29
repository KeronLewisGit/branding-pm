# Deployment

Branding & Signage PM Checklist System. Written for whoever installs and then
keeps this running — including the parts that will bite six months from now.

Stack: PHP 8.4, Laravel 13, MySQL 8, Livewire 4, queue `database`, cache
`file`, session `database`. **No Redis anywhere**, deliberately: this runs on
one plant server, and an extra daemon is one more thing to be down at 6am.

---

## 1. What you need

| | |
|---|---|
| PHP | 8.4 with `bcmath`, `exif`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip` |
| MySQL | 8.0 (utf8mb4 / utf8mb4_unicode_ci) |
| Node | 20+ — build-time only, not needed on the server if you build elsewhere |
| Web server | nginx or Apache with the document root on `public/` |
| TLS | **Required.** The kiosk PWA and its service worker only run on HTTPS (or `localhost`), and PINs cross the wire. |

`gd` is not optional: it renders the PWA icons and reads signature images.

---

## 2. First install

```bash
git clone <repo> /var/www/branding-pm
cd /var/www/branding-pm

composer install --no-dev --optimize-autoloader
npm ci && npm run build          # writes public/build

cp .env.example .env
php artisan key:generate
```

Edit `.env` — at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false                  # never true on the plant server
APP_URL=https://pm.example.com   # must match reality: PWA scope and QR links use it
APP_DISPLAY_TIMEZONE=America/Port_of_Spain

DB_DATABASE=branding_pm
DB_USERNAME=branding_pm
DB_PASSWORD=<strong>

ADMIN_PASSWORD=<strong>          # read ONCE by AdminUserSeeder, see §3
```

`APP_TIMEZONE` stays `UTC`. Everything is stored in UTC and displayed in
`APP_DISPLAY_TIMEZONE`; changing the storage timezone will silently shift
every historical timestamp.

Then:

```bash
php artisan migrate --force
php artisan db:seed --force      # roles, permissions, master data, ONE admin
php artisan storage:link         # /storage → storage/app/public (signatures, photos)
```

**Do not run `DemoSeeder` on a production install.** It fabricates 30 days of
checklist history, which is exactly the kind of fiction an auditor should
never find in a compliance record. It is opt-in for a reason:

```bash
php artisan db:seed --class=DemoSeeder     # demo/training installs only
```

---

## 3. The admin account

`AdminUserSeeder` creates exactly one account — `ADMIN-0001` /
`admin@example.com` — using `ADMIN_PASSWORD` from `.env`. If that variable is
unset it falls back to a **published default** and prints a loud warning; the
default is in the repository, so an install left on it is open to anyone who
can read this project.

Set `ADMIN_PASSWORD` before seeding, or change the password immediately after.

Everyone else is created through the app. Floor operators need a PIN (4–6
digits) and may have no email or password at all.

---

## 4. Permissions on disk

The web user must own — or at least be able to write — `storage/` and
`bootstrap/cache`:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
```

This is the single most common cause of a total outage here, and it fails in a
misleading way: an unwritable `storage/framework/cache` makes
spatie/laravel-permission's cache write fail, and since every authenticated
page renders `@can()`, **every page 500s at once**. Worse, if
`storage/logs/laravel.log` is also unwritable, the logger's own failure
replaces the real exception, so the log says nothing useful.

If you run `artisan` as root (or through `sudo`, or `docker compose exec`),
it leaves root-owned files behind and reintroduces exactly this. Run artisan
as the web user, or re-apply the two commands above afterwards.

---

## 5. Scheduler and queue

Both are required. Without the scheduler no checklists are generated and
nothing is ever marked missed — the system looks fine and quietly stops being
a compliance record.

Cron, one line:

```cron
* * * * * cd /var/www/branding-pm && php artisan schedule:run >> /dev/null 2>&1
```

That drives:

- `checklists:generate` daily at 05:00 (plant local) — creates the day's runs.
  Idempotent: safe to re-run by hand after an outage.
- `checklists:mark-missed` hourly — flips expired pending runs to `missed`.
  Never deletes anything.

A worker for the `database` queue, under supervisor/systemd:

```ini
[program:branding-pm-worker]
command=php /var/www/branding-pm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/branding-pm-worker.log
```

Catching up after downtime:

```bash
php artisan checklists:generate --date=2026-07-28 --dry-run   # see what it would do
php artisan checklists:generate --date=2026-07-28
php artisan checklists:mark-missed
```

---

## 6. Production caches

After every deploy, in this order:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

`php artisan optimize:clear` undoes all of it. Note that `config:cache` makes
`env()` return null outside config files — read config, never `env()`, in
application code.

---

## 7. Kiosk tablets

1. Sign in as an admin, create the device under kiosk management, then open
   `/kiosk/enrol/{device}` **on the tablet itself**. That plants the signed
   device cookie; without it the tablet gets the "not enrolled" screen.
2. Add the site to the home screen — `start_url` is `/kiosk`, and it launches
   standalone with no browser chrome.
3. The cookie is long-lived but not eternal. Re-enrol after a factory reset,
   a browser-data wipe, or a change of tablet.

Offline behaviour is scoped deliberately (SPEC §Non-Functional): a run that is
**already open** stays completable, queuing answers in IndexedDB and syncing
on reconnect, with a badge showing the queue depth. Master data is not synced
and a new run cannot be started offline.

One consequence to brief operators on: if the tablet is reloaded while answers
are queued, those answers are **not** replayed — a Livewire payload carries a
state snapshot that is stale after a reload, and replaying it would write old
state over new. The run form shows a red banner naming how many answers were
lost so they can be re-entered. Losing an answer loudly beats saving a wrong
one silently.

---

## 8. QR stickers

`/admin/machines/qr` prints the sheet. Each sticker encodes `/m/{code}` and
prints the code in plain text beneath, because a sticker in a print shop gets
scuffed and over-sprayed, and a code that can be typed still works.

**Machine codes are permanent once printed.** Changing a code invalidates
every sticker already on that machine — the admin screen warns you, and the
warning is not decorative. If a code must change, print and fit the
replacement sticker in the same visit.

---

## 9. Backups

Back up two things; either alone is useless:

1. **The database** — the entire record.
   ```bash
   mysqldump --single-transaction --routines branding_pm | gzip > branding-pm-$(date +%F).sql.gz
   ```
2. **`storage/app/public`** — signatures and photos. The database references
   them by path; without the files, approved runs lose the signatures that
   made them approvable.

Also keep `.env` somewhere safe and separate: `APP_KEY` decrypts sessions and
keys the run-sheet verification hashes. Restore the database without the
original `APP_KEY` and every printed sheet's verification code stops matching.

Test a restore before you need one.

---

## 10. Upgrading

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

The service worker caches the app shell; content-hashed asset filenames mean a
new build is a new URL, so tablets pick it up on their next navigation. If a
tablet seems stuck on an old build, close the standalone window and reopen it.

---

## 11. When something is wrong

| Symptom | Look here first |
|---|---|
| Every page 500s right after login | `storage/` permissions — §4. Check ownership before anything else. |
| `laravel.log` is empty or only shows logger errors | Same thing: the log file itself is unwritable. |
| No runs generated this morning | Is cron running? `php artisan schedule:list`. Then working days and holidays for the site. |
| Nothing is ever marked missed | Same cron. `checklists:mark-missed` is hourly. |
| Signatures show as broken images | `php artisan storage:link`, and check `storage/app/public/signatures` exists and is readable. |
| Tablet shows "not enrolled" | Cookie gone — re-enrol per §7. |
| A QR sticker opens "machine not found" | The machine code was changed after printing — §8. |
| Compliance % looks wrong | It is `completed / (completed + missed + outstanding)` over scheduled runs; a window with nothing due shows `—`, not 0%. See `App\Support\Reporting\Compliance`. |

Health checks worth wiring into monitoring: `/login` returns 200; the
scheduler ran within the last hour; the queue has no jobs older than a few
minutes; disk space on `storage/`.
