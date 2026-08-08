# Changelog

## [0.19.1] — Production settings, made safe by default and verifiable

### SESSION_SECURE_COOKIE
Defaulted to Laravel's `null`, which means "never Secure" — session and kiosk
cookies would have gone over plain HTTP on a production host, readable off the
shop-floor Wi-Fi and replayable.

It now defaults to **true when `APP_ENV=production`** and false elsewhere. A
production host gets HTTPS-only cookies without anyone remembering to set it,
and a plain-HTTP pilot is not locked out of its own login page. An explicit
`SESSION_SECURE_COOKIE=false` still wins — that is the pilot override, and the
check below keeps reporting it.

Consequence, spelled out in the deployment guide: the site must be on HTTPS
*before* `APP_ENV` flips, or nobody can log in and every enrolled tablet looks
un-enrolled.

### APP_DEBUG
Already defaulted to `false` in `config/app.php`, so nothing to fix — but
nothing verified it either, and it is the setting that leaks `.env`, `APP_KEY`
and the database password to anyone who can reach an error page.

### `php artisan security:check`
Exits non-zero on anything wrong, so it can gate a deploy script. `--strict`
also fails on warnings. It checks `APP_DEBUG`, `APP_KEY`, secure cookies,
whether `APP_URL` is HTTPS, whether signatures are on the public disk, whether
demo accounts are still active, and whether the admin password is still the
old published default.

Warnings rather than failures outside production: a pilot on plain HTTP with
debug on is a legitimate state, and a check that cries wolf gets ignored.

Run against this repository's own dev install it reports one **FAIL** — the
admin password is still `ChangeMe!2026`.

### A boot-time reminder
A production host logs a `critical` line while `APP_DEBUG` or insecure cookies
are on. Deliberately not an exception: refusing to boot takes the checklists
off the shop floor over a configuration mistake, and an application that will
not start gets "fixed" by whoever is on shift deleting the check.

### Also
- `DEPLOYMENT.md` §3 still described the removed published-default password;
  it now documents the print-once random one. Sections renumbered.
- Found while testing: `app()->isProduction()` reads the environment bound at
  bootstrap, so it cannot see a runtime config change — the command graded a
  production configuration against pilot rules. It reads `config('app.env')`.
- 10 new tests (155 total, 474 assertions).


Versions track build milestones: `0.<milestone>.<patch>`. The project reaches
`1.0.0` when all 8 milestones are complete and the paper forms are retired.

## [0.18.0] — UI standardisation

A consistency pass over every screen. No behaviour changed; 139 tests pass
unchanged and all 23 pages were re-checked by hand.

### Tables
Four different `<thead>` class strings and four `<table>` ones had grown up
across the screens — `tracking-wide` against `tracking-wider`, `slate-500`
against `slate-600`, some with a header fill and some without. Nobody chose
that; it is what happens when the next screen copies whichever one was open at
the time.

Now one definition in `app.css`: `.table-wrap`, `.table-scroll`, `.data-table`
and a `.data-table-bare` variant for tables that sit inside an already-padded
card. Every `<th>`/`<td>`/`<tbody>` shed its per-cell padding classes, since
the table now supplies them — the markup for a list is materially shorter and
a new screen cannot invent a fifth style.

`.table-scroll` is deliberately separate: a wide table must scroll inside its
own card, never take the page with it.

### Cards
`p-4`, `p-5` and `p-6` were all in use with no rule behind which was which,
and `<x-card>` added a fourth inset at `p-6` sitting next to raw `p-5` cards
on the same screen. Three names, one value each: **`.card-body`** for a
content card, **`.filter-bar`** for the filter row above a list, and
**`.stat-tile`** for a compact metric. `<x-card>` now uses `.card-body` too,
with `px-5` headers and footers so all three line up.

`mt-6` is baked into `.filter-bar` because one screen had left it off — the
kind of thing nobody reports and everybody notices.

### Counts
Table styles 4 → 1. Header styles 4 → 1. Card paddings 4 → 3, each with a name
that says when to use it.

