# Claude Code Prompt — Branding Machine Maintenance Checklist System

> Paste everything below the line into Claude Code at the root of an empty directory.
> Also drop the 13 source PDFs into `docs/source-checklists/` so Claude Code can refer to them.

---

## Role & Objective

You are building a production-ready **Preventive Maintenance (PM) Checklist System** for the Branding & Signage department of a label and large-format printing manufacturer. The department currently records daily and weekly machine maintenance on printed paper work orders — one sheet per machine, signed by an operator and a supervisor, then filed. Paper means no searchable history, no proof a check was actually done on time, no visibility into parts consumption, and no way to spot a machine that has been skipped for a week.

Replace the paper entirely. The digital version must be faster to complete on a shop floor than ticking a printed sheet, or operators will not use it.

Reference PDFs of the current paper forms are in `docs/source-checklists/`. Read them before writing code — the digital forms must map 1:1 onto them so the changeover needs no retraining.

## Tech Stack (non-negotiable)

- **PHP 8.3+**, **Laravel 11+**
- **MySQL 8** (InnoDB, utf8mb4)
- **Blade + Livewire 3** for the UI (no separate SPA build step — this must stay easy to deploy and maintain on a shared/self-hosted server)
- **Tailwind CSS** via Vite, **Alpine.js** for small interactions
- **Laravel Breeze** (Blade stack) for auth scaffolding
- Queue driver `database`, cache `file` by default — no Redis dependency required to run
- Must run **both** ways with no code changes, only `.env` differences:
  1. Locally: `php artisan serve` / Laragon / Herd / XAMPP against a local MySQL
  2. On a web server: Apache or Nginx + PHP-FPM, document root at `/public`
- Ship an **optional** `docker-compose.yml` (php-fpm, nginx, mysql, scheduler worker) but do not make Docker a requirement
- No paid packages, no external SaaS calls, no internet dependency at runtime — the plant may be offline

## Domain Model

Build this schema with migrations, models, factories and seeders. Use foreign keys, soft deletes on master data, and `restrict` on delete where history would be orphaned.

### Master data

**`sites`** — id, name, code
Seed: `Branding 23 — {Scarlet Ibis}`

**`locations`** — id, site_id, name, floor
Seed: Digital Print (Ground Floor), Digital Finishing (Ground Floor), Production Floor (Ground Floor), ESKO Router Room (Ground Floor)

**`machines`** (equipment) — id, location_id, code (unique, slug), name, manufacturer (nullable), model (nullable), asset_tag (nullable), is_active, notes
Seed the 11 machines listed in the seed data section.

**`parts`** — id, part_code (the existing external part ID, string — one is literally `XXX`), name, unit (nullable), is_active
Seed the parts catalogue below. `part_code` is a string, not an integer — do not assume numeric.

**`machine_part`** — pivot; which parts are normally consumed on which machine, so the form pre-lists them in the right order (`sort_order`).

**`checklist_templates`** — id, machine_id, name, work_category (enum: `daily`, `weekly`, `general`), work_description (text), frequency (enum: `daily`, `weekly`, `monthly`, `on_demand`), requires_supervisor_signoff (bool, default true), grace_period_hours (int, default 24), is_active
One template per source PDF — 13 in total.

**`checklist_template_items`** — id, checklist_template_id, sort_order, description, response_type (enum: `check`, `pass_fail`, `numeric`, `text`), is_required (bool, default true), guidance (nullable text), requires_photo_on_fail (bool)
Default every seeded item to `check` and required, matching the paper. The response types exist so supervisors can later upgrade an item (e.g. "Check water level in chiller" → numeric) without a code change.

**`checklist_template_parts`** — id, checklist_template_id, part_id, sort_order — the "Used Parts" table printed on each sheet.

### Transactional data

**`checklist_runs`** — id, checklist_template_id, machine_id, scheduled_for (date), shift (nullable enum), status (enum: `pending`, `in_progress`, `submitted`, `approved`, `rejected`, `missed`), started_at, submitted_at, operator_id, operator_signature_path, operator_signed_at, supervisor_id, supervisor_signature_path, supervisor_signed_at, supervisor_comment, notes (text — the "Notes:" box on the sheet), downtime_minutes (nullable), created_at/updated_at
Unique index on `(checklist_template_id, scheduled_for)` so the scheduler can never double-create.

**`checklist_run_items`** — id, checklist_run_id, checklist_template_item_id, sort_order, description (denormalised snapshot of the item text at run time), status (enum: `pending`, `done`, `not_applicable`, `failed`), value_numeric (nullable), value_text (nullable), completed_at, completed_by
Snapshotting the description is deliberate: a run completed in March must still show the wording that was on the form in March, even after the template is edited.

