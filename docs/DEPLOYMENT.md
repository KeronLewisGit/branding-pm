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

Installing on shared hosting rather than a server you control? Read
[§13](#13-hostinger-cloud-startup-shared-hosting) instead of §2–§7 — the
application is unchanged, but the install, cron, TLS and backups all differ.

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

### HTTPS on a closed plant network

The hardest go-live item, because a plant LAN has no public DNS and often no
inbound internet. Three workable routes, worst to best for this site.

**Two things are at stake, not one.** Secure cookies and encrypted PINs are
the obvious half. The other is that **the PWA and the offline queue do not
work at all over plain HTTP** — `navigator.serviceWorker` is undefined outside
a secure context, so `registerServiceWorker()` returns early and nothing is
ever cached. Milestone 8's "keep working through a Wi-Fi outage" is inert on
an `http://` host. That is not a warning about the future; it is the state of
the pilot today.

#### Option A — self-signed certificate

Free and offline, and the cost lands on every device forever. Each tablet,
phone and laptop must install the certificate and explicitly trust it (on iOS
that is a configuration profile *plus* **Settings → General → About →
Certificate Trust Settings**, a step people miss). A new tablet on the floor
means doing it again; so does expiry. Workable for three devices, miserable
for fifteen.

#### Option B — the company's internal CA

If there is a Windows domain with AD CS, domain-joined machines trust it
automatically and only the tablets need the root certificate installed once.
Better than A, and it is the right answer if IT already runs a CA. Ask before
building anything.

#### Option C — a real certificate for a real name (recommended)

Register or reuse a subdomain the company controls — `pm.example.com` — and
issue a Let's Encrypt certificate with a **DNS-01** challenge. DNS-01 proves
ownership by writing a TXT record, so **no inbound internet to the plant is
required**; only outbound access for renewal.

Then point an internal DNS record for that name at the server's fixed LAN
address. Every device trusts it with **zero per-device setup**, which is the
whole argument: on a shop floor, anything requiring a manual step per tablet
will eventually not be done.

A reverse proxy in front of the existing `nginx` container handles both the
certificate and the renewal — Caddy does DNS-01 with a few lines of config.

### The reverse proxy that ships with this

A `caddy` service is included, behind the `tls` profile so it is opt-in:

```bash
docker compose --profile tls up -d
```

Caddy rather than certificates in nginx because **renewal** is the part that
fails eighteen months later on a Sunday. Caddy renews by itself; a
hand-installed certificate is a diary entry somebody has to keep.

Configure it in `.env`:

```dotenv
TLS_SITE_ADDRESS=192.168.0.14     # the name or address browsers will use
HTTPS_PORT=8443
HTTP_REDIRECT_PORT=8080
HTTPS_PUBLIC_SUFFIX=:8443         # empty when HTTPS is on the standard 443
```

`HTTPS_PUBLIC_SUFFIX` is not redundant. Caddy listens on 443 *inside* its
container and cannot see which port Docker published; without it the
HTTP→HTTPS redirect sends browsers to a port with nothing on it. On the pilot
host both 80 and 443 are already held by Docker Desktop's WSL relay, which is
why the non-standard ports exist at all.

#### Three things that will bite

**An IP address is not a hostname, and TLS notices.** SNI carries the name a
client is asking for, and RFC 6066 forbids an IP literal in it — so a browser
opening `https://192.168.0.14` sends no SNI at all, Caddy has nothing to match
a certificate against, and the handshake dies as "internal error". The
`default_sni` global option in the Caddyfile works around it. It is a
workaround: no public CA will issue for an IP, so this pins you to the local
CA. **Get the hostname.**

**Every device must trust the local CA**, until there is a real certificate.
Export the root:

```bash
docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt .
```

On iOS that is an installed profile **and** a second step under
Settings → General → About → Certificate Trust Settings, which people miss and
then report the tablet "saying not secure".

**Stop publishing nginx's port.** Until you delete the `ports` entry from the
`nginx` service, the app answers on plain HTTP as well, and everything TLS
buys is optional for anyone who knows the port. It is also the only way the
forwarded-header trust becomes sound: Docker's NAT rewrites the source of a
direct request to the bridge gateway, a private address, so nginx cannot tell
a request that came through Caddy from one that did not.

#### Cutover, in order

1. `docker compose --profile tls up -d`, and confirm it serves.
2. Install the CA root on every tablet, or get a real certificate first.
3. Delete the `ports:` entry from the `nginx` service and `up -d` again.
4. Set `APP_URL=https://<name>` — **and only now print QR stickers**, since
   they encode it.
5. `APP_ENV=production`, which turns on secure cookies and HSTS by itself.
6. `php artisan security:check`.

### If you put anything in front of the app

Laravel decides whether to generate `http://` or `https://` URLs from the
request it sees. Behind a terminating proxy it sees plain HTTP and will emit
`http://` links on an `https://` page unless the proxy is trusted — configure
`TrustProxies` and have the proxy send `X-Forwarded-Proto`.

**Be careful with `X-Forwarded-Host`.** This project has already been bitten:
setting it broke every signed URL, because the host it forwarded omitted the
port and signature verification includes the host. Kiosk enrolment failed
silently. Send `X-Forwarded-Proto`, and leave `X-Forwarded-Host` alone unless
you have a reason and a test.

### Settle the address before printing stickers

Every QR sticker encodes `APP_URL`. Moving from `http://192.168.0.14:8088` to
`https://pm.example.com` **invalidates every sticker already on a machine**.
Do the certificate and the hostname first, then print. See §9.

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

**So is the address.** A sticker encodes the full URL, `APP_URL` included. If
the server's address changes — a new DHCP lease, a move to HTTPS, a different
host — every sticker on the floor becomes dead paper at the same moment.

Before printing a single sheet:

- give the server a **fixed address**: a DHCP reservation at minimum, and
  preferably a hostname, so the URL survives a reboot or a router change;
- settle **HTTPS** first if it is coming, because `https://` is a different
  URL from `http://` (see §6).

`http://192.168.0.14:8088` is a DHCP address. It has already moved more than
once during the pilot. It is not something to print and glue to a machine.

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
   approvable.

   The `backup` service covers this too: each night it writes
   `storage-<stamp>.tar.gz` beside the dump, taken **after** the database so
   every path in the dump is certain to be in the archive. Restore the pair
   from the same timestamp:

   ```bash
   tar -xzf storage/backups/storage-<stamp>.tar.gz -C storage/app
   ```

Also keep `.env` somewhere safe and separate: `APP_KEY` decrypts sessions and
keys the run-sheet verification hashes. Restore the database without the
original `APP_KEY` and every printed sheet's verification code stops matching.

### Off-site copies

Dumps kept only on the pilot host do not survive a failed disk, a fire or a
theft. The `backup-offsite` service copies them nightly to a network share,
verifying each by reading it back from the share and comparing checksums.

```ini
BACKUP_OFFSITE_SHARE=//fileserver/backups/branding-pm   # forward slashes
BACKUP_OFFSITE_USERNAME=svc-pmbackup
BACKUP_OFFSITE_PASSWORD=…
BACKUP_OFFSITE_TIME=03:30
BACKUP_OFFSITE_RETENTION_DAYS=30
```

```bash
docker compose --profile offsite up -d
```

#### Turning it on at this site

The share is already set to `//LH-ARCHIVE/Archive/branding-pm` in `.env`. Two
values are missing, and the service will not start without them.

1. **Ask IT for a service account** with write access to that share and
   nothing else. A dump holds every password and PIN hash in the plant, so it
   should not be somebody's personal login and it should not be an account
   that can read the rest of the file server.
2. Put it in `.env` — never in `docker-compose.yml`, which is committed:
   ```dotenv
   BACKUP_OFFSITE_USERNAME=svc-pmbackup
   BACKUP_OFFSITE_PASSWORD=…
   BACKUP_OFFSITE_DOMAIN=LHOUSE      # WORKGROUP for a standalone NAS
   ```
3. **Create the `branding-pm` folder on the share first.** The mount fails if
   the path does not exist, and the failure looks like a credential problem.
4. Be on the plant network. Docker mounts the share when the container
   starts; from anywhere else it cannot.
5. Bring it up, then prove it rather than assuming:
   ```bash
   docker compose --profile offsite up -d
   docker compose ps backup-offsite          # must be Up, not Restarting
   docker compose logs backup-offsite --tail 20
   docker compose run --rm --entrypoint /usr/local/bin/offsite.sh backup-offsite once
   php artisan backup:status                 # Off-site should read OK
   ```

Until the credentials are in, `backup:status` reports *"awaiting credentials,
not running yet"* and stays green — the share being chosen is not the same as
it being broken. Once the username is set, a missing or stale off-site copy
becomes a failure.

**If the container will not start**, it is almost always the mount: a wrong
password, a missing folder, a domain that should not be `WORKGROUP`, or SMB1
on the far end. `docker compose logs backup-offsite` shows the mount error.

Three things worth knowing before you rely on it:

- **The service account needs write access to that share and nothing else.**
  A dump holds every password and PIN hash in the plant.
- **It is behind a compose profile on purpose.** Docker mounts the share at
  container start, so a bad credential or an unreachable server fails the
  mount — and unprofiled, that would stop the whole stack over a secondary
  copy. Check it started: `docker compose ps backup-offsite`.
- **Its failure mode is silence.** An unreachable share means the container
  never starts, so nothing records the failure. `backup:status` and
  `security:check` therefore judge it on when the copier last *ran*, and both
  fail past `BACKUP_OFFSITE_MAX_AGE_HOURS` (default 36). Monitor
  `backup:status`; do not rely on somebody noticing.

The share is still on the same site. If the requirement is to survive losing
the building, that needs a second destination — the same service handles any
path Docker can mount, so a cloud-backed share works without code changes.

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

---

## 13. Hostinger Cloud Startup (shared hosting)

Everything above assumes a server you control. This section is for the shared
LiteSpeed hosting instead — no root, no Docker, no daemons. The application
itself needs no changes: sessions and queue are `database`, cache is `file`,
storage is `local`, and nothing here depends on Redis or a long-running
worker. What changes is how it is installed and what you have to arrange
yourself.

**Read this first.** This system was specified for a shop floor where "the
plant may be offline". Hosting it externally means every kiosk tablet needs
working internet to log a checklist, and the app moves from a plant-internal
network to the public web with device enrolment as the only gate. That is a
defensible choice for remote access to the record. It is the wrong choice if
the tablets must keep working when the line's uplink does not.

### 13.1 Verify before committing

| Check | Where in hPanel | If it is not there |
|---|---|---|
| **PHP 8.4** | Advanced → PHP Configuration | Stop. `spatie/laravel-activitylog` 5 and `pestphp/pest` 5 both require `^8.4`; 8.3 will not install. |
| **Document root can be changed** | Websites → Dashboard → Advanced | Stop, or you are patching `public/index.php` — a permanent fork of the framework skeleton. |
| **SSH access** | Advanced → SSH Access | Needed for `composer install` and `artisan migrate`. |

Extensions to confirm on the PHP page: `bcmath`, `exif`, `gd`, `intl`,
`mbstring`, `openssl`, `pdo_mysql`, `zip`. While you are there raise
`upload_max_filesize` and `post_max_size` to **at least 12M** —
`RunForm::photoRules()` accepts images up to 10 MB, and a lower PHP limit
fails as a bare 413 with no Laravel error behind it.

### 13.2 Install

Build the assets on your own machine first: `public/build` is gitignored, so a
clone has none, and shared hosting is a poor place to run Node.

```bash
npm ci && npm run build          # locally, then upload public/build with the rest
```

Upload the project to `~/domains/<domain>/branding-pm` — **outside**
`public_html` — then point the website root at
`~/domains/<domain>/branding-pm/public` in hPanel. Do not copy `public/*` into
`public_html` and rewrite the require paths in `index.php`: that is the usual
shared-hosting shortcut and it has to be re-done on every upgrade.

Create the database and user under Databases → MySQL (Hostinger prefixes both
with the account ID), write `.env` per §2 with `DB_HOST=localhost`, then over
SSH:

```bash
cd ~/domains/<domain>/branding-pm
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
chmod -R ug+rwX storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

If the default `php` is not 8.4, use the versioned binary
(`/opt/alt/php84/usr/bin/php`) for **every** command, including the cron line
below. Mixing versions between CLI and web is its own afternoon.

`public/storage` is gitignored, so a git-based deploy simply has no symlink
until `storage:link` makes one. If you deploy by copying the working directory
instead, delete `public/storage` first — it points at the absolute container
path `/var/www/html/storage/app/public` and arrives dead.

Leave every `BACKUP_OFFSITE_*` value unset. That share is an SMB path on the
plant LAN and is unreachable from a datacentre; §10 covers what replaces it.

### 13.3 Cron

Advanced → Cron Jobs, every minute. Hostinger supports a 1-minute interval:

```
/opt/alt/php84/usr/bin/php ~/domains/<domain>/branding-pm/artisan schedule:run >> /dev/null 2>&1
```

No queue worker is needed. There is no `app/Jobs`, exports stream and PDFs
render inline — see §5. If that changes, a second cron running
`queue:work --stop-when-empty` is the shared-hosting substitute for Supervisor.

### 13.4 TLS

Websites → Security → SSL: install the free certificate and enable Force
HTTPS. This is not optional — the kiosk captures photos through `getUserMedia`,
which browsers refuse on insecure origins. The Caddy service and the `tls`
compose profile in §6 are Docker-only and play no part here.

### 13.5 Verify

```bash
php artisan security:check
php artisan schedule:list
curl -I https://<domain>/up            # 200
curl https://<domain>/.env             # must NOT return the file
```

Then two failures the commands above cannot see:

**Open a deep route** (`/runs`, `/admin/machines`), not just the home page.
This is where a missing or ignored `public/.htaccess` shows up — the front
page works and everything else 404s. `AllowOverride All` must be in force.

**Enrol a kiosk from a real tablet.** `bootstrap/app.php` trusts
`X-Forwarded-Proto` only from private ranges, which is right behind the Caddy
service. On LiteSpeed it is simply not used: PHP runs under LSAPI on the same
machine, so the server sets `HTTPS` and the real `REMOTE_ADDR` directly and
`$request->secure()` is already correct. Nothing to configure.

It becomes a problem only if you later put a CDN or external proxy in front.
Then `$request->secure()` returns false, generated URLs go out as `http://`,
and signed enrolment links fail their signature check — a silent 403 that
looks exactly like an expired link, the same failure §6 describes for
`X-Forwarded-Host`.

**`URL::forceScheme('https')` is not the fix, and it makes this worse.** It
changes URL *generation* only; `UrlGenerator::hasCorrectSignature()` rebuilds
the URL for *validation* from `$request->url()`, which still reports `http`.
Forcing the scheme therefore signs with one URL and verifies against another
— guaranteeing the 403 rather than curing it. The fix is to add the proxy's
address to the `trustProxies()` list so the request is genuinely seen as
secure.

Edit that list in `bootstrap/app.php` directly. Do **not** reach for
`env('TRUSTED_PROXIES')` there: that closure runs when the HTTP kernel is
resolved, which `Application::handleRequest()` does *before*
`$kernel->handle()` loads the environment — and `LoadEnvironmentVariables`
skips `.env` entirely once `config:cache` has run, which §7 requires. The
variable would read as null on every production host and the hardcoded
default would silently apply.

### 13.6 Backups

Hostinger's automatic daily backups on Cloud plans cover files and database,
and that is a real safety net — but restore one into a scratch database and
compare row counts before you rely on it. A backup nobody has restored is not
yet a backup.

Add your own copy on top, because the platform's backups leave with the
platform. A weekly cron taking `mysqldump` plus a `tar` of `storage/app`,
pulled down off-site, is enough. Both halves, from the same run: `checklist_runs`
stores signature *paths*, so a database restored without the files leaves
every approved run pointing at an image that is not there — §10 has the
reasoning and the restore order.

A dump holds every operator's name and every password and PIN hash. Wherever
it lands is as sensitive as the server.