## [0.17.1] — Icon actions in every list

The machines list carried five word-buttons — Profile, Edit, Parts, Operators,
Delete — which wrapped onto three lines and made the table look unfinished.
Kiosk devices had five, users four.

`<x-icon-button>` replaces them everywhere an Actions column exists: machines,
locations, parts, holidays, templates, the template editor (items and parts),
kiosk devices, users and the QA queue. One 44px square per action, on one row.

Discoverability is the trade, so the component makes the accessible name
**non-optional**: `aria-label` for screen readers, `title` for the hover
tooltip, and the icon itself `aria-hidden` so nothing is announced twice. 44px
is the WCAG 2.2 target-size minimum, and these sit shoulder to shoulder.

The icon vocabulary lives in one map inside the component, so "edit" looks the
same on every screen and a new list cannot invent its own pencil. Two places
that had hand-rolled icon buttons — the template editor's reorder arrows and
the machine parts modal — now use it too, which deleted four copies of an
inline SVG.

Filter and form buttons keep their words. This is about the Actions column,
where the meaning is carried by the row.

## [0.17.0] — Quality Assurance: a third sign-off

Operator signs, supervisor approves, **QA verifies**. Three people, three
separate acts, and the last performed by somebody who did neither of the first
two.

### The role
`quality_assurance` is **standalone — not a rung on the cumulative ladder**.
It holds `run.view`, `run.verify`, `issue.view`, `machine.view`,
`machine.view_all`, `template.view`, `report.view` and `export.data`. It does
**not** hold `run.complete`, `run.approve` or `run.amend`, and there is a test
asserting each of those.

That separation is the whole point. An auditor asking "who checked the
checker?" must not be told "the same person".

`machine.view_all` is a new permission: QA is a plant-wide function and an
officer restricted to one site could not do the job. Maintenance managers get
the same reach through `machine.manage`.

### Verifying
`qa_verified_by`, `qa_verified_at` and `qa_comment` on `checklist_runs`,
nullable throughout — nothing backfills, because inventing a verification
nobody performed is exactly what these columns exist to prevent.

Verification **does not change the run's status**. `approved` already means
"the supervisor signed this off"; a fourth status would silently change what
every existing compliance figure means. It is recorded alongside and reported
separately.

Refused when: the sheet is not approved, it has already been verified, or the
verifier operated or approved it themselves. That last one only fires for
somebody holding several roles — which is precisely when it matters.

A finding is optional. Most verifications have nothing to say, and forcing a
comment fills the record with "ok".

### Where it shows
- **QA Verification** queue — approved sheets nobody has verified, oldest
  first, with failed-item counts on the row. The mirror of the approval queue,
  one step along. A backlog nobody can see is a backlog nobody clears.
- The review screen gained a QA panel, visible to everyone who can read the
  sheet once verification has happened — "who checked the checker" is part of
  the record, not a private note.
- **Checks completed** gained a *QA verified by* column and a verified count.

The review route previously required `run.approve`, which QA does not hold —
it now accepts `run.approve|run.verify`. Worth noting the component tests
passed throughout; only an HTTP test caught it, because Livewire component
tests bypass route middleware.

### Tests
15 new (139 total, 441 assertions).

## [0.16.0] — The amendment path for approved runs

The last item the specification asked for: *"Approved runs are immutable —
corrections happen by an admin-only amendment that is logged, never by silent
edit."* `run.amend` and `ChecklistRunPolicy::amend()` had been in place since
milestone 1 with nothing calling them, so a correction after sign-off had no
route through the UI at all — which in practice means somebody eventually
opens the database.

On the run review screen, a holder of `run.amend` (maintenance manager and
admin) can correct:

- **an item's answer**, and its failure reason
- **the operator's notes**
- **a parts quantity**

Every amendment **requires a written reason** and records the field, the old
value, the new value, the reason, the actor and the IP to the activity log.

### What it deliberately cannot do
Signatures, timestamps and the status are untouched, and there is a test for
it. Those are the attestation itself — a correction is not a re-approval, and
re-signing on somebody else's behalf is precisely what the two-person rule
exists to prevent. A sheet that needs a different signature needs a different
sheet.

