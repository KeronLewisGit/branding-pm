# Changelog

Versions track build milestones: `0.<milestone>.<patch>`. The project reaches
`1.0.0` when all 8 milestones are complete and the paper forms are retired.

## [0.11.0] — The QR deep link, and a way to set a tablet up

### Fixed: every machine on the kiosk opened the "unknown machine" screen

Tapping any machine tile, or scanning any QR sticker, produced *“This machine
was not found. The code "{"id":6,"location_id":4,…}" does not match any
machine.”* — the machine's own JSON quoted back as the thing that could not be
found. An unknown code was worse: a bare 404, which is precisely the failure
`MachineRuns` was written to avoid.

The cause is a name collision. Livewire's `ImplicitRouteBinding` intersects
route parameters with the component's **public property names**, so the
`{machine}` route parameter bound to `MachineRuns::$machine` (typed
`?Machine`), resolved the model before `mount()` ran, and handed it to a
`mount(string $machine)` — where PHP coerced it through
`Model::__toString()`, which is `toJson()`. When the code did not resolve, the
same binding threw `ModelNotFoundException` first, hence the 404.

The route parameter is now `{code}`, matching the public `string $code`, which
Livewire passes through untouched. Both the route and the component carry a
comment saying why the name is load-bearing, because reverting it looks
harmless. Four call sites updated; the picker's URLs were correct all along.

Worth noting the code was authored against **Livewire 3** and the project now
requires **Livewire 4** (`^4.3`) on **Laravel 13** and **PHP 8.4** — two majors
past the stack SPEC §Tech Stack names, and past what the README and
BUILD-CONTRACT still describe. This is the first place that gap has drawn
blood.

### Admin → Kiosk Tablets

There was no way to set a tablet up. `kiosk_devices` had no seeder, no factory
and no screen, so a clean install had zero devices and `/kiosk` was a flat 403
with nothing in the UI to explain it — the tablet had to be created by hand in
tinker and then enrolled by visiting `/kiosk/enrol/{id}` on the device itself.

`/admin/kiosk` (permission `kiosk.manage`) lists tablets worst-first —
never-enrolled at the top — with in-use / enrolled / deactivated / not-enrolled
status, last-seen, and create, rename, activate and delete.

**Enrolment is by temporary signed link, shown as a QR code**, valid 15
minutes and requiring no login. The old authenticated route still works, but
the alternative it replaces is typing an admin password into a shared
shop-floor device in front of whoever is standing there. What the link is worth
if it leaks — the machine grid and the PIN pad, nothing that records anything —
is documented on the controller, in the modal, and in the deployment guide.

**Un-enrol** rotates the device token, which drops every browser enrolled as
that device on its next request; **Deactivate** is the reversible version. Both
are immediate: the middleware resolves the token per request through an
`is_active` scope. This is the lost-tablet path, and it did not exist before.

### Tests
13 new feature tests (45 total, 160 assertions). The deep link with a valid and
an invalid code — including an assertion that the page contains no JSON, which
is the regression above — plus enrolment by signed link with nobody logged in,
expiry, tampering, deactivated devices, token rotation locking a tablet out,
the QR actually rendering, and permission on the admin screen.

The kiosk cookie helper carries a comment about `withCookies()` encrypting what
it is given: passing an already-encrypted value encrypts it twice and every
request 403s, which reads as a middleware bug rather than a test bug. It cost
three rounds to find.

### Also
- `APP_URL` moved to the LAN address, with a note that it is a DHCP lease that
  has already moved once. Tablets remember the address they were enrolled on
  and PWA `start_url` is absolute, so a kiosk deployment needs a reservation or
  a DNS name — now called out in `DEPLOYMENT.md` §7 and the manager guide.
- `DEPLOYMENT.md` §7 rewritten around the new flow, including the
  deactivate/un-enrol/delete distinction and the HTTPS requirement for the PWA.

## [0.10.1] — First execution

Ran the application for the first time, under the shipped `docker-compose.yml`.
Recorded here because the README had warned since milestone 1 to "expect to fix
something" on first boot. Nothing needed fixing.

- 22 migrations apply; seeders load (5 users, 11 machines, 13 templates, 82
  items, 347 demo runs, 9 issues).
