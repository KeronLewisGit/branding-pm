# Seed Notes — ambiguities, assumptions and deliberate deviations

The spec says: *"keep it verbatim, typos included, and flag anything ambiguous in
`docs/seed-notes.md` rather than silently 'fixing' it."* This file is that record.

Nothing listed here has been changed in the seed data. Where wording is odd, the odd
wording is what got seeded. Each item below needs a yes/no from the Maintenance
Manager before the paper forms are retired.

---

## A. Source material

**The 13 source PDFs were not supplied.** The spec expects them in
`docs/source-checklists/`; that directory is empty. All task wording, machine names,
part codes and template groupings in the seeders were transcribed from the written
specification instead.

Consequence: the 1:1 mapping onto the paper forms is **unverified**. Before go-live,
someone must sit with the 13 printed sheets and the seeded templates side by side and
confirm the item order and wording match. Specifically unverifiable without the PDFs:

- the exact header block layout each sheet uses (the PDF export in milestone 7 mirrors
  a reconstruction, not a scan)
- whether any sheet has items printed in two columns in an order other than
  top-to-bottom-then-next-column
- whether any sheet carries a revision number or issue date that should be captured
- whether the "Used Parts" tables list quantities or only part codes

---

## B. Verbatim oddities preserved from the spec

| # | Where | What | Why it was left alone |
|---|---|---|---|
| B1 | Parts catalogue | `part_code` `XXX` for "Simple Green" | This is a real placeholder in the existing external parts system. `parts.part_code` is a **string**, never cast to int, precisely so this row survives. Ask whether `XXX` should be issued a real code before go-live. |
| B2 | Parts catalogue | Braces used as brackets: `Tooth Brush {Soft}`, `General Cleaning Towels {Rags}` | Consistent with the site name `Branding 23 — {Scarlet Ibis}`. Appears to be house style for a parenthetical, not a typo. Preserved. |
| B3 | Machine list | `HP R2000 [FB]` — square brackets, `[FB]` unexplained | Presumed "Flatbed". Not expanded. |
| B4 | Templates 5 and 6 | HP 570 Latex and HP 800W have **identical** six-item task lists | Two separate paper sheets, same content. Seeded as two independent templates, not one shared template, because they are two machines with two sign-off trails. |
| B5 | Template 6 | Machine is `HP 800W` in the machine list but the work description reads `"Daily Maintenance of the HP 800 W"` (space before W) | Description seeded verbatim with the space. Machine name seeded without. |
| B6 | Template 13 | Machine list says `Rolls Roller` (two words); template title says `RollsRoller — Mounting Table` (one word) | Machine name seeded as `Rolls Roller`, template name seeded as `RollsRoller — Mounting Table, General Maintenance`. Both verbatim. Manufacturer is one word ("RollsRoller"), so the machine list is probably the typo — **not corrected**. |
| B7 | Template 10, item 5 | `"Check Belts Once Per Month"` sits inside a **general** checklist | The item text carries its own frequency, which the system cannot enforce as written. Seeded verbatim as a normal item. Recommended fix: split it into its own `monthly` template so it is actually tracked. Flagged, not done. |
| B8 | Template 7 | Work description is `"Consult User manual where Required"` — an instruction, not a description | Seeded verbatim. |
| B9 | Templates 8, 9, 10, 13 | Work description is `"Follow Outlined Checks"`; template 3 and 11 use `"Follow Checks Outlined"` (words reversed) | Both variants seeded exactly as given. |
| B10 | Template 3, item 6 | `"Follow Checks Outlined"` appears as a **task item** as well as being the work description | Circular, but that is what the sheet says. Seeded verbatim as item 6. |
| B11 | Casing | Inconsistent capitalisation throughout — `Clean media Clamps`, `Clean substrate Belt`, `Cleaning the Free-fall Rollers` | Not normalised. Operators recognise the sheets by their exact wording. |
| B12 | Templates 11, 12, 13 | "Parts: none listed" | Seeded with **zero** template parts, not with a default set. The run form will show an empty Used Parts section. Confirm this is right — a laminator and a mounting table plausibly consume rags and IPA. |

---

## C. Ambiguities that required a decision