An amendment where nothing actually changed is refused rather than logged: a
reason recorded against a change that never happened reads as though something
was corrected.

### Visible to the reader, not just the amender
The amendment history renders on the review screen from the activity log
itself — for **everyone who can read the sheet**, including supervisors who
cannot amend. A correction only the corrector can see is the silent edit the
specification forbids.

One consequence worth stating: amending changes the run's verification hash,
so a sheet printed before the amendment stops matching. That is the tamper
check working, not breaking — and the amendment history is what explains the
difference.

### Tests
12 new (124 total, 403 assertions): who may amend, refusal on a run that is
not approved, all three targets recording both values with the reason and
actor, the reason requirement, the no-op refusal, signatures and status left
alone, and the history being visible to a supervisor.

## [0.15.0] — Machine profile, and a sidebar that fits the role

### Machine profile
`/machines/{machine}` — SPEC screen 5, the last screen the specification
asked for. Details, the machine's own QR, its checklists with item counts and
template version, recent checks, faults, parts consumed, and who is assigned
to it. Over a 30 / 90 / 365-day window.

All of it was reachable before, but only by visiting the runs list and
filtering, then the issues register and filtering again, then a report. "What
is going on with the MATAN?" had no single answer.

Gated on `MachinePolicy::view` — `machine.view` plus the machine scope — so it
is **not** admin-only. The people who need a machine's history are the ones
standing next to it. Reached from the machines admin, and from the machine
name in the runs list and the issues register, which is what makes it useful
to a supervisor who has no admin menu at all.

Compliance on this screen follows the same rule as everywhere else: a window
with nothing due shows a dash, because 0% and 100% would both be lies. Parts
group on the snapshot name, matching the parts usage report. `scheduled_for`
is printed without timezone conversion — it is a calendar date.

Unlike the kiosk's `/m/{code}`, an unknown code is a plain 404: an office
screen reached from a list has no peeling sticker to account for.

### The sidebar
**Fixed: an operator was shown a Dashboard link that bounced them.** It was
gated on `run.view`, which operators hold, but the Dashboard component
redirects anyone without `report.view` straight to `/runs` — so the one link
an operator was most likely to try sent them back where they already were. Now
gated on `report.view`.

Regrouped into **work** (no heading — for an operator that is the whole menu),
**Plant**, and **System**. The admin heading previously appeared only for
`machine.manage`, `part.manage`, `template.manage` or `holiday.manage`, so a
holder of `kiosk.manage` or `user.manage` alone got those links with no
heading above them.

**QR sticker sheet left the sidebar.** It is a task you perform on machines,
not a section of the system, and it now sits in the machines screen header and
on each machine's profile — one fewer permanent entry, found where you would
look for it.

Icons throughout, decorative and `aria-hidden` since every entry is named in
text beside it. Labels truncate rather than wrap, so a long entry cannot push
the sidebar around.

What each role now sees: operator **2** entries, supervisor **5**, maintenance
manager **10**, administrator **12**.

### Tests
13 new (112 total, 373 assertions): the profile reachable by all four roles,
refused across sites, 404 on an unknown code, panels gathering the right
records, the window whitelist rejecting a hand-edited value, the no-percentage
rule, and one test per role pinning exactly what the sidebar offers.

## [0.14.0] — User administration

**Admin → Users**, gated on `user.manage` — a permission only the `admin`
role holds, so the screen is admin-only by construction rather than by a
second check. A maintenance manager runs the plant, not the user list.

Until now there was no way to create a person at all. Operators, their PINs
and their roles came from a seeder or tinker, so a new starter on a Monday
needed a developer.

- Create and edit: name, employee number, email (blank for floor operators —
  most have no company address), role, site, password, PIN, active.
- **A blank password or PIN on edit means "leave it alone".** Correcting a
  spelling must not silently lock somebody out of a kiosk.
- **Clear PIN** for the whiteboard case. A PIN can be replaced, never read
  back.