- **All 34 Pest tests pass**, 135 assertions, 62s.
- `checklists:generate` created 12 runs for today, then 0 on a second pass —
  `runs_template_date_shift_unique` doing its job.
- `/login`, `/dashboard`, `/kiosk`, `/m/{code}`, `/offline.html`,
  `/manifest.webmanifest` and `/sw.js` all serve; unauthenticated app routes
  302 to login; `/kiosk` returns the not-enrolled page until the device cookie
  is set, then the machine grid.

Two things needed doing that a fresh install will also need, and neither is a
code defect:

- **No kiosk device is seeded**, so `/kiosk` is a 403 on a clean database until
  someone creates a `kiosk_devices` row. Nothing in the docs said so; the
  deployment guide now has to.
- **`DemoSeeder` history stops at the day it was seeded**, so on any later day
  the kiosk is empty until `checklists:generate` runs.

`APP_URL` set to the LAN IP rather than `localhost` so a tablet on the same
network gets working absolute URLs.

## [0.10.0] — Remaining documentation deliverables

No application code changed. This closes the two `docs/` deliverables the spec
asked for and corrects a README that had been describing a four-milestone build
since milestone 4.

### `docs/data-model.md`
Mermaid ER diagram of all 19 domain tables with columns and keys, plus the parts
that a diagram cannot carry: the delete-behaviour table (cascade for parts of a
thing, restrict for the thing itself, null for the person or definition that
produced it), the six snapshot columns and what each one defends against, the
two unique indexes that carry weight, and `stateDiagram` renderings of the run
and issue lifecycles taken from `RunStatus` and `IssueStatus::allowedNext()`.

Verified against the migrations rather than transcribed from BUILD-CONTRACT §2.

### `docs/user-guide.md`
Three guides, written for someone who has never used a computer-based form:
operator (kiosk, PIN, the run form, signing, and the four things that will go
wrong — offline queue, reload loss, rejection, idle logout), supervisor
(approval queue, reviewing, the two-person rule, the issue register, and why
`report.view` and `export.data` are separate), and maintenance manager (the
compliance formula and its two consequences, template versioning, scheduling and
the January holiday-calendar problem, QR stickers, exports, and the `APP_KEY`
dependency in the verification hash).

### README corrections
- Status table said milestones 5–8 were **Not started**; all four are built.
- Test section said the Pest suite was unwritten; there are 34 tests.
- Queue section said exports would queue. They do not — there is no `app/Jobs`,
  CSV is streamed and PDF renders inline. A worker is optional today.
- `data-model.md` and `user-guide.md` were listed as pending; both now link.

### Gaps now recorded in the README
Checking the routes against SPEC §Screens turned up three things built into the
authorisation layer but never given a screen:

- **Admin → users, roles, settings.** `user.manage` and `UserPolicy` exist;
  nothing calls them, so operators and PINs are created via seeder or tinker.
- **Machine profile** (spec screen 5) — reachable data, no per-machine page.
- **The admin amendment path.** `run.amend` and `ChecklistRunPolicy::amend()`
  are in place and unreferenced, so correcting an approved run — the escape
  hatch that makes immutability tolerable — has no route through the UI.

## [0.9.0] — Milestone 8: PWA, QR stickers, tests, deployment

### PWA and offline tolerance
- `public/sw.js` precaches the app shell, serves content-hashed assets
  cache-first and navigations network-first, falling back to a standalone
  `/offline.html`. It **never** caches or replays a write.
- Answers made while the tablet is offline are queued in IndexedDB
  (`resources/js/offline.js`) and replayed oldest-first on reconnect, followed
  by a refresh so the server stays authoritative. This is safe only because
  the run form's actions are absolute-state and idempotent by design.
- A badge in the kiosk header shows the queue depth — an operator must never
  walk away believing a sheet was saved when it was not.
- **Replay is same-session only.** A Livewire payload carries a state
  snapshot; after a reload it is stale, and replaying it would write old state
  over new. Queued answers stranded by a reload are therefore *not* replayed —
  a red banner names how many were lost so they can be re-entered. Losing an
  answer loudly beats saving a wrong one silently.
- Submissions are never queued or replayed: they take a signature and a PIN,
  and a blind replay could double-submit.
