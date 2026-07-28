# BUILD CONTRACT — Branding & Signage PM Checklist System

**This file is the single source of truth for names.** Every agent working on this
codebase writes against the exact table names, column names, enum values, class
names and route names below. Do not rename, do not "improve", do not add columns
that are not listed. If something is genuinely missing, add it and append a note
to the bottom of `docs/seed-notes.md` — do not silently diverge.

Original functional spec: `docs/SPEC.md`.

---

## 0. Confirmed decisions (answered by the client)

| Question | Answer | Consequence |
|---|---|---|
| Toolchain | PHP/Composer/MySQL are **not installed** on the build machine | Author source only. Nothing is executed. Every file must be correct by inspection. `composer install && php artisan migrate --seed` must work first try. |
| Working days | **Mon–Sat**, Sunday excluded, public holidays excluded | `sites.working_days` JSON, default `[1,2,3,4,5,6]` (ISO-8601 weekday numbers). `holidays` table. |
| Shifts | **Two shifts — day and night** | Daily templates generate **one run per shift per machine per day**. See §2 `shift`. |
| Operator email | **Mixed** — some have company email, some do not | `users.email` nullable + unique. `users.password` nullable. Login form accepts **email OR employee number**. Kiosk PIN login works for everyone. |
| Build scope | **Milestones 1–4**, then stop for review | Milestones 5–8 are NOT in this pass. Do not build signatures/approval, issues UI, dashboards, reports, PDF export, PWA. Schema and models for them DO get built (migrations cover the whole domain). |
| CMMS/ERP feed | Not asked for now — assumed **no live integration** | Keep CSV export as the seam. No API client code. |

**Deviations from the written spec, deliberate, each logged in `docs/seed-notes.md`:**

1. `checklist_runs.shift` is **`enum('day','night','all')` NOT NULL default `'all'`**, not
   a nullable enum. Reason: the spec requires a unique index that makes the generator
   idempotent. In MySQL a `NULL` in a unique index does **not** collide with another
   `NULL`, so a nullable `shift` would let `checklists:generate` create unlimited
   duplicate runs for non-shift templates — the exact failure the index exists to
   prevent. `'all'` means "not shift-split".
2. `checklist_templates.per_shift` (bool) added. `true` → generator creates a `day` run
   and a `night` run. `false` → one run with `shift = 'all'`.
3. `checklist_templates.version` (int, default 1) — required by spec §"Template
   management" but absent from the spec's own column list.
4. `checklist_templates.weekly_weekday` (tinyint, ISO 1–7, default 1) and
   `monthly_day` (tinyint 1–28, default 1) — spec says weekly runs on "a configurable
   weekday per template" but lists no column for it.
5. `users.password` is nullable. PIN-only floor operators have no password.

---

## 1. Stack and versions

- PHP `^8.3`, Laravel `^11.0`
- `livewire/livewire ^3.5`
- `spatie/laravel-permission ^6.9`
- `spatie/laravel-activitylog ^4.8`
- `barryvdh/laravel-dompdf ^3.0` (installed now, used in milestone 7)
- `simplesoftwareio/simple-qrcode ^4.2` (installed now, used in milestone 8)
- `laravel/breeze ^2.1` (dev) — **do not run its installer**; the Blade auth views are
  hand-authored here because Composer cannot run on the build machine.
- Dev: `pestphp/pest ^3.0`, `pestphp/pest-plugin-laravel ^3.0`, `fakerphp/faker`
- Front end: `tailwindcss ^3.4`, `@tailwindcss/forms`, `alpinejs ^3.14`, `vite ^5.4`,
  `laravel-vite-plugin ^1.0`
- Queue `database`, cache `file`, session `database`, filesystem disk `public`.
  **No Redis anywhere.**
- Timezone: store UTC. `config('app.timezone') = 'UTC'`,
  `config('app.display_timezone')` = `env('APP_DISPLAY_TIMEZONE', 'America/Port_of_Spain')`.

---

## 2. Database schema — authoritative

Charset `utf8mb4`, collation `utf8mb4_unicode_ci`, engine InnoDB. All tables get
`timestamps()` unless stated. Master data gets `softDeletes()`. Deletes that would
orphan history are `restrictOnDelete()`.

Migration files are numbered so they run in this order. Use these exact filenames.