- A *Signs in with* column — password, PIN, both, or **cannot sign in**. That
  last one is the single most common support question about a floor operator.
- Removal is a soft delete plus a deactivate. Runs, signatures and issues
  reference users with `nullOnDelete`, so a hard delete would strip the name
  off signed work; a maintenance record is never rewritten.

### The lockout guards
An administrator cannot demote, deactivate or delete **their own account**,
and the last active administrator cannot be removed by anyone. Both are
unrecoverable from inside the application — there is no second screen to undo
them from.

`UserPolicy::before()` returned a blanket `true` for admins, which bypassed
the policy's own self-delete check because `before()` cannot see the target
model. It now defers on `delete`.

Worth being honest about the second guard: only admins can reach this screen
and an admin is already blocked from acting on themselves, so the last-admin
count check is reachable only when `user.manage` has been granted directly to
a non-admin. It is defence in depth, and the test exercises it that way rather
than pretending otherwise.

### Tests
18 new (99 total, 327 assertions): role-by-role access, a PIN-only operator
with no email or password, PIN format, employee-number reuse, blank fields
preserving credentials, PIN replacement and clearing, every self-guard, the
last-admin guard, and that a spare administrator *can* still be deactivated —
a guard that fired there would make the role impossible to hand over.

## [0.13.1] — Issues scope tighter than runs

Two scopes now, because the two screens want opposite things.

`MachineScope::for()` — **runs, the kiosk, reports.** Site-wide. Anybody may
have to cover any machine at short notice, and a checklist nobody can open is
a checklist that does not get done.

`MachineScope::forIssues()` — **the issues register, its detail screen, its
policy and the dashboard tile.** An assignment narrows it: the register is a
standing worklist, and a plant-wide one buries the three faults on the
machines somebody actually runs.

Two judgement calls, both reversible, both stated here because they are the
kind of thing that is invisible until it annoys somebody:

- **A user with no assignments still sees their whole site.** Almost everybody
  is in that state — `user_machine` was unreachable from the UI until 0.13.0 —
  and an empty register reads as broken rather than tidy.
- **Reporting a fault is not narrowed.** The "raise an issue" picker still
  offers every machine at the site. An operator who walks past a bearing
  screaming on a machine they are not assigned to must be able to say so.
  Accepted consequence: they may raise a fault that does not then appear in
  their own register. The alternative is an unreported fault.

The dashboard's open-issues tile moved to the issue scope too — it links into
the register, and a count that disagrees with the list behind it is worse than
no count.

### Tests
4 new (81 total, 274 assertions): the same user seeing a machine's runs but
not its issues, the no-assignment fallback, the detail screen returning 403
for an issue on an unassigned machine, and the raise picker staying wide.

## [0.13.0] — Who did the check, and how they came to be doing it

### A report that names names
`Checks completed` — one row per sheet: operator, employee number, machine,
checklist, shift, the day it was **due**, the moment it was **signed**, status
and who signed it off. On screen, CSV and PDF like the other four.

Every existing report is an aggregate — a percentage per machine, a count per
operator. None answered the question an auditor actually asks: *who signed
this one, and on what day?*

Two dates on purpose. `scheduled_for` is the day the work was owed and
`submitted_at` the moment it was attested; collapsing them into one column
would hide lateness entirely.

Only finished work appears. A sheet in progress has an operator but no
completion date, and a row whose date column is a dash answers nothing —
outstanding and missed work is the missed-checks report's job.

**Caught while testing:** `scheduled_for` is a date cast to midnight UTC, so
formatting it through `America/Port_of_Spain` rendered every run a day early —
a report claiming the 6th for work scheduled on the 7th. It is now formatted
without timezone conversion, with a test pinning it.

### Assignment stopped being a fence
`MachineScope` narrowed an operator to their `user_machine` rows, and showed
the whole site only to operators with **no** assignments. That is backwards:
assigning somebody to the two machines they normally run *removed* their
ability to cover a third when a shift was short, and the fix was an
administrator editing a pivot table that had no screen. Nobody does that at
6am, so in practice the table stayed empty and the fence never existed.