- Manifest, icons (generated, maskable variant included) and theme colour;
  `start_url` is `/kiosk`, so the tablet installs straight to the picker.

### QR sticker sheet
- `/admin/machines/qr` — printable three-up sheet, filter by location, choose
  machines, screen controls hidden from the print output, stickers kept whole
  across page breaks.
- Inline SVG QR codes (no network needed at print time), medium error
  correction to survive a scuffed sticker, and the machine code printed in
  plain text beneath every QR — when the label is over-sprayed, the code is
  what still works.

### Tests
34 Pest feature tests across milestones 5–8, covering everything SPEC §Tests
asks for except the signature canvas itself, which is JavaScript and still
needs a human with a tablet: run generation (per-shift, idempotent, working
days, holidays, inactive templates), the missed-run job either side of the
grace period, required-item enforcement naming the blocking items, signature
and PIN confirmation, approval, rejection, the two-person rule, issue
transitions and scoping, the compliance definition, export permissions and
role restrictions.

### Deployment
`docs/DEPLOYMENT.md` — install, the admin account and its published default
password, file permissions (with the 500-on-every-page failure they cause and
why the log goes silent with them), scheduler and queue, caches, kiosk
enrolment, QR sticker warnings, backups (database *and* `storage/app/public`
*and* `APP_KEY`, since it keys the run-sheet verification hashes), upgrades,
and a symptom-first troubleshooting table.

## [0.8.0] — Milestone 7: dashboard, reports and exports

### One definition of compliance
`App\Support\Reporting\Compliance` is the only place the word is defined, and
the dashboard, the reports screen, the CSV and the PDF all read it:
`completed / (completed + missed + outstanding)`, over runs scheduled inside
the window. Work not yet due is not counted at all, and a window with nothing
due has **no** percentage rather than 0% or 100% — both of which would lie.
Lateness within the grace period is still not distinguished (seed-notes §E6).

### Dashboard
Replaces the placeholder at `/dashboard`: compliance for a 7/30/90-day window,
due-today and overdue/missed tiles, sign-off backlog, compliance by week,
open issues by severity, a 14-day machine × day completion heat-map, worst-first
compliance by machine, and parts consumed this month. The heat-map and the
per-machine table are two grouped aggregates, not a query per cell.

An operator has no `report.view`, and `/` redirects here — so the dashboard
sends them to `/runs` rather than meeting a password login with a 403.

### Reports
`/reports` with four reports — compliance by machine, missed checks, parts
usage, operator activity — over any window, filtered by machine or location.
Each declares its own columns and rows through one `Report` interface, so the
screen, the CSV and the PDF cannot disagree.

- **Parts usage** reads the snapshot columns, not the catalogue: a part renamed
  last month still reports under the name it was consumed as.
- **Operator activity** is framed as a record, not a score — finding faults is
  the job, and a column that punished it would corrupt the record.
- **Missed checks** includes runs still open past their date, not just those
  already flagged `missed`.

### Exports
- CSV is streamed, BOM-prefixed so Excel on Windows reads UTF-8 correctly, and
  carries two provenance lines (report title, window) above the data.
- PDF via dompdf, landscape, one template for all four reports.
- Both rebuild the identical `ReportFilters` — **including the machine scope**,
  so an export can never widen what its requester may see.
- `report.view` shows numbers on screen; `export.data` is what takes them out
  of the building. A supervisor has the first and not the second.

### The run sheet PDF
`/runs/{run}/pdf` — a facsimile of the paper work order: same header block,
two-column numbered task list, Used Parts table, Notes box, and both signature
blocks with printed name, employee number and timestamp beneath each image.
Signatures are embedded as data URIs (dompdf fetches nothing). Gated on the
run's own view policy, not `export.data`.

The footer carries a verification hash (`App\Support\RunVerification`): an
HMAC over the record — items, parts, signatures, timestamps — keyed with the
app key. Somebody holding a printed sheet can ask whether it still matches the
record; alter an approved run and the printed hash stops matching. It is a
tamper check, not a signature, and says so in its own docblock.

### Tests
27 Pest feature tests now cover milestones 5–7: signature capture and PIN
confirmation, approval and rejection, the two-person rule, issue transitions
and scoping, the compliance definition, export permissions and the PDF policy.