**`checklist_run_parts`** — id, checklist_run_id, part_id, part_name_snapshot, qty_used (decimal 8,2, default 0)

**`attachments`** — polymorphic; run or run_item, disk path, mime, size, uploaded_by. Store on the `public` disk by default, configurable.

**`issues`** — id, checklist_run_id (nullable), checklist_run_item_id (nullable), machine_id, raised_by, severity (enum: `low`, `medium`, `high`, `breakdown`), description, status (enum: `open`, `acknowledged`, `in_progress`, `resolved`, `closed`), assigned_to, resolved_at, resolution_notes
When an operator marks an item `failed`, prompt to raise an issue and pre-fill it.

**`activity_log`** — full audit trail (use `spatie/laravel-activitylog`). Every status change, signature, template edit and item response must be attributable and immutable.

### Users & roles

Use `spatie/laravel-permission`. Roles:

- **Operator** — sees only their assigned machines/locations; can start, complete and submit runs; can raise issues; cannot edit templates or approve.
- **Supervisor** — everything an operator can do, plus approve/reject submitted runs, sign off, view their department's dashboard and reports.
- **Maintenance Manager** — all of the above, plus manage machines, templates, parts, schedules; sees plant-wide dashboards and exports.
- **Admin** — user management, roles, system settings.

Users have `employee_number`, `full_name`, `email` (nullable — floor operators may not have company email), `pin` (hashed 4–6 digit), `is_active`. Support **PIN login on a shared shop-floor tablet**: a kiosk route where the tablet is authenticated as a device, an operator taps their name/scans a badge and enters a PIN to sign a run, then the session drops back to the kiosk after submission or 2 minutes of inactivity. Full email/password login remains for supervisors and office users.

## Core Workflows

### 1. Scheduling
A console command `checklists:generate` (registered in the scheduler to run daily at 05:00, and idempotent so it can be re-run safely) creates `checklist_runs` in `pending` status for every active template due that day — daily templates every working day, weekly templates on a configurable weekday per template. A second command `checklists:mark-missed` runs after the grace period and flips untouched `pending` runs to `missed`. Missed runs are never silently deleted; they are the compliance record.

Provide a working-days/holiday calendar table so Sundays and public holidays can be excluded per site.

### 2. Completing a run (the screen that matters most)
Tablet-first, one-thumb operation, large tap targets, high contrast, readable under plant lighting. Assume a 10" Android tablet in a grubby environment and possibly gloves.

- Operator opens the machine (via a **QR code sticker on the machine** → `/m/{machine_code}` deep link, or from a list)
- Sees today's due checklist(s) for that machine, with the header block from the paper form: name, work category, equipment, location, building, work description
- Items render as a single scrolling list of big checkbox rows, numbered exactly as on the paper form. Tap = done. Long-press or a secondary control = mark N/A or Failed with a reason.
- Progress indicator ("7 of 9"), autosave every response immediately via Livewire so nothing is lost if the tablet sleeps
- "Used Parts" section pre-populated from the template with a stepper for `qty_used`, defaulting to 0
- Free-text **Notes** box
- Optional photo capture per item and per run
- **Operator signature**: finger/stylus signature on canvas, stored as PNG, plus the PIN confirmation and a server-side timestamp. Do not let the client supply the timestamp.
- Submit → status `submitted`. Block submission if a required item is still pending, and say exactly which ones.

### 3. Supervisor sign-off
Queue of submitted runs. Supervisor reviews, sees any failed items and photos, then either signs (status `approved`, signature + timestamp) or rejects with a comment (status `rejected`, bounced back to the operator with the reason). Approved runs are immutable — corrections happen by an admin-only amendment that is logged, never by silent edit.

### 4. Issues
Failed items feed an issue register with severity, assignment and resolution notes. A machine with an open `breakdown` issue is flagged on every dashboard.

### 5. Template management
Maintenance Manager can create/edit templates, reorder items, add items, deactivate items, and attach the parts list — all without touching code. Editing a template **versions** it (`version` integer on the template, incremented on item changes) so historical runs stay tied to what was actually asked.

## Screens to Build

1. **Kiosk / machine picker** — grid of machine tiles with a status dot (due, in progress, done, overdue), filtered by location
2. **Run form** — as described above
3. **Supervisor approval queue**
4. **Dashboard** — compliance % by machine and by week, runs due today, overdue/missed count, open issues by severity, parts consumed this month, a heat-map calendar of completion by machine
5. **Machine profile** — details, QR code (printable sticker sheet), template list, run history, issue history, parts consumption
6. **Issue register** — filterable list plus detail view
7. **Reports** — date-range compliance report, missed-checks report, parts usage report, per-operator activity; each exportable to CSV and PDF
8. **Admin** — users, roles, machines, locations, parts, templates, holidays, settings