```
0001_01_01_000000_create_users_table.php          (users, password_reset_tokens, sessions)
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2025_01_01_000100_create_permission_tables.php    (spatie/laravel-permission, verbatim)
2025_01_01_000110_create_activity_log_table.php   (spatie/laravel-activitylog, verbatim)
2025_01_01_000200_create_sites_table.php
2025_01_01_000210_create_locations_table.php
2025_01_01_000220_create_machines_table.php
2025_01_01_000230_create_parts_table.php
2025_01_01_000240_create_machine_part_table.php
2025_01_01_000250_create_holidays_table.php
2025_01_01_000300_create_checklist_templates_table.php
2025_01_01_000310_create_checklist_template_items_table.php
2025_01_01_000320_create_checklist_template_parts_table.php
2025_01_01_000400_create_checklist_runs_table.php
2025_01_01_000410_create_checklist_run_items_table.php
2025_01_01_000420_create_checklist_run_parts_table.php
2025_01_01_000500_create_attachments_table.php
2025_01_01_000510_create_issues_table.php
2025_01_01_000600_create_user_machine_table.php
2025_01_01_000610_create_kiosk_devices_table.php
2025_01_01_000620_create_settings_table.php
```

### users
| column | type | notes |
|---|---|---|
| id | bigIncrements | |
| employee_number | string(32) | **unique**, indexed — this is the floor identifier |
| full_name | string(160) | |
| email | string(190) | **nullable**, unique |
| email_verified_at | timestamp nullable | |
| password | string | **nullable** — PIN-only operators have none |
| pin | string | **nullable**, `Hash::make()` of a 4–6 digit PIN. `hidden`. |
| pin_set_at | timestamp nullable | |
| is_active | boolean default true | |
| default_site_id | foreignId nullable | → sites, nullOnDelete |
| remember_token | rememberToken | |
| softDeletes | | |

Also `password_reset_tokens` and `sessions` exactly as Laravel 11 ships them.

### sites
`id`, `name` string(120), `code` string(32) unique, `working_days` json (default
`[1,2,3,4,5,6]`), `timezone` string(64) nullable, softDeletes.

### locations
`id`, `site_id` foreignId → sites restrictOnDelete, `name` string(120), `floor`
string(60) nullable, softDeletes. Unique `(site_id, name)`.

### machines
`id`, `location_id` foreignId → locations restrictOnDelete, `code` string(64)
**unique** (slug — this is what the QR sticker encodes, `/m/{code}`), `name`
string(160), `manufacturer` string(120) nullable, `model` string(120) nullable,
`asset_tag` string(64) nullable, `is_active` boolean default true, `notes` text
nullable, softDeletes.

### parts
`id`, `part_code` **string(32) unique** (external ID — one is literally `XXX`,
never cast to int), `name` string(190), `unit` string(32) nullable, `is_active`
boolean default true, softDeletes.

### machine_part (pivot)
`id`, `machine_id` → machines cascadeOnDelete, `part_id` → parts restrictOnDelete,
`sort_order` unsignedSmallInteger default 0. Unique `(machine_id, part_id)`.

### holidays
`id`, `site_id` foreignId **nullable** → sites cascadeOnDelete (null = all sites),
`date` date, `name` string(120), `is_recurring` boolean default false (same
day-of-year every year). Unique `(site_id, date)`.

### checklist_templates
| column | type |
|---|---|
| id | |
| machine_id | foreignId → machines restrictOnDelete |
| name | string(190) |
| work_category | enum(`daily`,`weekly`,`general`) |
| work_description | text |
| frequency | enum(`daily`,`weekly`,`monthly`,`on_demand`) |
| per_shift | boolean default false |
| weekly_weekday | unsignedTinyInteger default 1 (ISO 1=Mon … 7=Sun) |
| monthly_day | unsignedTinyInteger default 1 (1–28) |
| requires_supervisor_signoff | boolean default true |
| grace_period_hours | unsignedSmallInteger default 24 |
| version | unsignedInteger default 1 |
| is_active | boolean default true |
| softDeletes | |

Index `(machine_id, is_active)`.

### checklist_template_items
`id`, `checklist_template_id` → cascadeOnDelete, `sort_order` unsignedSmallInteger,
`description` string(500), `response_type` enum(`check`,`pass_fail`,`numeric`,`text`)
default `check`, `is_required` boolean default true, `guidance` text nullable,
`requires_photo_on_fail` boolean default false, `is_active` boolean default true,
softDeletes. Index `(checklist_template_id, sort_order)`.

### checklist_template_parts
`id`, `checklist_template_id` → cascadeOnDelete, `part_id` → parts restrictOnDelete,
`sort_order` unsignedSmallInteger default 0. Unique `(checklist_template_id, part_id)`.