| # | Question | Decision taken | Reversible? |
|---|---|---|---|
| C1 | Template 7 (ESKO) has `work_category = general` but no stated frequency. Same for templates 8, 9, 10, 13. | Seeded `work_category = general`, `frequency = weekly`, `weekly_weekday = 1` (Monday). "General" describes the sheet, not a cadence, and an ungenerated template produces no compliance record at all. | Yes — one field on the template admin screen. |
| C2 | Template 7, item 10: "Check water level in chiller" is a `check` on paper but is really a measurement. | Seeded as `response_type = check`, per the spec's instruction to default everything to `check`. The `numeric` response type exists so a supervisor can upgrade this item later without a code change. | Yes, by design. |
| C3 | Template 10, item 2 ("Check Air Line / system for any leaks") and item 6/7/8 are pass/fail in nature. | Same as C2 — seeded as `check`. | Yes. |
| C4 | Which templates are shift-split? | Only `daily` templates get `per_shift = true` (one run per shift). `weekly` and `general` templates get `per_shift = false` and run once, with `shift = 'all'`. Rationale: a weekly deep-clean is not done twice a day. | Yes — `per_shift` toggle per template. |
| C5 | Which weekday for weekly templates? | Monday (`weekly_weekday = 1`) for all of them. | Yes. |
| C6 | Grace period before a run is marked `missed`. | 24 hours for every template, the spec's stated default. | Yes. |
| C7 | The site seed is `Branding 23 — {Scarlet Ibis}`. Is `Branding 23` the code and `{Scarlet Ibis}` the name, or is the whole string the name? | Seeded `name = "Branding 23 — {Scarlet Ibis}"`, `code = "BR23"`. | Yes. |
| C8 | Machine `code` values (the QR sticker slug) are not given in the spec. | Generated as slugs of the machine name: `matan`, `hp-stitch-1000`, `hp-r2000-fb`, `hp-570-latex`, `hp-800w`, `esko-c64-kongsberg`, `hiker-grommet-machine`, `mistral-1650-65-laminator`, `miller-112-cross-seamer`, `monti-antonio`, `rolls-roller`. **These are printed on physical stickers — changing one after the stickers are printed breaks that machine's QR code.** Confirm before printing. | Expensive after printing. |
| C9 | `machine_part` (which parts are normally consumed on which machine) is not given separately from the template parts lists. | Derived: each machine's `machine_part` rows are the union of the parts on that machine's templates, in first-seen order. Monti Antonio and Rolls Roller therefore have none. | Yes. |
| C10 | Manufacturer / model / asset_tag for the 11 machines. | Left `null` — inventing asset tags would put fiction into an audit record. Manufacturer was filled only where it is unambiguous from the machine name itself (HP, ESKO, Miller, Monti Antonio, RollsRoller); MATAN, Hiker and Mistral left null. | Yes. |
| C11 | Public holidays. | Trinidad & Tobago public holidays seeded for the current year with `is_recurring = true` only for the fixed-date ones (New Year's Day, Spiritual Baptist Liberation Day, Indian Arrival Day, Labour Day, Emancipation Day, Independence Day, Republic Day, Christmas, Boxing Day). Movable feasts (Carnival Mon/Tue, Good Friday, Easter Monday, Corpus Christi, Eid, Divali) are seeded as **non-recurring, current-year only** and must be re-entered each year on the Holidays admin screen. | Yes. |

---

## D. Deliberate deviations from the written spec

Each of these is a change to what the spec literally said. Rationale given; each is
cheap to reverse if you disagree.

| # | Spec said | Built instead | Why |
|---|---|---|---|
| D1 | `checklist_runs.shift` is a *nullable* enum, with a unique index on `(checklist_template_id, scheduled_for)`. | `shift` is `enum('day','night','all')` **NOT NULL**, default `'all'`, and the unique index is `(checklist_template_id, scheduled_for, shift)`. | Two shifts were confirmed, so the index must include `shift` or the second shift's run cannot be created. But in MySQL a `NULL` in a unique index does not collide with another `NULL` — a nullable `shift` would let `checklists:generate` create unlimited duplicate runs for every non-shift-split template, which is the exact failure the index exists to prevent. `'all'` is the explicit "not shift-split" value. |
| D2 | — | Added `checklist_templates.per_shift` (bool). | Needed to know which templates split by shift. Without it the generator has to guess from `frequency`. |
| D3 | Template management "versions" the template via a `version` integer. | Added `checklist_templates.version` and `checklist_runs.template_version`. | The spec requires versioning in the workflow section but omits the column from its own schema listing. |
| D4 | Weekly templates run on "a configurable weekday per template". | Added `checklist_templates.weekly_weekday` (ISO 1–7) and `monthly_day` (1–28). | No column was specified for the thing the spec says must be configurable. `monthly_day` caps at 28 so a monthly template never silently skips February. |
| D5 | Users have `email` nullable. | `users.password` is **also** nullable. | Confirmed answer was "mixed" — some operators have company email, some do not. A PIN-only operator has no password at all, and a nullable-but-required column would force a fake one. Login accepts email **or** employee number. |
| D6 | — | Added `checklist_run_items.fail_reason`, `response_type` and `is_required` (snapshotted alongside `description`). | The spec snapshots only `description`, but if a supervisor later changes an item from optional to required, historical runs would retroactively appear non-compliant. Snapshotting `is_required` and `response_type` keeps a March run judged by March's rules. |
| D7 | — | Added `checklist_run_parts.part_code_snapshot` (spec has only `part_name_snapshot`). | Same reasoning: the external part code can be reissued (see B1 — `XXX` in particular is likely to change). |
| D8 | — | Added tables `user_machine`, `kiosk_devices`, `settings`. | Operator→machine scoping, kiosk device authentication and the configurable settings the spec asks for all need somewhere to live. |
| D9 | Approve/reject is a supervisor permission. | A supervisor **cannot approve a run they themselves operated**, even if they hold `run.approve`. | The paper form has two distinct signature blocks. Letting one person sign both destroys the control the form exists to provide. If this blocks a real single-person shift, it should be an explicit, logged setting — not an accident. |

---

## E. Open questions for the Maintenance Manager

1. **Shift boundaries.** Two shifts are confirmed, but not their clock times. What time
   does night shift start, and does a night shift that crosses midnight belong to the
   date it started or the date it ended? The generator currently books both shifts
   against the **calendar date the shift starts**.
2. **B7** — should "Check Belts Once Per Month" become its own monthly template?
3. **B12** — do Monti Antonio, Rolls Roller and the Mistral laminator really consume no
   parts, or is the paper sheet simply missing a Used Parts table?
4. **C8** — confirm the 11 machine codes before any QR stickers are printed.
5. **C11** — who maintains the holiday calendar each January?
6. Does a run still count as compliant if it is completed late but within the grace
   period, or should late-but-completed be reported separately from on-time? Currently
   a run completed within grace is simply `approved` with no lateness flag.
7. Does any of this eventually have to feed an existing CMMS or ERP? Assumed **no** for
   now; CSV export is the seam if the answer changes.