## [0.7.0] — Milestone 6: issues register

The data side already existed and was being written to — `RunForm` has raised an
issue from every failed checklist item since milestone 4. What was missing is
that nobody could see them.

### Register
- `/issues` — filter by machine, location, severity, status and assignee, plus a
  free-text search over the description and machine name/code. Scoped by
  `MachineScope` like runs: an operator sees their own machines' faults.
- Defaults to open work only; resolved and closed stay reachable through the
  status filter. Nothing is ever deleted — an issue is part of the same
  maintenance record the checklists are.
- Ordering reads top-down as "what needs doing next": open before closed,
  breakdown before high before medium, then oldest first.
- Open counts by severity double as one-tap filters.
- **Raise an issue** outside a run, for a fault noticed between checks — the
  only path that did not exist before.

### Detail and workflow
- `/issues/{issue}` — the evidence first: the failed checklist item, the
  operator's reason, their photos and a link back to the run.
- Status moves along `IssueStatus::allowedNext()` only, re-read from the
  database on every action, so a button in a tab left open since the morning
  cannot drive a closed issue backwards. Acknowledging, starting and reopening
  need `issue.assign`; resolving and closing need `issue.resolve`.
- Resolving requires notes. Reopening keeps them (they are the history of the
  fault) but clears `resolved_at`, which is no longer true.
- Assignment is restricted to users who actually hold `issue.resolve` —
  the id from the browser is checked against that list, not trusted.
- History is rendered from the activity log itself, so the audit trail and the
  screen cannot drift apart. Reads activitylog 5's `attribute_changes` column
  and renders each change as *was → now* with enum labels and user names.

### Flags and links
- Machines with an open `breakdown` issue are flagged in the machine admin list
  (counted in the list query, not per row), alongside the kiosk tiles that
  already flagged them.
- The approval queue's open-issue badge and the run review's issue list now link
  into the register.

## [0.6.0] — Milestone 5: signatures and supervisor sign-off

### Signature capture
- Canvas signature pad (`<x-signature-pad>` + the `signaturePad` Alpine
  component) — pointer/stylus capture, device-pixel-ratio aware, cleared and
  re-signable, exported as a PNG data URL.
- The data URL is passed as a **Livewire action argument**, never bound as a
  property: a ~20 KB image in the component snapshot would ride every request
  in both directions for as long as the sheet is open. The pad's root carries
  `wire:ignore` so a re-render mid-signature cannot blank the canvas.
- `App\Support\SignatureImage` is the only place a signature is validated,
  stored or deleted. Validation is performed on the **decoded bytes**
  (`getimagesizefromstring` must report PNG, within size and dimension caps) —
  never on the MIME label the client wrote. Stored per run on the disk in
  `config('checklists.signature_disk')`.

### Submitting a run
- Submission now takes the operator's signature **and** an identity
  re-confirmation — PIN where the signer has one, password otherwise
  (seed-notes §D12) — rate-limited per run and user with the kiosk's lockout.
  On a shared tablet the session only proves someone signed in earlier.
- `operator_signature_path` / `operator_signed_at` are written inside the same
  transaction as the status change, on the server clock.
- A run whose template does not require sign-off is completed outright rather
  than queued for a signature nobody is entitled to give (seed-notes §D10).
- Re-signing a rejected run supersedes and deletes the earlier image.

### Supervisor sign-off
- Approval queue (`/runs/approvals`) — submitted runs oldest first, scoped by
  `MachineScope`, with failed-item and open-issue counts on the row so the
  queue shows where attention is needed before anything is opened.
- Review screen (`/runs/{run}/review`) — the completed sheet read-only: every
  item with its answer, failure reasons, photos, parts used, notes and issues,
  with the failures pulled to the top.
- **Approve** takes a supervisor signature and an optional comment; approved
  runs are immutable (`RunStatus::isEditable()` excludes them).
  **Reject** requires a comment, takes no signature — a rejection is not a
  sign-off — and returns the run to the operator, who sees the reason at the
  top of their sheet.
- Both decisions re-read the run before acting: two supervisors can hold the
  same queue open, and the second must not overwrite the first one's decision.