## PDF Export

Using `barryvdh/laravel-dompdf`, produce a per-run PDF that visually mirrors the original paper work order — same header layout, two-column task list, Used Parts table, Notes box, and both signature blocks with printed name, employee number and timestamp beneath each signature image. Auditors and ISO reviewers will expect the familiar sheet. Include the run ID and a verification hash in the footer.

## Non-Functional Requirements

- **Offline tolerance**: make it a PWA with a service worker. A run already open must remain completable if Wi-Fi drops mid-shift, queueing responses in IndexedDB and syncing on reconnect, with a clear "unsynced" badge. Do not attempt full offline-first sync of master data — scope it to the open run.
- **Timezone**: store UTC, display in `America/Port_of_Spain`, configurable via `.env`
- **Audit**: nothing that constitutes a record is hard-deleted; every state transition is logged with actor, IP and timestamp
- **Performance**: run form must be interactive within 2 seconds on a mid-range tablet over plant Wi-Fi
- **Localisation-ready**: all UI strings through `__()`, English only for now
- **Security**: CSRF, rate-limited PIN attempts with lockout, signed URLs for attachments, role checks enforced in Policies (not just in the Blade views), validation on every input
- **Tests**: Pest feature tests covering run generation, completion, required-item enforcement, sign-off, rejection, role restrictions and the missed-run job. Aim for meaningful coverage of the workflow, not 100% of getters.

## Seed Data

Seed all of this exactly. Task wording is taken from the paper forms — keep it verbatim, typos included, and flag anything ambiguous in `docs/seed-notes.md` rather than silently "fixing" it.

### Parts catalogue

| part_code | name |
|---|---|
| 7 | Long-term Grease for Bearings |
| 21 | Tooth Brush {Soft} |
| 22 | General Cleaning Towels {Rags} |
| 23 | Isopropyl alcohol |
| 24 | Nitrile Gloves |
| 25 | Miller 112 - Air Filter |
| 26 | Miller 112 - Weld Roller Solenoid |
| 27 | Miller 112 - Micro Switch |
| 28 | Miller 112 - Standard Solenoid |
| XXX | Simple Green |

### Machines

| Machine | Location |
|---|---|
| MATAN | Digital Print |
| HP Stitch 1000 | Digital Print |
| HP R2000 [FB] | Digital Print |
| HP 570 Latex | Digital Print |
| HP 800W | Digital Print |
| ESKO C64 Kongsberg | ESKO Router Room |
| Hiker Grommet Machine | Production Floor |
| Mistral 1650-65 Laminator | Digital Finishing |
| Miller 112 Cross Seamer | Production Floor |
| Monti Antonio | Digital Finishing |
| Rolls Roller | Digital Finishing |

### Templates

**1. MATAN — Daily Maintenance** (daily) · description: "Daily Maintenance of the MATAN"
1. Cleaning the Vacuum Table
2. Cleaning the Measure Media Sensor
3. Cleaning the Ink Sink
4. Emptying the Ink Collector
5. Replacing the UV Filters
6. Empty Bins
7. Dust Frame
8. Sweep Around Machine
9. Remove any End Rolls and Neaten up WIP roll storage

Parts: 22, 23, 24, 21, 7, XXX

**2. MATAN — Weekly Maintenance** (weekly) · description: "Weekly Maintenance of the MATAN"
1. Cleaning the Vacuum Table
2. Cleaning the Measure Media Sensor
3. Cleaning the Ink Sink
4. Emptying the Ink Collector
5. Replacing the UV Filters
6. Draining the Filter Separators
7. Draining the Oil Filter Separator
8. Draining the Water Filter Separator
9. Cleaning the Free-fall Rollers
10. Cleaning the Print Table Fan(s) on the Carriage
11. Clean the Ionizer Bars
12. Clean Mist Fans
13. Lubricating the Carriage Bearings
14. Clean Around Machine
15. Empty Bins
16. Mop Floor

Parts: 22, 23, 24, 21, 7, XXX

**3. HP Stitch 1000 — Daily Maintenance** (daily) · description: "Daily Maintenance of the HP Stitch 1000"
1. Clean - Dust - Ink - Spots on machine
2. Clean Pinch Rollers
3. Sweep around Machine
4. Clean Platen
5. Check & Clean Print Head Fringe
6. Follow Checks Outlined

Parts: 22, 23, 24

**4. HP R2000 [FB] — Daily Maintenance** (daily) · description: "Daily Maintenance of the HP R2000 [FB]"
1. Clean - Dust - Ink - Spots on machine
2. Clean substrate Belt
3. Clean Print Heads