### checklist_runs
| column | type |
|---|---|
| id | |
| checklist_template_id | foreignId → restrictOnDelete |
| machine_id | foreignId → restrictOnDelete |
| template_version | unsignedInteger default 1 (snapshot) |
| scheduled_for | date, indexed |
| shift | enum(`day`,`night`,`all`) NOT NULL default `all` |
| status | enum(`pending`,`in_progress`,`submitted`,`approved`,`rejected`,`missed`) default `pending`, indexed |
| started_at | timestamp nullable |
| submitted_at | timestamp nullable |
| operator_id | foreignId nullable → users nullOnDelete |
| operator_signature_path | string(255) nullable |
| operator_signed_at | timestamp nullable |
| supervisor_id | foreignId nullable → users nullOnDelete |
| supervisor_signature_path | string(255) nullable |
| supervisor_signed_at | timestamp nullable |
| supervisor_comment | text nullable |
| notes | text nullable |
| downtime_minutes | unsignedInteger nullable |

**Unique `(checklist_template_id, scheduled_for, shift)`** — named
`runs_template_date_shift_unique`. This is what makes `checklists:generate`
idempotent. Extra index `(machine_id, scheduled_for)`.

### checklist_run_items
`id`, `checklist_run_id` → cascadeOnDelete, `checklist_template_item_id` foreignId
nullable → nullOnDelete, `sort_order` unsignedSmallInteger, `description`
string(500) **(snapshot — never read the template for display)**, `response_type`
enum(`check`,`pass_fail`,`numeric`,`text`) default `check`, `is_required` boolean
default true, `status` enum(`pending`,`done`,`not_applicable`,`failed`) default
`pending`, `value_numeric` decimal(12,3) nullable, `value_text` text nullable,
`fail_reason` string(500) nullable, `completed_at` timestamp nullable,
`completed_by` foreignId nullable → users nullOnDelete.
Index `(checklist_run_id, sort_order)`.

### checklist_run_parts
`id`, `checklist_run_id` → cascadeOnDelete, `part_id` foreignId nullable →
nullOnDelete, `part_code_snapshot` string(32), `part_name_snapshot` string(190),
`sort_order` unsignedSmallInteger default 0, `qty_used` decimal(8,2) default 0.

### attachments
`id`, `attachable_type` + `attachable_id` (`morphs`), `disk` string(32) default
`public`, `path` string(255), `original_name` string(255) nullable, `mime`
string(120) nullable, `size` unsignedBigInteger nullable, `uploaded_by` foreignId
nullable → users nullOnDelete.

### issues
`id`, `checklist_run_id` nullable → nullOnDelete, `checklist_run_item_id` nullable →
nullOnDelete, `machine_id` → restrictOnDelete, `raised_by` foreignId nullable →
users nullOnDelete, `severity` enum(`low`,`medium`,`high`,`breakdown`) default
`medium`, `description` text, `status` enum(`open`,`acknowledged`,`in_progress`,
`resolved`,`closed`) default `open`, indexed, `assigned_to` foreignId nullable →
users nullOnDelete, `resolved_at` timestamp nullable, `resolution_notes` text
nullable.

### user_machine (pivot — which machines an operator is assigned)
`id`, `user_id` → cascadeOnDelete, `machine_id` → cascadeOnDelete. Unique pair.

### kiosk_devices
`id`, `name` string(120), `token` string(64) unique, `location_id` foreignId
nullable → nullOnDelete, `last_seen_at` timestamp nullable, `is_active` boolean
default true.

### settings
`id`, `key` string(120) unique, `value` text nullable, `type` string(20) default
`string`.

---

## 3. PHP backed enums — `app/Enums/`

All are `: string`. Each gets a `label(): string` method returning a `__()` string.

| file | cases |
|---|---|
| `WorkCategory.php` | Daily=`daily`, Weekly=`weekly`, General=`general` |
| `Frequency.php` | Daily=`daily`, Weekly=`weekly`, Monthly=`monthly`, OnDemand=`on_demand` |
| `ResponseType.php` | Check=`check`, PassFail=`pass_fail`, Numeric=`numeric`, Text=`text` |
| `RunStatus.php` | Pending=`pending`, InProgress=`in_progress`, Submitted=`submitted`, Approved=`approved`, Rejected=`rejected`, Missed=`missed` |
| `RunItemStatus.php` | Pending=`pending`, Done=`done`, NotApplicable=`not_applicable`, Failed=`failed` |
| `Shift.php` | Day=`day`, Night=`night`, All=`all` |
| `IssueSeverity.php` | Low=`low`, Medium=`medium`, High=`high`, Breakdown=`breakdown` |
| `IssueStatus.php` | Open=`open`, Acknowledged=`acknowledged`, InProgress=`in_progress`, Resolved=`resolved`, Closed=`closed` |

