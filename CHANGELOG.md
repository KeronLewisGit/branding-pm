# Changelog

Versions track build milestones: `0.<milestone>.<patch>`. The project reaches
`1.0.0` when all 8 milestones are complete and the paper forms are retired.

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

- Milestone 5 — signature capture, supervisor approval/rejection
- Milestone 6 — issues register
- Milestone 7 — dashboards, reports, CSV/PDF export
- Milestone 8 — PWA/offline, QR sticker sheets, Pest suite, deployment docs