- The two-person rule (a supervisor cannot sign off their own work) is
  enforced in `ChecklistRunPolicy` and explained on screen when it applies,
  rather than silently hiding the buttons.

### Not in this milestone
Issues register UI, dashboards, reports, PDF export, PWA/offline, QR sticker
sheets and the admin-only amendment path for approved runs.

## [0.5.0] — Milestones 1–4

Delivered together as the first review checkpoint.

### Milestone 1 — Scaffold, auth, roles, schema, seed data
- Laravel 11 skeleton: `composer.json`, `package.json`, Vite, Tailwind, Alpine,
  15 config files, optional `docker-compose.yml` (php-fpm, nginx, mysql,
  scheduler). Queue `database`, cache `file`, no Redis anywhere.
- 22 migrations covering the **whole** domain, including tables for milestones
  5–8 (attachments, issues, signatures) so later work adds screens, not schema.
- 8 backed enums, 16 models, 5 policies, `MachineScope` for operator scoping.
- 4 roles / 24 permissions, cumulative grants.
- Login accepts **either an email address or an employee number** — floor
  operator email is mixed.
- Kiosk device enrolment (signed cookie), PIN sign-in with rate limiting and
  lockout, and a 2-minute idle drop enforced **server-side**.
- Seed data: 1 site, 4 locations, 11 machines, 10 parts, 13 templates, 82
  checklist items — all verbatim, typos preserved. Separate `DemoSeeder`
  (30 days of history) excluded from `DatabaseSeeder`.
- `README.md`, `docs/BUILD-CONTRACT.md`, `docs/seed-notes.md`.

### Milestone 2 — Admin CRUD
- Machine, location, part and holiday management.
- Checklist template management with an item builder and parts list, and
  **template versioning**: item changes bump the version; existing runs keep
  the wording they were generated with.

### Milestone 3 — Scheduling and kiosk
- `checklists:generate` — idempotent, honours working days, holidays,
  frequency and shift split; resolves "today" in the plant's timezone.
- `checklists:mark-missed` — flips expired pending runs to `missed`. Never
  deletes; missed runs are the compliance record.
- Run listing with filters; kiosk machine picker with per-machine status.

### Milestone 4 — Run completion form
- Tablet-first checklist form: 80px rows, tap-to-complete, N/A and Failed with
  reason, immediate autosave, parts steppers, notes, photo capture.
- Submission blocked while any required item is unanswered — and the
  unanswered items are named, not just counted.

### Deviations from the specification
Nine, each with rationale, in [`docs/seed-notes.md`](docs/seed-notes.md) §D.
The significant one: `checklist_runs.shift` is `NOT NULL` with an explicit
`'all'` value rather than nullable, because MySQL does not collide `NULL`s in a
unique index — a nullable `shift` would have let the generator create unlimited
duplicate runs, the exact failure the index exists to prevent.

### Known limitations
- **Never executed.** Authored without PHP, Composer or MySQL available.
- **The 13 source PDFs were unavailable**; all wording came from the written
  spec. The 1:1 mapping onto the paper forms is unverified.
- `holidays` has a unique index on `(site_id, date)`, but site-wide holidays use
  `site_id = NULL` and MySQL does not collide NULLs — de-duplication is enforced
  in application code instead.

## Unreleased

Milestones 1–8 are complete and all documentation deliverables are written.
Remaining before `1.0.0`:

**Needs a person, not code**
- A browser pass on the signature canvas (JavaScript, not covered by the suite)
- Verification of the 13 checklist templates against the original paper forms —
  `docs/source-checklists/` is still empty
- Confirmation of the 11 machine codes before any QR stickers are printed
- The open questions in `docs/seed-notes.md` §E, several of which are cheap to
  change now and expensive once history exists

**Needs building**
- Admin screens for users, roles and settings
- The machine profile screen (SPEC screen 5)
- The admin amendment path for approved runs

**First execution — 2026-08-07.** Brought up under the shipped
`docker-compose.yml`. All 22 migrations apply, seeders load, **all 34 Pest tests
pass (135 assertions)**, `checklists:generate` created 12 runs and then 0 on a
second pass, and login, dashboard, kiosk enrolment and the machine grid serve.
No code changes were needed to get there.