`RunStatus` also gets `isEditable(): bool` (true for `pending`, `in_progress`,
`rejected`) and `color(): string` returning a Tailwind token used by the status dot.

---

## 4. Models — `app/Models/`

`User`, `Site`, `Location`, `Machine`, `Part`, `Holiday`, `ChecklistTemplate`,
`ChecklistTemplateItem`, `ChecklistTemplatePart`, `ChecklistRun`,
`ChecklistRunItem`, `ChecklistRunPart`, `Attachment`, `Issue`, `KioskDevice`,
`Setting`.

Rules:
- Every model casts its enum columns to the backed enums in §3 via `casts()`.
- Master-data models use `SoftDeletes`.
- `User` uses `HasRoles` (spatie), `Notifiable`, `SoftDeletes`; `$hidden = ['password','pin','remember_token']`;
  `casts()` maps `password` and `pin` to `'hashed'`, `is_active` to `bool`.
- Models that must be audited use `Spatie\Activitylog\Traits\LogsActivity` with
  `getActivitylogOptions()`: `ChecklistRun`, `ChecklistRunItem`, `ChecklistTemplate`,
  `ChecklistTemplateItem`, `Issue`, `Machine`, `User`.
- `$fillable` on every model — never `$guarded = []`.
- Relationship method names are the obvious ones (`machine()`, `location()`, `site()`,
  `items()`, `parts()`, `runs()`, `template()`, `operator()`, `supervisor()`,
  `issues()`, `attachments()`).
- `ChecklistRun` scopes: `scopeDueOn($q, $date)`, `scopeForMachine`, `scopeOpen`
  (status in pending/in_progress/rejected), `scopeAwaitingApproval` (submitted).
- `ChecklistRun` accessors: `getProgressAttribute(): array{done:int,total:int}`,
  `getIsCompleteAttribute(): bool` (no required item still `pending`).
- `Machine::getRouteKeyName()` returns `'code'` so `/m/{machine}` binds on the slug.

---

## 5. Roles and permissions

Roles (guard `web`): `operator`, `supervisor`, `maintenance_manager`, `admin`.

Permissions:
```
run.view  run.start  run.complete  run.submit  run.approve  run.reject  run.amend
issue.view  issue.create  issue.assign  issue.resolve
machine.view  machine.manage
template.view  template.manage
part.manage  schedule.manage  holiday.manage
report.view  export.data
user.manage  role.manage  setting.manage  kiosk.manage
```

Grants (cumulative — each role gets everything the one above it has):
- operator: `run.view run.start run.complete run.submit issue.view issue.create machine.view template.view`
- supervisor: + `run.approve run.reject issue.assign issue.resolve report.view`
- maintenance_manager: + `machine.manage template.manage part.manage schedule.manage holiday.manage export.data run.amend`
- admin: all permissions.

Policies in `app/Policies/`: `ChecklistRunPolicy`, `ChecklistTemplatePolicy`,
`MachinePolicy`, `IssuePolicy`, `UserPolicy`. Registered in
`app/Providers/AppServiceProvider::boot()` via `Gate::policy()`.
**Authorisation is enforced in policies and in the Livewire component's `mount()`
— Blade `@can` is presentation only and is never the only check.**

Operator scoping: an operator may only see machines in `user_machine`, OR — if they
have no rows in `user_machine` — machines at their `default_site_id`. Implement once
in `app/Support/MachineScope.php` as `MachineScope::for(User $user): Builder`.

---

## 6. Routes — `routes/web.php` is owned by ONE agent

Named routes other code may link to:

| name | URI | notes |
|---|---|---|
| `login` | GET/POST `/login` | email **or** employee_number |
| `logout` | POST `/logout` | |
| `dashboard` | GET `/dashboard` | milestone 7 — stub view for now |
| `kiosk.home` | GET `/kiosk` | machine picker grid |
| `kiosk.machine` | GET `/m/{machine}` | QR deep link, binds `machines.code` |
| `kiosk.pin` | POST `/kiosk/pin` | PIN auth for a run |
| `kiosk.release` | POST `/kiosk/release` | drop session back to kiosk |
| `runs.index` | GET `/runs` | |
| `runs.show` | GET `/runs/{run}` | the completion form |
| `admin.machines` | GET `/admin/machines` | |
| `admin.locations` | GET `/admin/locations` | |
| `admin.parts` | GET `/admin/parts` | |
| `admin.templates` | GET `/admin/templates` | |
| `admin.templates.edit` | GET `/admin/templates/{template}/edit` | items + parts builder |
| `admin.holidays` | GET `/admin/holidays` | |

