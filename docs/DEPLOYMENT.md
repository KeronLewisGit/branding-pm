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
`admin@example.com` (override with `ADMIN_EMAIL`).

The password comes from `ADMIN_PASSWORD`. **If that is unset, a random one is
generated and printed once**, like this:

```
========================================================================
ADMIN PASSWORD — SHOWN ONCE, NOT RECOVERABLE
========================================================================
  Sign in with: admin@example.com  (or ADMIN-0001)
  Password:     kQ2m7ZrxT9vBnL4sPfWdHc1e
========================================================================
```

Write it down before that scrollback is gone. Nothing stores it in the clear
and no command can print it again; if it is lost, reset it with
`php artisan tinker` and `$u->update(['password' => 'new'])`.

> This used to fall back to a constant in the repository. That is a published
> credential — the source stated the admin password of every install where
> nobody changed it, and a printed warning is not a control. `security:check`
> still tests for that old default, in case an install predates the change.

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

## 6. Before go-live — verify, do not assume

```bash
php artisan security:check --strict
```

It exits non-zero on anything wrong, so it can gate a deploy script. Every
item is something that lives on the host, not in the code, and so cannot be
enforced from inside it:

| Check | Why it fails a production host |
|---|---|
| `APP_DEBUG` | An error page prints the stack trace, the failing query and the whole `.env` — `APP_KEY` and the database password included |
| `APP_KEY` | Keys sessions, cookies **and** the run-sheet verification hashes |
| Secure cookies | A session cookie sent over plain HTTP can be read off the shop-floor Wi-Fi and replayed |
| `APP_URL` | Not HTTPS means PINs and passwords cross the network in the clear |
| Signature storage | On the `public` disk, signatures are guessable unauthenticated URLs |
| Demo accounts | `OP-1001` and friends ship with the password `password` and PINs that are in the repository |
| Admin password | Still the old published default |

### The three settings that matter

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-host
```

`APP_ENV=production` is the switch. On it, **`SESSION_SECURE_COOKIE` defaults
to true on its own** — session and kiosk cookies become HTTPS-only without
anyone remembering to set it.

> **That means the site must be on HTTPS first.** With Secure cookies on and
> the site still on `http://`, the browser will not send the cookie back:
> nobody can log in, and every enrolled tablet looks un-enrolled. If you are
> running a plain-HTTP pilot, set `SESSION_SECURE_COOKIE=false` explicitly and
> treat it as a debt, not a setting — `security:check` will keep reporting it.

A production host also logs a `critical` line on every boot while `APP_DEBUG`
or insecure cookies are on. It does not refuse to start: taking the checklists
off the shop floor over a configuration mistake is worse than the mistake, and
an application that will not boot gets "fixed" by deleting the check.

---

## 7. Production caches

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

## 8. Kiosk tablets

A tablet must be enrolled **once** before the kiosk will open on it. Until it
is, `/kiosk` returns the "not enrolled" screen — and a clean install has no
devices at all, so this is the first thing to do after seeding.

1. **Admin → Kiosk Devices → Add a device.** Give it a name somebody can find
   on a shelf ("Digital Print — wall tablet") and pick the **device type**:
   tablet, laptop, desktop, phone or other. The token is generated for you and
   never displayed; it is the shared secret between this row and the device's
   cookie.
2. **Set up.** The device type decides which method is offered first, because
   the two are not interchangeable:

   - **Tablet or phone** → a QR code and a link, both good for 15 minutes.
     Scan it with the device's camera.
   - **Laptop, desktop or other** → **Enrol this browser**, which turns the
     browser you are reading in into the kiosk. You cannot scan a QR code with
     the screen displaying it.

   Both methods are always shown, so picking the wrong type costs a scroll and
   not a dead end. Either way the device opens the kiosk and stays enrolled —
   the cookie lasts about five years.

   The list flags a device being used from hardware that does not match what
   was recorded — a "tablet" driven from a laptop usually means an enrolment
   link was opened on the wrong machine. It is read from a User-Agent, so it
   is a hint, never a block.

   The link carries its own signature and needs **no login**, which is the
   point: the alternative is typing an admin password into a shared shop-floor
   device in front of whoever is standing there. Anyone who opens the link
   before it expires turns their own browser into that kiosk; they would still
   need an operator PIN to record anything, and you can revoke it (below).
   Generate a fresh link rather than saving or forwarding one.
3. **Add the site to the home screen** — `start_url` is `/kiosk`, and it
   launches standalone with no browser chrome. Note this needs HTTPS; iOS in
   particular will not register the service worker over plain `http://`, so
   over HTTP you get a browser tab and no offline queue.
