# User guide

Three guides, one for each job. Find yours and read only that part.

- [For the operator](#for-the-operator) — you do the checks on the machines
- [For the supervisor](#for-the-supervisor) — you check the operators' work and sign it off
- [For the maintenance manager](#for-the-maintenance-manager) — you set up the machines, the checklists and the reports

This system replaces the printed maintenance sheets. The screen asks the same
questions, in the same order, with the same numbering as the paper form you are
used to. Nothing has been added or reworded.

---

# For the operator

You use the **tablet mounted near the machines**. You do not need a password
and you do not need an email address. You need your **PIN** — the 4 to 6 digit
number your supervisor gave you.

## What the tablet shows when nobody is using it

A grid of machine tiles. Each tile has the machine name and a coloured dot with
a word next to it:

| Dot | Word | What it means |
|---|---|---|
| Grey | **Due** | A check is waiting to be done today |
| Blue | **In progress** | Somebody has started it and not finished |
| Green | **Done** | Today's check is finished and submitted |
| Red | **Overdue** | The check was due and is now late |

If a machine has a breakdown reported against it, the tile says so. Do not start
a check on a machine that is broken down without speaking to your supervisor.

Never rely on the colour alone — the word next to it says the same thing.

## Doing a check, start to finish

**1. Find the machine.**
Either tap its tile on the tablet, or scan the **QR sticker on the machine
itself** with the tablet camera. Scanning takes you straight to that machine.

If the sticker is scuffed or dirty and will not scan, the machine's code is
printed in plain text underneath the square. Find that code in the tile grid.

**2. Tap the checklist you are doing.**
A machine can have more than one — a daily and a weekly, for example. Tap the
one that is due.

**3. Tap your name.**
A list of operators appears. Tap yours.

**4. Type your PIN.**
Four to six digits, then the tick.

> If you get your PIN wrong several times in a row, the tablet locks you out for
> a few minutes. This is on purpose. Wait, then try again. If you have genuinely
> forgotten it, your supervisor can set a new one — nobody can look up the old
> one, not even the manager.

**5. Work down the list.**

The checks are the same ones, in the same order and with the same numbers, as
the paper sheet. The screen shows how far along you are — "**7 of 9**".

For each row:

- **Tap the row** — that marks it **done**. This is what you will do most of the time.
- **Tap the small button on the right** to mark it something else:
  - **N/A** — this check does not apply today (a part that is not fitted, for example)
  - **Failed** — you checked it and it is not right

**If you tap Failed, the screen asks you why.** Type what you found, in your own
words, the way you would have written it in the margin of the paper sheet. Some
checks also ask for a photo — take one with the tablet camera.

A failed check **automatically raises a fault report** for the maintenance team.
You do not have to report it separately. Reporting a fault is the job working
correctly, not a mark against you.

> **Everything saves the moment you tap it.** You do not have to press save.
> If the tablet goes to sleep or somebody walks off with it, nothing is lost.

**6. Record any parts you used.**

Below the checks is the **Used Parts** list — the same table as on the paper
sheet, already filled in with the parts that machine normally takes. Each has a
**minus** and a **plus** button. Leave it at 0 if you used none.

**7. Add notes if you need to.**

The **Notes** box at the bottom is the same "Notes:" box on the paper form.
Anything worth telling the next shift goes here.

**8. Sign.**

Tap **Submit**. You will be asked to:

- **Sign your name** with your finger or the stylus in the white box. If it
  comes out wrong, tap **Clear** and sign again.
- **Enter your PIN once more.** This is not a mistake — the tablet is shared, and
  the PIN is what proves the signature is yours and not whoever used the tablet
  before you.

Then tap **Submit** again. The tablet returns to the machine grid, ready for the
next person.

## Things that will happen sooner or later

**"You cannot submit yet."**
A required check is still unanswered. The screen **names the ones it is waiting
for** — it will not just tell you a number. Scroll to them, answer them, submit
again.

**The tablet says it is offline.**
Keep working. Your answers are held on the tablet and sent when the Wi-Fi comes
back. A badge at the top shows how many are waiting. **Do not walk away while
that badge is showing a number** — wait for it to clear, or tell your supervisor.

You **cannot submit and sign** while offline. Signing needs the network. If you
finish a sheet during a Wi-Fi outage, leave the tablet on that sheet until the
connection returns, then sign.

> **If you reload the page or the tablet restarts while answers are still
> waiting to send, those answers are lost** — and a red banner tells you exactly
> how many, so you can enter them again. The system would rather lose an answer
> loudly than save the wrong one quietly.

**A sheet comes back to you marked "Rejected".**
Your supervisor sent it back. Their reason is at the top of the sheet in red.
Fix what they asked, then sign and submit again.

**The tablet logs you out on its own.**
After two minutes of nobody touching it, the tablet drops back to the machine
grid. This is so the next person cannot sign as you. Tap your name and PIN again
— your answers are still there.

## What you cannot do

Change a sheet after a supervisor has approved it, edit the checklists, or see
machines you are not assigned to. If a check is missing from a list, or a check
on the screen does not match the machine in front of you, tell your maintenance
manager — do not work around it.

---

# For the supervisor

You do everything an operator does, plus you sign off their work. You log in
with an **email address and password**, or with your **employee number and
password**, at the normal login screen — not the kiosk tablet.

## If you forget your password

On the login screen, click **Forgotten your password?** and type your company
email address. You will be sent a link that lets you set a new one. The link
works **once**, and stops working after an hour. If it has expired, ask for
another.

The screen says the same thing whether or not the address is one we know —
that is deliberate, so the form cannot be used to find out who works here. If
nothing arrives, check your junk folder first, then ask an administrator.

If you do **not** have a company email address, this cannot help you. An
administrator sets your password for you on the users screen. Operators who
sign in with a PIN are in this position — see [For the operator](#for-the-operator).

## Your job in this system

An operator finishes a sheet and signs it. It then waits for you. Until you
approve it, the work is not counted as complete.

## The approval queue

Go to **Runs → Approvals**. This lists every sheet waiting for you, **oldest at
the top** — clear from the top down.

Each row shows, before you open anything:

- the machine and the checklist
- who signed it and when
- **how many checks failed**
- **how many faults are open** on that machine

A row with failures on it is the one to open first.

## Reviewing a sheet

Tap the row. You get the completed sheet, read-only, with **the failed checks
pulled to the top** so you are not hunting for them.

For each failed check you see the operator's reason in their own words and any
photo they took. Look at the photos. They are the reason the operator took them.

Below that: every other check with its answer, the parts used, the notes box,
and any faults raised off this sheet.

## Approving

Tap **Approve**. Sign in the box with your finger or a stylus. Add a comment if
you want to — it is optional.

**Once you approve, the sheet is locked.** Nobody, including you, can edit it
afterwards. A correction after approval has to go through an administrator and
is recorded as an amendment. So read it before you sign.

## Rejecting

Tap **Reject**. **You must write a comment** — the system will not let you send
a sheet back without saying why. Be specific: "photo of the weld roller is out
of focus, retake it" is useful; "redo" is not.

You do not sign a rejection. A rejection is not a sign-off.

The sheet goes straight back to the operator with your reason at the top of it.

## The rule you will run into

**You cannot approve your own work.** If you covered a shift and completed a
sheet yourself, another supervisor has to sign it off. The buttons are still
there but they explain why they will not work — the system tells you rather than
hiding them and leaving you guessing.

## Two of you in the queue at once

If you and another supervisor both have the same sheet open and they approve it
first, your decision will not overwrite theirs. The system re-reads the sheet
before acting. You will be told it has already been decided.

## Faults

Go to **Issues**. Every failed check on a sheet has already created a fault here
— you do not create it.

Faults are listed **worst first**: still-open before closed, breakdowns before
high before medium, then oldest first. The counts across the top are also
buttons — tap **Breakdown** to see only breakdowns.

Open a fault and you see the evidence first: which check failed, the operator's
words, their photos, and a link back to the sheet.

What you can do:

- **Acknowledge** it — you have seen it
- **Start** it — work is underway
- **Resolve** it — the fault is fixed. **You must write resolution notes.**
- **Reopen** it — the repair did not hold, or it has come back

You can also **raise a fault directly** from the register, without a checklist,
for something you spot walking the floor.

Nothing here is ever deleted. A fault is part of the maintenance record.

## Reports

**Dashboard** shows compliance for the last 7, 30 or 90 days, what is due today,
what is overdue, and how many sheets are waiting for your signature.

**Reports** gives you four reports over any date range: compliance by machine,
missed checks, parts usage and operator activity.

Read **operator activity as a record, not a score.** It counts sheets completed
and faults raised. Finding faults *is* the job — an operator who raises a lot of
them is doing it properly.

You can see these figures on screen. **Exporting them to CSV or PDF is a
separate permission** you may not have. That is deliberate: seeing the numbers
and taking them out of the building are different things. Ask your maintenance
manager if you need it.

---

# For the maintenance manager

You have everything a supervisor has, plus you set the system up: machines,
checklists, parts, the holiday calendar, and plant-wide reports.

## Compliance — what the number actually means

Every percentage in this system uses one definition, in one place in the code:

```
compliance = completed ÷ (completed + missed + still outstanding)
```

Over sheets **scheduled inside the window you chose**.

Two things follow, and both matter when you are explaining a number to somebody:

- **Work not yet due is not counted at all.** A daily check due next Friday does
  not drag this week's figure down.
- **A window with nothing due shows no percentage** — not 0%, not 100%. Both of
  those would be a lie. It shows a dash.

A sheet completed late but inside its grace period counts the same as one done
on time. Whether that is right for you is [open question 6 in
`seed-notes.md`](seed-notes.md) — it is cheap to change now and expensive once
you have a year of history.

## Setting up a machine

**Admin → Machines → New machine.**

- **Code** is the important field. It is the short slug in the web address and
  **it is what the QR sticker encodes**. Get it right before you print stickers.
  Changing a code after the stickers are on the machines means reprinting them.
- Pick the location. Add manufacturer, model and asset tag if you have them.
- **Parts** — list the parts that machine normally consumes, in the order they
  should appear on the sheet. This is what pre-fills the Used Parts table.

**Admin → Locations** and **Admin → Parts** work the same way.

Part codes are **text, not numbers** — one of yours is literally `XXX`. Do not
try to renumber them.

## Building a checklist

**Admin → Templates → New template.** One template = one paper form.

| Field | What it does |
|---|---|
| Machine | Which machine this belongs to |
| Name | What operators see, e.g. "MATAN — Daily Maintenance" |
| Work category | Daily, weekly or general — matches the paper form's heading |
| Work description | The description line from the paper form |
| Frequency | How often a sheet is generated: daily, weekly, monthly, on demand |
| Weekly weekday | For weekly checklists — which day of the week |
| Per shift | Tick this and the system generates **two** sheets a day, day and night |
| Requires supervisor sign-off | Almost always yes. See the warning below |
| Grace period hours | How long after the due date before it is marked missed. Default 24 |

Then add the **items** — the numbered checks. Use the item builder to add,
reword, reorder and deactivate them. Attach the **parts list** for the Used
Parts table.

### Versioning — the thing to understand

**Editing the items on a template bumps its version number. Sheets that already
exist keep the wording they were generated with.**

So if you reword check 3 today, last month's completed sheets still show last
month's wording, because that is what the operator actually agreed to when they
signed. This is what makes the records defensible to an auditor. It also means
you can fix a typo without worrying about what it does to history.

### Turning off supervisor sign-off

If you untick **requires supervisor sign-off**, sheets on that template go
**straight to approved** when the operator submits, with no supervisor
signature. They do not sit in the approval queue.

This is deliberate — otherwise they would queue forever with nobody entitled to
clear them — but it means an approved sheet on that template carries no
countersignature. None of the 13 supplied checklists work this way. Think before
you make one that does, and see [open question 8](seed-notes.md).

## Scheduling

Sheets are generated automatically at **05:00 every day** for every active
template due that day. You do not create them by hand.

The generator respects:

- **Working days** — set per site. Sundays are excluded by default.
- **The holiday calendar** — **Admin → Holidays.** Add public holidays here and
  no sheets are generated for them. Mark a holiday as recurring if it falls on
  the same date every year.
- **Frequency and shift split** on each template.

> **Somebody has to update the holiday calendar every January.** If nobody does,
> the plant generates sheets on public holidays, nobody completes them, and they
> are recorded as missed — your compliance figure drops for a reason that has
> nothing to do with maintenance. Decide who owns this.

A second job marks untouched sheets as **missed** once their grace period
expires. Missed sheets are never deleted. They are the record that something was
skipped, and deleting them would make the skip invisible.

Re-running the generator is safe. It cannot create a duplicate sheet.

## QR stickers

**Admin → Machines → QR stickers.**

Filter by location, tick the machines you want, print. Three stickers to a page.
The screen controls do not appear on the printout.

Two things are built in on purpose:

- The **machine code is printed in plain text** under every QR square. When the
  label gets over-sprayed or scratched, that text is what still works.
- The codes are medium error-correction, so a scuffed sticker still scans.

**Confirm the 11 machine codes before you print the first batch.** See [open
question 4](seed-notes.md).

## Reports and exports

**Reports** — four reports over any window, filtered by machine or location:

| Report | Use it for |
|---|---|
| **Checks completed** | **Who did what, and when.** One line per sheet: the operator, the machine, the day it was due, the moment they signed it, and who signed it off. This is the one to hand an auditor asking about a particular day |
| Compliance by machine | Which machines are being skipped. Worst first |
| Missed checks | What was skipped and when. Includes sheets still open past their date, not only ones already flagged |
| Parts usage | What is being consumed. Reads the snapshot names, so a part renamed last month still reports under the name it was consumed as |
| Operator activity | A record of who did what. Not a league table |

Each exports to **CSV** and **PDF**. The CSV opens correctly in Excel on Windows
and carries the report title and date window above the data, so a spreadsheet
found on somebody's desktop in six months still says what it is.

An export always uses the same filters as the screen it came from — including
the machine restriction — so an export can never show more than its requester is
allowed to see.

## The run sheet PDF

Any individual sheet can be printed as a **facsimile of the paper work order** —
same header block, two-column numbered task list, Used Parts table, Notes box,
and both signature blocks with printed name, employee number and timestamp under
each signature. This is what you hand an auditor.

The footer carries a **verification hash**. If somebody hands you a printed
sheet and asks whether it still matches the record, that hash answers it: alter
an approved sheet and the printed hash stops matching.

It is a tamper check, not a legal signature. It proves the paper matches the
database. It does not prove who signed.

> The hash is keyed with the application key (`APP_KEY`). **If you lose
> `APP_KEY`, every previously printed hash stops verifying.** Back it up with
> the database — see [`DEPLOYMENT.md`](DEPLOYMENT.md) §9.

## Who does which checks

Nobody hands a checklist out. An operator walks to a machine, signs in with
their PIN, and **the first tap makes the sheet theirs** — their name is on it
from that moment, and they are the one who signs it.

**If somebody else picks it up part-way through** — a shift changes, a tablet
gets put down — they can carry on. The sheet moves to whoever is working it,
the hand-over is recorded, and the screen tells them they are now the one
signing. You can see the hand-over in the machine's history afterwards. This
is deliberate: blocking it would strand half-finished work, and allowing it
quietly would leave the record naming the wrong person.

**Assigning people to machines** (Admin → Machines → **Operators**) marks a
machine as somebody's usual work. It is **not a permission.** Anyone signed in
at the kiosk can complete any checklist on your site, assigned or not — which
is what lets someone cover an unfamiliar machine at 6am without ringing you.
Removing someone from this list does not lock them out.

What limits people is the **site**, not the assignment. An operator never sees
another site's work.

To find out who did a particular check and exactly when, use the **Checks
completed** report.

## People

Operators need an **employee number** and a **PIN**. They do not need an email
address — most floor operators do not have one.

Supervisors and office users need an **email address or employee number** and a
**password**. They can log in either way.

PINs are stored hashed. **Nobody can look up a forgotten PIN**, including you.
You can only set a new one.

Roles are cumulative:

| Role | Can do |
|---|---|
| **Operator** | Complete and submit sheets on their assigned machines; raise faults |
| **Supervisor** | All of the above, plus approve and reject, and view reports |
| **Maintenance Manager** | All of the above, plus manage machines, templates, parts and holidays, and export data |
| **Admin** | User management, roles, system settings |

Assign each operator the machines they actually work on. That assignment is what
narrows what they see — and it narrows their exports too.

## The kiosk tablets

**Admin → Kiosk Devices.**

Each device is **set up once**. After that it sits on the machine grid and
operators sign in with a PIN only. A device that has not been set up shows
"this … is not set up as a kiosk" and nothing else — so this is the first thing
to do on a new install, and the answer when someone says a new device
"doesn't work".

**A kiosk does not have to be a tablet.** It can be a laptop on a bench, a PC
bolted next to a machine, or a phone. Say which when you add it, because it
changes how you set it up:

| Device type | How you set it up |
|---|---|
| Tablet, phone | **Scan the QR code** shown on your screen with the device's camera |
| Laptop, desktop, other | **Enrol this browser** — turns the browser you are looking at into the kiosk |

The reason for the split is simple: you cannot scan a code with the screen
that is showing it. Both options always appear, so if you pick the wrong type
you just scroll to the other one — nothing is lost.

To set one up: **Add a device**, name it something you could find on a shelf,
choose the type, then **Set up**.

For a tablet or phone, you do not log in on the device, and you should not —
that would mean typing an admin password on something that sits unattended on
a shop floor all shift.

If the list later says a device was **last used from** something different to
what you recorded — a "tablet" used from a laptop — someone probably opened
the setup link on the wrong machine. It is a hint, not an error; nothing stops
working.

The code expires in 15 minutes. Anyone who scans it in that window turns their
own phone into that kiosk. That is not as bad as it sounds — they would still
need an operator's PIN to record anything — but generate a fresh one rather
than saving it or sending it on.

**If a tablet goes missing**, the difference between the buttons matters:

- **Deactivate** — locks it out on its next tap, and you can switch it back on.
- **Un-enrol** — signs it out for good. The entry and its history stay, so the
  replacement tablet can be enrolled under the same name. Use this one when a
  tablet is actually lost or stolen.
- **Delete** — removes the entry completely. Rarely what you want.

Both take effect immediately. There is no waiting period.

One thing to settle with whoever runs your network: **the kiosk address must not
change.** Tablets remember the address they were enrolled on. If the server is
on a DHCP address that moves, every tablet stops working at once. Ask for a
fixed address or a name before you enrol anything.

The two-minute idle drop, backups and the rest are in
[`DEPLOYMENT.md`](DEPLOYMENT.md) §7.

---

## Before you retire the paper

Four things are still outstanding, and the first one is not optional:

1. **Compare all 13 seeded checklists against the printed forms**, item by item.
   The wording came from the written specification, not the PDFs — the 1:1
   mapping is unverified.
2. **Confirm the 11 machine codes** before printing QR stickers.
3. **Test the signature pad** on the actual tablets you will use. It is the one
   part of the system the automated tests cannot cover.
4. **Answer the open questions** in [`seed-notes.md`](seed-notes.md) §E. Several
   are cheap to change now and expensive once you have history.