Middleware groups: `auth` for everything except `login` and the kiosk routes;
kiosk routes use a `kiosk` middleware alias (`app/Http/Middleware/EnsureKioskDevice.php`)
that accepts a signed device cookie. `kiosk.idle` (2-minute inactivity → release)
is enforced client-side in Alpine **and** server-side by comparing
`session('kiosk.authenticated_at')`.

---

## 7. Console commands

- `checklists:generate {--date=} {--dry-run}` → `App\Console\Commands\GenerateChecklistRuns`
- `checklists:mark-missed {--now=}` → `App\Console\Commands\MarkMissedChecklistRuns`

Both **must be idempotent**. Registered in `routes/console.php`:
`Schedule::command('checklists:generate')->dailyAt('05:00');`
`Schedule::command('checklists:mark-missed')->hourly();`

Generation rules:
1. Skip templates where `is_active = false` or the machine `is_active = false`.
2. Skip dates that are not in `sites.working_days` for the machine's site, or that
   match a `holidays` row for that site (or a site-wide `site_id = null` row).
   Recurring holidays match on month+day regardless of year.
3. `frequency = daily` → generate on every working day.
   `weekly` → only when ISO weekday == `weekly_weekday`.
   `monthly` → only when day-of-month == `monthly_day`.
   `on_demand` → never auto-generated.
4. `per_shift = true` → create a `day` run and a `night` run. Else one `'all'` run.
5. Use `firstOrCreate` on `(checklist_template_id, scheduled_for, shift)`.
6. On create, snapshot `template_version`, and copy every **active** template item
   into `checklist_run_items` (description, response_type, is_required, sort_order)
   and every template part into `checklist_run_parts` (code + name snapshot, qty 0).
7. Report counts: created, skipped-existing, skipped-non-working-day.

Missed rules: a run is `missed` when `status = pending`, `started_at` is null, and
`now() > scheduled_for end-of-day + template.grace_period_hours`. Never delete.
Log the transition to the activity log with a `system` causer.

---

## 8. UI conventions

- Layout `resources/views/layouts/app.blade.php` — authenticated chrome, sidebar nav.
- Layout `resources/views/layouts/kiosk.blade.php` — **no chrome**, full-bleed, dark
  high-contrast, no browser nav affordances, 10" tablet first.
- Layout `resources/views/layouts/guest.blade.php` — login.
- Tablet targets: every interactive control is **at least 56px tall** (`min-h-14`).
  Checklist rows are **80px** (`min-h-20`). Body text `text-lg` minimum on kiosk.
- Tailwind palette: neutral slate base; status colours
  `pending` slate-400 · `in_progress` amber-500 · `submitted` sky-500 ·
  `approved` emerald-600 · `rejected` rose-600 · `missed` red-700.
  Never encode status by colour alone — always pair with a text label or icon.
- All user-facing strings go through `__()`. Keys live in `lang/en/app.php`.
- Livewire components are in `app/Livewire/**`, views in
  `resources/views/livewire/**` matching the component's kebab path.
- Autosave: the run form persists each response on change with
  `wire:model.live` + a dedicated `toggle()`/`setValue()` action that writes
  immediately. There is no "save" button for item state.
- Never trust a client timestamp. `completed_at`, `operator_signed_at`,
  `submitted_at` are always `now()` server-side.

---

## 9. Definition of done for this pass (milestones 1–4)

1. Scaffold, auth (email-or-employee-number + kiosk PIN), roles/permissions,
   all migrations, master-data seeders, `README.md`.
2. Machine + location + part + template admin CRUD (Livewire), including the
   template item reorder/add/deactivate builder and the parts attach UI.
3. `checklists:generate` + `checklists:mark-missed`, run listing, kiosk machine
   picker with status dots.
4. The run completion form — items, N/A + fail with reason, parts steppers, notes,
   progress indicator, autosave, required-item enforcement on submit.

Not in this pass: signature canvas, supervisor approval queue, issues UI,
dashboards, reports, CSV/PDF export, PWA/service worker, QR sticker sheet.
The schema and models for those DO exist.