4. Re-enrol after a factory reset, a browser-data wipe, or a change of tablet.

**When a tablet goes missing.** Two buttons, and the difference matters:

| Button | Effect |
|---|---|
| **Deactivate** | The tablet is locked out on its very next tap. Reversible — activate it and the same tablet works again, no re-enrolment. |
| **Un-enrol** | Rotates the token, so every browser enrolled as this device drops to the "not enrolled" screen. The entry and its history survive, so the replacement tablet enrols under the same name. Not reversible — the old tablet must be enrolled again. |
| **Delete** | Removes the entry entirely. Use Un-enrol instead unless the device is genuinely gone for good. |

Both take effect immediately: `EnsureKioskDevice` looks the token up on every
request, through an `is_active` scope. There is no cache to wait out.

**The kiosk address must be stable.** Tablets remember the URL they were
enrolled on, and the PWA `start_url` is absolute. A DHCP lease that moves will
strand every tablet. Give the host a DHCP reservation or a DNS name before
enrolling anything.

### Running the kiosk on a laptop or desktop

The kiosk is built for a 10" tablet but is usable on a PC, and during a pilot
that is often a laptop serving the app to itself. Three things to know.

**Enrolling.** You cannot scan a QR code with the screen displaying it. The
enrolment modal has **Enrol this browser** for exactly this — it turns the
current browser into that kiosk directly.

**Use a separate browser profile for admin work.** This one bites. Signing in
with an operator PIN calls `Auth::login()` on the same session, so it
*replaces* whoever is logged in — PIN in as an operator and your admin session
is gone; the two-minute idle drop then logs that session out entirely. One
browser cannot be both the admin console and the kiosk. A second browser
profile, or a different browser, keeps them apart.

**`http://localhost` is a secure context.** This is the one advantage a PC
kiosk has over a tablet on the LAN: browsing to `http://localhost:8088/kiosk`
registers the service worker, so the PWA installs and the offline queue works
without any HTTPS setup. Over a LAN IP — `http://192.168.x.x` — it does not,
on any browser. If you want to exercise offline behaviour before you have
certificates, do it on the machine running the app.

To run it fullscreen with no browser chrome:

```
chrome.exe --kiosk --app=http://localhost:8088/kiosk
msedge.exe --kiosk --app=http://localhost:8088/kiosk
```

**Idle timeout.** Two minutes suits a shared tablet on a shop floor; on a
laptop being used for a pilot it is usually too short. Set
`CHECKLISTS_KIOSK_IDLE_SECONDS` in `.env` — both the browser countdown and the
server check read it.

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

## 9. QR stickers

`/admin/machines/qr` prints the sheet. Each sticker encodes `/m/{code}` and
prints the code in plain text beneath, because a sticker in a print shop gets
scuffed and over-sprayed, and a code that can be typed still works.

**Machine codes are permanent once printed.** Changing a code invalidates
every sticker already on that machine — the admin screen warns you, and the
warning is not decorative. If a code must change, print and fit the
replacement sticker in the same visit.

---

## 10. Backups

Back up two things; either alone is useless:

1. **The database** — the entire record.

   Under Docker this is **already running**: the `backup` service dumps it
   nightly at `BACKUP_TIME` (default 02:30 plant time) into `storage/backups/`
   on the host, keeps `BACKUP_RETENTION_DAYS` (default 14), and writes a
   `.sha256` beside each one. Confirm it with:

   ```bash
   php artisan backup:status     # exits 1 if the newest dump is stale
   ```

   On a native install without Docker, run the equivalent from cron:
   ```bash
   mysqldump --single-transaction --routines branding_pm | gzip > branding-pm-$(date +%F).sql.gz
   ```
2. **`storage/app`** — signatures and photos. The database references them by
   path; without the files, approved runs lose the signatures that made them
   approvable. This is **not** covered by the `backup` service; it has to be
   part of whatever backs up the host.

Also keep `.env` somewhere safe and separate: `APP_KEY` decrypts sessions and
keys the run-sheet verification hashes. Restore the database without the
original `APP_KEY` and every printed sheet's verification code stops matching.

Do not keep the dumps only on the same machine as the database. A failed disk
or a stolen host takes both. Copy `storage/backups/` somewhere else on a
schedule of its own.

**Test a restore before you need one** — into a scratch database, comparing
row counts against the live one:

```bash
gunzip -c storage/backups/branding_pm-YYYY-MM-DD_HHMMSS.sql.gz \
  | docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" restore_test
```

A backup nobody has restored is not yet a backup.

---

## 11. Upgrading

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

## 12. When something is wrong

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