Now everyone sees every machine at their site, and assignment marks a machine
as somebody's usual work rather than gating it. **The site is still the
boundary** — an operator cannot see another site's work, and there is a test
for it.

Also fixed a trap in the old rule: a user with assignments but no
`default_site_id` saw nothing at all. Sites are now inferred from assignments
when no default is set.

### Assigning someone, from a screen
`user_machine` has existed since milestone 1 with no UI whatsoever. **Admin →
Machines → Operators** attaches and detaches people, logged to the activity
trail. The modal says in as many words that the list is not a permission,
because a list of names beside a machine reads like one.

Not restricted to the operator role: supervisors and managers cover shifts and
complete sheets themselves, and an assignment list that could not name them
would be wrong about who works a machine.

### Self-assignment and hand-over
The first tap on a sheet already claimed it. What was missing is what happens
when a **second** person continues it — shifts change mid-checklist and a
tablet gets picked up by whoever is free.

Blocking that would strand half-finished work; letting it happen silently
would leave the record naming somebody who did not do the second half. So it
is allowed, the sheet moves to whoever is working it, the hand-over is written
to the activity log with the previous operator's name, and the screen says so.
Whoever **signs** is still the operator of record (seed-notes D13).

### Tests
12 new (77 total, 266 assertions): assignment not narrowing, cross-site still
blocked, the no-default-site fallback, the nothing-at-all case, attach/detach
through the admin screen, rejection of an inactive user id, self-claim on
first tap, hand-over logged, no hand-over logged for the same operator, and
three on the new report including the timezone bug.

## [0.12.0] — A kiosk is not always a tablet

The whole surface assumed tablets: the screen was "Kiosk Tablets", the actions
said "Enrol a tablet", and enrolment offered exactly one method — carry the
device to this screen and scan a QR code. That is useless for a laptop or a
panel PC, which cannot scan a code displayed on their own screen, and 0.11.1
had already bolted an "Enrol this browser" escape hatch into the modal for
exactly that reason.

### Device kinds
`App\Enums\KioskDeviceKind` — tablet, laptop, desktop, phone, other — declared
when the device is added, stored on `kiosk_devices.kind`. It decides which
enrolment method is offered **first**:

- **scan** for tablets and phones — something carried to this screen
- **enrol this browser** for laptops, desktops and anything unrecognised — the
  machine the administrator is already sitting at

Both methods are always rendered, in an order chosen by the kind, so guessing
wrong costs a scroll rather than a dead end.

The screen, the nav entry and every string are now "device" rather than
"tablet"; the list gained a Device type column and a filter.

### Noticing when the kind is wrong
`kiosk_devices.last_user_agent` records what actually used the device, and the
list flags a device being driven from hardware that does not match what was
recorded — a "tablet" used from a laptop usually means an enrolment link was
opened on the wrong machine.

That check reads a User-Agent, so it is a hint and never a block, and it is
deliberately generous: `Other` matches anything, and an unrecognised
User-Agent accuses nobody.

`KioskDeviceKind` (declared, stored, drives the UI) is kept separate from
`App\Support\DeviceType` (guessed per request, only ever picks a noun).
Collapsing them would have made the mismatch check impossible to express.

### Migration
`2026_08_07_000100_add_kind_and_user_agent_to_kiosk_devices_table` — the first
migration since the original 22. `kind` defaults to `tablet`, which is what
every existing row implicitly was.

### Tests
8 new (65 total, 232 assertions): the kind defaulting and round-tripping,
rejection of a kind outside the enum, the modal ordering for a laptop and for
a tablet, both methods present for **every** kind, the User-Agent being
recorded, a mismatch flagged, a matching device not flagged, and the filter.

## [0.11.2] — The not-enrolled screen says what it is looking at

The 403 told everyone that "this **tablet** is not set up as a kiosk" and to
"ask your supervisor or IT to enrol this tablet". On a laptop that reads as a
broken message and sends the reader off looking for a tablet — and on a laptop
the reader is very often the person who can fix it.