Parts: 22, 23, 21

**5. HP 570 Latex — Daily Maintenance** (daily) · description: "Daily Maintenance of the HP 570 Latex"
1. Clean - Dust - Ink - Spots on machine
2. Clean Pinch Rollers
3. Clean media Clamps
4. Clean Platen
5. Sweep around Machine
6. Clean Encoder

Parts: 22, 23, 24

**6. HP 800W — Daily Maintenance** (daily) · description: "Daily Maintenance of the HP 800 W"
1. Clean - Dust - Ink - Spots on machine
2. Clean Pinch Rollers
3. Clean media Clamps
4. Clean Platen
5. Sweep around Machine
6. Clean Encoder

Parts: 22, 23, 24

**7. ESKO C64 Kongsberg — General Maintenance** (general) · description: "Consult User manual where Required"
1. Clean Dust Machine using Vacuum
2. Clean around Machine
3. Check Water Traps on Air Line
4. Clean Guide-way & rails
5. Apply Light Grease and Oil to Guide-way & rails
6. Clean Carriage assembly of any dust buildup
7. Check Vacuum bin and Empty as needed
8. Empty Waste Bins
9. Tidy up work station and around machine
10. Check water level in chiller
11. Clean tools and tool box
12. Dust and wipe computer table

Parts: 24, 22, 23

**8. Hiker Grommet Machine — General Maintenance** (general) · description: "Follow Outlined Checks"
1. Check the UP and DOWN movement - To be smooth
2. Dust Frame
3. Clean around Machine

Parts: 22, 23, 24

**9. Mistral 1650-65 Laminator — General Maintenance** (general) · description: "Follow Outlined Checks"
1. Wipe Rollers with IPA/Water solution
2. Dust and clean Frame
3. Empty Bins
4. Clean around Machine

Parts: 22, 23, 24

**10. Miller 112 Cross Seamer — General Maintenance** (general) · description: "Follow Outlined Checks"
1. Clean around Machine and Empty Bins
2. Check Air Line / system for any leaks
3. Clean Overhead Track and apply light Grease
4. Clean Air Filters as per Guidelines in Manual
5. Check Belts Once Per Month
6. Check Weld Roller for any Damage
7. Check Micro Switch for Proper Operation
8. Check Laser Lights are Operating and aligned
9. Clean Machine Frame

Parts: 26, 27, 25, 24, 23, 22, 28

**11. Monti Antonio — Daily Maintenance** (daily) · description: "Follow Checks Outlined"
1. Dust and clean around machine

Parts: none listed

**12. Monti Antonio — Weekly Maintenance** (weekly) · description: "Follow Checks Outlined"
1. Wipe rollers and frame with damp cloth
2. Mop floor around machine

Parts: none listed

**13. RollsRoller — Mounting Table, General Maintenance** (general) · description: "Follow Outlined Checks"
1. Dust Frame
2. Clean around Machine
3. Bleed and Check Compressor under Table
4. Clean and Wipe Table surface with IPA/Water solution
5. Check Bearing Track and apply Grease as needed

Parts: none listed

Also seed demo users — one per role — plus 30 days of realistic historical runs so the dashboards and reports are not empty on first boot. Put demo data in a separate `DemoSeeder` that is **not** part of `DatabaseSeeder`, so production installs stay clean.

## Build Order

Work in these milestones, and stop after each for review rather than building everything before showing anything:

1. Project scaffold, auth, roles, migrations, master-data seeders, `README`
2. Machine + template admin CRUD
3. Run generation command + run listing/kiosk
4. The run completion form (this is the make-or-break screen — polish it)
5. Signatures, submission, supervisor approval/rejection
6. Issues register
7. Dashboards + reports + CSV/PDF export
8. PWA/offline handling, QR stickers, tests, deployment docs

## Deliverables

- Working Laravel application
- `README.md` covering: local setup (both native PHP and Docker), `.env` reference, migration + seeding, scheduler cron line (`* * * * * php /path/artisan schedule:run`), queue worker setup, and a production deployment checklist for Apache and Nginx
- `docs/data-model.md` with an ER diagram (Mermaid)
- `docs/user-guide.md` — one page each for operator, supervisor and manager, written for someone who has never used a computer-based form
- `docs/seed-notes.md` — anything ambiguous in the source PDFs, and the assumption you made

## Rules of Engagement

- Ask me before assuming anything material: shift patterns, whether Sundays are working days, whether operators have company email addresses, whether this must eventually feed an existing CMMS or ERP.
- Do not invent checklist items that are not on the source forms.
- Prefer boring, maintainable Laravel over clever abstractions — this will be maintained by a small internal IT team.
- Commit in logical chunks with clear messages.
