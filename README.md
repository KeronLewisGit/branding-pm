# Branding & Signage — Preventive Maintenance Checklist System

Digital replacement for the paper PM work orders used by the Branding & Signage
department. One sheet per machine per shift, completed on a shop-floor tablet,
signed by an operator and countersigned by a supervisor, with a searchable and
auditable history behind it.

- **Stack:** PHP 8.3 · Laravel 11 · MySQL 8 · Livewire 3 · Tailwind · Alpine
- **No Redis, no paid packages, no external SaaS.** The plant may be offline.
- **Timezone:** stored UTC, displayed `America/Port_of_Spain`.

> **Build status: all 8 milestones built.** See [Current status](#current-status).

---

## Contents

- [Current status](#current-status)
- [Requirements](#requirements)
- [Local setup — native PHP](#local-setup--native-php)
- [Local setup — Docker (optional)](#local-setup--docker-optional)
- [`.env` reference](#env-reference)
- [Database: migrate and seed](#database-migrate-and-seed)
- [Mail](#mail)
- [Backups](#backups)
- [Scheduler](#scheduler)
- [Queue worker](#queue-worker)
- [Running the tests](#running-the-tests)
- [Production deployment checklist](#production-deployment-checklist)
- [How the system works](#how-the-system-works)
- [Project documents](#project-documents)

---

## Current status

| # | Milestone | State |
|---|---|---|
| 1 | Scaffold, auth, roles, migrations, master-data seeders, README | Built |
| 2 | Machine + template admin CRUD | Built |
| 3 | Run generation command + run listing/kiosk | Built |
| 4 | Run completion form | Built |
| 5 | Signatures, submission, supervisor approval/rejection | Built |
| 6 | Issues register | Built |
| 7 | Dashboards + reports + CSV/PDF export | Built |
| 8 | PWA/offline, QR stickers, tests, deployment docs | Built |

The database schema covered the **whole** domain from milestone 1 — including
issues, attachments and signatures — so milestones 5–8 added screens, not
migrations. Exactly one migration has been added since the original 22
(`kiosk_devices.kind` and `last_user_agent`, so a kiosk can be a laptop or a
panel PC rather than only a tablet).

Not yet built, and outstanding against [`docs/SPEC.md`](docs/SPEC.md):

- **Admin → settings.** The `settings` table is seeded but nothing reads it —
  every value it holds actually comes from `config/checklists.php` via `.env`,
  so editing a seeded setting has no effect. Either give it a screen and read
  from it, or drop the table.

Two things to know before you run it:

1. **It runs.** Authored on a machine with no PHP, Composer or MySQL, so for
   most of its life this was "correct by inspection" only. It has now been
   brought up under the shipped `docker-compose.yml` and exercised: all 22
   migrations apply, the seeders load, **all 34 Pest tests pass**,
   `checklists:generate` produced 12 runs and then correctly created 0 on a
   second pass, and the login, dashboard, kiosk enrolment and machine grid all
   serve. Nothing needed fixing to get there.

   Still unexercised by anything automated: the signature canvas, the service
   worker and the offline queue. All three need a real touch device.
2. **The 13 source PDFs were not available.** Every checklist item was
   transcribed from the written specification instead. Before the paper forms
   are retired, someone must compare the seeded templates against the printed
   sheets. See [`docs/seed-notes.md`](docs/seed-notes.md).

---

## Requirements

| | Version |
|---|---|
| PHP | 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `gd` (PDF + QR), `zip`, `intl` |
| Composer | 2.x |
| MySQL | 8.0+ (InnoDB, utf8mb4) |
| Node | 20+ (build-time only — the server does not need Node) |

`gd` is required: `barryvdh/laravel-dompdf` and the QR sticker generator both
need it. Installing without it will appear to work until the first PDF export.

---

## Local setup — native PHP

Works with a plain PHP install, Laragon, Herd or XAMPP.

```bash
git clone <repo> branding-pm
cd branding-pm

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```sql
CREATE DATABASE branding_pm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_DATABASE=branding_pm
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate --seed
php artisan storage:link          # signatures and photos are served from /storage
npm run build                     # or: npm run dev
php artisan serve
```

Visit <http://127.0.0.1:8000>. Sign in with the admin account created by
`AdminUserSeeder` (see [Database](#database-migrate-and-seed)).

**Want demo data?** The seeders ship clean by design. To get 30 days of history,
demo users and populated dashboards:

```bash
php artisan db:seed --class=DemoSeeder
```

Never run that on production.

---

## Local setup — Docker (optional)

Docker is **not** required. It exists so a new developer can start without
installing PHP locally; the production target is Apache or Nginx + PHP-FPM.

```bash
cp .env.example .env
# uncomment the Docker block in .env (DB_HOST=mysql, etc.)

docker compose up -d
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
docker compose exec php php artisan storage:link
```

App on <http://localhost:8080>.

| Service | Purpose |
|---|---|
| `php` | PHP 8.3-FPM with the required extensions |
| `nginx` | Web server, document root `/var/www/html/public`, host port 8080 |
| `mysql` | MySQL 8, utf8mb4, named volume, no host port published |
| `mailpit` | Mail server. SMTP on 1025, inbox at <http://localhost:8025> |
| `scheduler` | Runs `php artisan schedule:work` — the cron replacement |

The `scheduler` service means run generation works out of the box under Docker.
On a native install you must add the [cron line](#scheduler) yourself.

### Mail

Password reset is the only thing this system emails. Under Docker, `mailpit`
accepts it and shows it in a web inbox — **nothing leaves the machine**, which
is what the pilot wants: a reset you can watch arrive beats one that vanishes
into a misconfigured relay.

Open <http://localhost:8025> (or `http://<pilot-host>:8025`) to read it.

On a native install without Docker, leave `MAIL_MAILER=log` and read
`storage/logs/laravel.log`.

### Backups

The `backup` service dumps the database every night at `BACKUP_TIME` (default
`02:30` plant time) and keeps `BACKUP_RETENTION_DAYS` of them (default 14).

Dumps land in **`storage/backups/`** — on the host, not in a Docker volume.
That is the point: `docker compose down -v` destroys named volumes, so a
backup kept in one would be destroyed by the same command that destroys the
database it protects. They are gitignored; a dump contains every operator's
name and every password and PIN hash.

Each dump is gzipped, checked with `gzip -t`, size-checked (a suspiciously
small dump is refused rather than allowed to replace a good one), and written
alongside a `.sha256`.

```bash
php artisan backup:status            # newest backup, age, size, checksum
docker compose run --rm --entrypoint /usr/local/bin/backup.sh backup once
```

`backup:status` **exits 1 when the newest backup is stale**, so it can be
monitored rather than remembered. `security:check` reports the same thing.

**Restoring:**

```bash
gunzip -c storage/backups/branding_pm-YYYY-MM-DD_HHMMSS.sql.gz \
  | docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" branding_pm
```

Restore into a scratch database first and compare row counts. A backup nobody
has restored is not yet a backup.

**On go-live**, point `MAIL_HOST` / `MAIL_PORT` at the company relay, set
`MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_SCHEME`, and use a `MAIL_FROM_ADDRESS`
on a domain the relay will vouch for — an unroutable from-address is the usual
reason these land in junk. Then drop the `mailpit` service; nothing in the
application refers to it. `php artisan security:check` fails if a production
install is still writing reset emails to a log file.

---

## `.env` reference

Full list in `.env.example`. The ones that matter:

| Key | Default | Notes |
|---|---|---|
| `APP_URL` | `http://localhost` | Must be correct — signed attachment URLs and QR deep links are built from it |
| `APP_TIMEZONE` | `UTC` | **Leave as UTC.** All timestamps are stored in UTC |
| `APP_DISPLAY_TIMEZONE` | `America/Port_of_Spain` | What operators see, and the timezone run generation uses to decide "today" |
| `DB_*` | — | MySQL 8, utf8mb4 |
| `QUEUE_CONNECTION` | `database` | No Redis |
| `CACHE_STORE` | `file` | No Redis |
| `SESSION_DRIVER` | `database` | Survives a PHP-FPM restart mid-shift |
| `FILESYSTEM_DISK` | `local` | Attachments use the `public` disk explicitly |
| `ADMIN_PASSWORD` | `ChangeMe!2026` | Read by `AdminUserSeeder`. **Set this before seeding production** |
| `CHECKLISTS_GENERATION_TIME` | `05:00` | Before day shift starts |
| `CHECKLISTS_KIOSK_IDLE_SECONDS` | `120` | Kiosk drops to the picker after this |
| `CHECKLISTS_PIN_MAX_ATTEMPTS` | `5` | Then lockout |
| `CHECKLISTS_PIN_LOCKOUT_MINUTES` | `15` | |

`APP_TIMEZONE` and `APP_DISPLAY_TIMEZONE` are deliberately separate. Storing
local time makes the compliance history unreconcilable the first time the
server moves or a date-range report crosses a boundary.

---

## Database: migrate and seed

```bash
php artisan migrate --seed
```

`DatabaseSeeder` is **production-safe** and runs, in order:

| Seeder | Creates |
|---|---|
| `RolesAndPermissionsSeeder` | 4 roles, 24 permissions, cumulative grants |
| `SiteSeeder` | `Branding 23 — {Scarlet Ibis}` (`BR23`), Mon–Sat working week |
| `LocationSeeder` | Digital Print, Digital Finishing, Production Floor, ESKO Router Room |
| `PartSeeder` | The 10-part catalogue |
| `MachineSeeder` | The 11 machines + their normal parts |
| `ChecklistTemplateSeeder` | The 13 templates, 82 items, verbatim |
| `HolidaySeeder` | T&T public holidays |
| `SettingSeeder` | Defaults |
| `AdminUserSeeder` | One admin account |

`DemoSeeder` is **excluded** so production installs stay clean. Run it
explicitly if you want history: `php artisan db:seed --class=DemoSeeder`.

Set `ADMIN_PASSWORD` in `.env` before seeding production. The seeder prints a
warning if you leave the default.

---

## Scheduler

Run generation depends on this. Without it, no checklists appear.

**Native — one cron line, nothing else:**

```cron
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

**Docker:** the `scheduler` service already does this.

| Command | When | Does |
|---|---|---|
| `checklists:generate` | Daily 05:00 local | Creates `pending` runs for every template due today |
| `checklists:mark-missed` | Hourly | Flips untouched runs to `missed` past their grace period |

Both are idempotent — safe to re-run by hand at any time:

```bash
php artisan checklists:generate --date=2026-07-28 --dry-run   # preview
php artisan checklists:generate                                # for real
php artisan checklists:mark-missed
```

Daily templates produce **two** runs per machine per day (day shift and night
shift). Weekly and general templates produce one.

Missed runs are never deleted. A gap in the record is the record.

---

## Queue worker

Queue driver is `database`. **Nothing currently queues** — there is no
`app/Jobs`; CSV exports are streamed and PDFs rendered inline on the request.
A worker is therefore optional today. Run one anyway if you are setting up a
production host, so the seam exists before the first slow export needs it.

```bash
php artisan queue:work --tries=3 --timeout=120
```

Supervisor config for production:

```ini
[program:branding-pm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/branding-pm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/branding-pm-worker.log
stopwaitsecs=3600
```

---

## Running the tests

```bash
php artisan test          # or: ./vendor/bin/pest
```

`phpunit.xml` points at `branding_pm_test` — create it first:

```sql
CREATE DATABASE branding_pm_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

34 Pest feature tests cover run generation (per-shift, idempotent, working days,
holidays, inactive templates), the missed-run job either side of the grace
period, required-item enforcement, signature and PIN confirmation, approval and
rejection, the two-person rule, issue transitions and scoping, the compliance
definition, and export permissions.

The signature canvas itself is **not** covered — it is JavaScript on a touch
device and still needs a human with a tablet.

---

## Production deployment checklist

Run through this in order. Steps 1–4 are the ones that bite.

**1. Environment**

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pm.yourdomain.local
ADMIN_PASSWORD=<something real>
```

`APP_DEBUG=true` in production leaks the database credentials on the first
stack trace.

**2. Permissions** — the web user needs write access to exactly two paths:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```

**3. Build and cache**

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Re-run the three `:cache` commands after **every** deploy or `.env` change.
A cached config ignores `.env` entirely.

**4. Document root is `/public`** — not the project root. Getting this wrong
exposes `.env` over HTTP. Verify: `curl https://your-host/.env` must **not**
return the file.

**5. Scheduler** — add the [cron line](#scheduler). Confirm with
`php artisan schedule:list`.

**6. Queue worker** — Supervisor config above.

**7. HTTPS** — required. The kiosk uses the tablet camera for photo capture,
and browsers block `getUserMedia` on insecure origins, so photo capture simply
will not work over plain HTTP.

**8. Backups** — under Docker the `backup` service already does the database
nightly (see [Backups](#backups)). You still need `storage/app` (signatures and
photos) in whatever backs up the host: signature images are part of the audit
record, and a database backup without them is not a complete record.

### Apache

`mod_rewrite` required. Laravel ships the `.htaccess`.

```apache
<VirtualHost *:80>
    ServerName pm.yourdomain.local
    DocumentRoot /var/www/branding-pm/public

    <Directory /var/www/branding-pm/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/branding-pm-error.log
    CustomLog ${APACHE_LOG_DIR}/branding-pm-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite && sudo systemctl restart apache2
```

### Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name pm.yourdomain.local;
    root /var/www/branding-pm/public;

    index index.php;
    charset utf-8;

    client_max_body_size 12M;   # tablet photos

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;
}
```

`client_max_body_size` must exceed PHP's `upload_max_filesize` /
`post_max_size`, or photo uploads fail with a bare 413 and no Laravel error.

---

## How the system works

**Scheduling.** `checklists:generate` runs at 05:00 and creates a `pending`
run for every active template due that day, skipping Sundays, seeded public
holidays and inactive machines. Daily templates create one run per shift.

**Completing a run.** An operator scans the QR sticker on the machine
(`/m/{code}`) or taps its tile on the kiosk grid, opens the sheet for their
shift, taps each row, records parts used, adds notes and photos, and submits.
Responses save immediately — nothing is lost if the tablet sleeps. Submission
is blocked while any required item is unanswered, and the unanswered ones are
named.

**Sign-off.** A supervisor reviews submitted runs and approves or rejects with
a comment. A rejected run reopens for the operator with the reason shown.
Approved runs are immutable — corrections are admin-only amendments, logged.
A supervisor cannot approve a run they operated themselves; the paper form has
two signature blocks, and one person signing both defeats the control.

**Audit.** Nothing that constitutes a record is hard-deleted. Every state
change, signature and template edit is attributed via
`spatie/laravel-activitylog`. Run items snapshot their wording at generation
time, so a run completed in March still shows March's form even after the
template is edited.

**Roles.** Operator → Supervisor → Maintenance Manager → Admin, cumulative.
Operators see only their assigned machines. Authorisation is enforced in
policies and re-checked in every Livewire action, never in Blade alone.

---

## Project documents

| Document | What it is |
|---|---|
| [`docs/BUILD-CONTRACT.md`](docs/BUILD-CONTRACT.md) | Authoritative schema, enums, routes and permission names. Read before changing anything structural |
| [`docs/SPEC.md`](docs/SPEC.md) | The original functional specification |
| [`docs/seed-notes.md`](docs/seed-notes.md) | **Read this.** Preserved typos, decisions taken, deviations from the spec, and open questions for the Maintenance Manager |
| [`docs/data-model.md`](docs/data-model.md) | ER diagram, delete behaviour, the snapshot columns and the run/issue lifecycles |
| [`docs/user-guide.md`](docs/user-guide.md) | One guide each for operator, supervisor and maintenance manager |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Install, permissions, scheduler, kiosk enrolment, backups, upgrades, troubleshooting |
| `docs/source-checklists/` | The 13 source PDFs — **currently empty, please add them** |