`App\Support\DeviceType` buckets the User-Agent into tablet / phone / computer
/ unknown, and the screen is worded from it. The help line differs by more than
the noun: at a computer it points to **Admin → Kiosk Tablets → Enrol this
browser**, because that person can usually enrol themselves; on the floor it
still says go and find someone. A phone is told the kiosk runs on the floor
tablets.

**The verdict is wording only.** A User-Agent is client-controlled, so it must
never gate access — there is a test asserting a phone with a valid device
cookie still gets the kiosk.

### The iPad problem
Since iPadOS 13, Safari on iPad requests desktop sites **by default** and sends
a Macintosh User-Agent, byte-for-byte what a MacBook sends. No server-side
pattern matching separates them, so naive sniffing would have called this
project's own test iPad a computer.

The server renders the computer wording and ships a small script that swaps in
the tablet wording when the browser reports more than one touch point. It is
attached **only** for Macintosh: a Windows touchscreen laptop also reports
touch points, and calling that a tablet is a worse answer than calling it a
computer. Progressive enhancement — with JavaScript off the page still reads
correctly, just as "computer".

No package. A UA database exists to tell thousands of phone models apart; this
needs four buckets, and the plant IT team would inherit the upkeep.

### Tests
8 new (57 total, 200 assertions), including a dataset over six real
User-Agents, the empty-UA fallback, the iPad-as-Mac correction being present
for Macintosh and absent for Windows, and the assertion that device type never
affects access.

## [0.11.1] — Kiosk review: running it on a laptop

Reviewed the kiosk for a PC rather than the 10" tablet it was designed around.
The interaction model holds up — the "N/A / Fail" control is a visible button
on every row and long-press is only an accelerator, the signature pad is on
pointer events so a mouse draws, and right-click opens the actions sheet. Two
real defects and one footgun.

### Fixed: the idle timeout was only half configurable
`EnforceKioskIdleTimeout` reads `checklists.kiosk_idle_seconds`, but the kiosk
layout hardcoded `idleRelease(120, …)`. Raising
`CHECKLISTS_KIOSK_IDLE_SECONDS` moved the server's deadline while the browser
went on dropping the operator at two minutes — the setting looked broken
rather than ignored. The layout now renders the same config value, verified
end to end (600 in, 600 out) and covered by a test.

### Fixed: no way to enrol the machine you are sitting at
Enrolment is by QR code, and you cannot scan a QR with the screen displaying
it — which is exactly the case when the app is served from the laptop being
used as the kiosk. The enrol modal now offers **Enrol this browser**, wired to
the pre-existing authenticated `kiosk.enrol` route, which had no UI at all.

### Documented: one browser cannot be both admin console and kiosk
PIN sign-in calls `Auth::login()` on the same session, so it replaces whoever
is signed in — PIN in as an operator on your admin browser and the admin
session is gone, and the idle drop then logs it out entirely. Not a bug, but
sharp enough to warrant saying so in the modal and in `DEPLOYMENT.md` §7.

### Noted, not changed
- **`http://localhost` is a secure context**, so a PC kiosk registers the
  service worker and can exercise the PWA and offline queue with no HTTPS
  setup — the one thing a LAN-connected tablet cannot do. Now in the docs,
  along with the `--kiosk` flags for Chrome and Edge.
- **The `settings` table is dead data.** `SettingSeeder` writes
  `kiosk.idle_timeout_seconds`, `kiosk.pin_max_attempts` and
  `kiosk.pin_lockout_minutes`, but nothing reads the `Setting` model — every
  one of those values actually comes from `config/checklists.php` via `.env`.
  Changing a seeded setting has no effect. Belongs with the missing
  Admin → Settings screen.
- The kiosk `<main>` has no max-width, so content spans a wide monitor
  edge to edge. Cosmetic.
- The idle watchdog resets on `pointerdown`, `touchstart`, `keydown`, `wheel`
  and `scroll` — correct for a tablet, but mouse movement alone does not count
  as activity. Reading a long checklist without touching anything can still
  time out.

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
