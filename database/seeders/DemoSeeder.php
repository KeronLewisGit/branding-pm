<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Enums\Shift;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistRunPart;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\ChecklistTemplatePart;
use App\Models\Holiday;
use App\Models\Issue;
use App\Models\Machine;
use App\Models\Part;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo data: one user per role plus 30 days of realistic historical runs so
 * the dashboards and reports are not empty on first boot.
 *
 * NOT called by DatabaseSeeder — production installs must stay clean.
 * Run explicitly:  php artisan db:seed --class=DemoSeeder
 *
 * Requires the master-data seed to have run first (sites, machines,
 * templates, roles). The generated history is deterministic (seeded RNG)
 * and idempotent: re-running creates nothing new thanks to the
 * (template, scheduled_for, shift) unique key.
 *
 * Shape of the history (see the status plan in runStatusFor()):
 * - ~90% of runs completed and approved
 * - Monti Antonio (and the Hiker's one weekly run) missed for a whole week
 *   — the "machine skipped for a week" story the spec describes
 * - a few submitted runs in the last two days, awaiting approval
 * - a couple of rejected runs with a supervisor comment
 * - occasional failed items, each with a linked issue
 * - plausible qty_used on parts (mostly 0, sometimes 1–2), notes on some runs
 * - timestamps respect shifts and working days: no runs on Sundays or
 *   seeded holidays; day-shift work starts 07:05–09:30, night 19:05–21:30
 *   local (America/Port_of_Spain), stored UTC.
 */
class DemoSeeder extends Seeder
{
    private const HISTORY_DAYS = 30;

    /** Machine codes whose runs are missed for the cluster week (offsets 8–14 days ago). */
    private const MISSED_CLUSTER_MACHINES = ['monti-antonio', 'hiker-grommet-machine'];

    private const NOTES_POOL = [
        'Low on rags — requested a new box from stores.',
        'Ink build-up heavier than usual, took extra time to clean.',
        'Machine ran hot during shift, kept an eye on it.',
        'All good, no observations.',
        'Vacuum suction seems weaker than last week.',
        'Used extra IPA on the platen.',
    ];

    private const FAIL_REASONS = [
        'Could not complete — part seized, needs maintenance.',
        'Filter damaged on removal, replacement not in stock.',
        'Access panel bolt stripped, could not open.',
        'Sensor still dirty after cleaning — may be faulty.',
    ];

    /** @var array<string, int> */
    private array $summary = [
        'runs' => 0,
        'approved' => 0,
        'submitted' => 0,
        'rejected' => 0,
        'missed' => 0,
        'failed_items' => 0,
        'issues' => 0,
    ];

    public function run(): void
    {
        if (Machine::count() === 0 || ChecklistTemplate::count() === 0) {
            $this->command?->error(
                'DemoSeeder: master data missing. Run `php artisan db:seed` first.'
            );

            return;
        }

        // Deterministic "randomness" — the same demo dataset every install.
        mt_srand(20260401);

        $site = Site::where('code', 'BR23')->firstOrFail();
        $timezone = $site->timezone ?? 'America/Port_of_Spain';

        [$operatorPin, $operatorEmail, $supervisor, $manager] = $this->seedUsers($site);
        $this->assignMachines($operatorPin, $operatorEmail);

        $holidays = Holiday::all();
        $templates = ChecklistTemplate::where('is_active', true)->get();
        // location is read per run below to pick the operator, so eager-load it:
        // Model::preventLazyLoading() is enabled outside production and would
        // otherwise throw on the first access.
        $machines = Machine::with('location')->get()->keyBy('id');
        $partsById = Part::all()->keyBy('id');

        $today = Carbon::today($timezone);

        for ($offset = self::HISTORY_DAYS; $offset >= 1; $offset--) {
            $date = $today->copy()->subDays($offset);

            if (! $this->isWorkingDay($date, $site->working_days, $holidays)) {
                continue;
            }

            foreach ($templates as $template) {
                if (! $this->isDueOn($template, $date)) {
                    continue;
                }

                $machine = $machines[$template->machine_id];
                $shifts = $template->per_shift ? [Shift::Day, Shift::Night] : [Shift::All];

                foreach ($shifts as $shift) {
                    $this->createRun(
                        $template,
                        $machine,
                        $date,
                        $shift,
                        $offset,
                        $timezone,
                        $machine->location->name === 'Digital Print' ? $operatorPin : $operatorEmail,
                        $supervisor,
                        $manager,
                        $partsById,
                    );
                }
            }
        }

        $this->command?->info(sprintf(
            'DemoSeeder: %d runs over the last %d days (%d approved, %d submitted, %d rejected, %d missed), %d failed items, %d issues. Demo users: OP-1001 (PIN 4321, no email), OP-1002 / operator@example.com, supervisor@example.com, manager@example.com, qa@example.com — password "password".',
            $this->summary['runs'],
            self::HISTORY_DAYS,
            $this->summary['approved'],
            $this->summary['submitted'],
            $this->summary['rejected'],
            $this->summary['missed'],
            $this->summary['failed_items'],
            $this->summary['issues'],
        ));
    }

    /**
     * One demo user per role. Operator email/password mix is deliberate —
     * the confirmed answer was "mixed": one floor operator is PIN-only with
     * no email and no password, the other has a company email as well.
     *
     * @return array{0: User, 1: User, 2: User, 3: User}
     */
    private function seedUsers(Site $site): array
    {
        // The User model's 'hashed' cast hashes password and pin on assignment.
        $operatorPin = User::firstOrCreate(
            ['employee_number' => 'OP-1001'],
            [
                'full_name' => 'Darnell Joseph',
                'email' => null,       // PIN-only floor operator — no company email
                'password' => null,    // and therefore no password login
                'pin' => '4321',
                'pin_set_at' => now(),
                'is_active' => true,
                'default_site_id' => $site->id,
            ],
        );

        $operatorEmail = User::firstOrCreate(
            ['employee_number' => 'OP-1002'],
            [
                'full_name' => 'Alicia Ramkissoon',
                'email' => 'operator@example.com', // operator WITH email — "mixed"
                'password' => 'password',
                'pin' => '2468',
                'pin_set_at' => now(),
                'is_active' => true,
                'default_site_id' => $site->id,
            ],
        );

        $supervisor = User::firstOrCreate(
            ['employee_number' => 'SUP-2001'],
            [
                'full_name' => 'Marcus Baptiste',
                'email' => 'supervisor@example.com',
                'password' => 'password',
                'pin' => null,
                'is_active' => true,
                'default_site_id' => $site->id,
            ],
        );

        $manager = User::firstOrCreate(
            ['employee_number' => 'MM-3001'],
            [
                'full_name' => 'Sherry-Ann Gopaul',
                'email' => 'manager@example.com',
                'password' => 'password',
                'pin' => null,
                'is_active' => true,
                'default_site_id' => $site->id,
            ],
        );

        // Quality Assurance is a standalone role, not a rung on the ladder —
        // this person verifies work they can neither do nor approve.
        $qa = User::firstOrCreate(
            ['employee_number' => 'QA-4001'],
            [
                'full_name' => 'Anisa Ramkissoon',
                'email' => 'qa@example.com',
                'password' => 'password',
                'pin' => null,
                'is_active' => true,
                'default_site_id' => $site->id,
            ],
        );

        foreach ([[$operatorPin, 'operator'], [$operatorEmail, 'operator'], [$supervisor, 'supervisor'], [$manager, 'maintenance_manager'], [$qa, 'quality_assurance']] as [$user, $role]) {
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        return [$operatorPin, $operatorEmail, $supervisor, $manager];
    }

    /**
     * user_machine assignments: the PIN-only operator owns Digital Print,
     * the second operator owns everything else.
     */
    private function assignMachines(User $operatorPin, User $operatorEmail): void
    {
        $digitalPrint = Machine::whereHas('location', fn ($q) => $q->where('name', 'Digital Print'))
            ->pluck('id');
        $rest = Machine::whereNotIn('id', $digitalPrint)->pluck('id');

        $operatorPin->machines()->syncWithoutDetaching($digitalPrint->all());
        $operatorEmail->machines()->syncWithoutDetaching($rest->all());
    }

    /**
     * @param  Collection<int, Holiday>  $holidays
     * @param  list<int>  $workingDays
     */
    private function isWorkingDay(Carbon $date, array $workingDays, Collection $holidays): bool
    {
        if (! in_array($date->isoWeekday(), $workingDays, true)) {
            return false; // Sunday (working_days = Mon–Sat)
        }

        return ! $holidays->contains(function (Holiday $holiday) use ($date): bool {
            $holidayDate = Carbon::parse((string) $holiday->getRawOriginal('date'));

            if ($holiday->is_recurring) {
                return $holidayDate->month === $date->month && $holidayDate->day === $date->day;
            }

            return $holidayDate->isSameDay($date);
        });
    }

    private function isDueOn(ChecklistTemplate $template, Carbon $date): bool
    {
        return match ($template->frequency->value) {
            'daily' => true,
            'weekly' => $date->isoWeekday() === (int) $template->weekly_weekday,
            'monthly' => $date->day === (int) $template->monthly_day,
            default => false, // on_demand — never auto-generated
        };
    }

    /**
     * The status plan:
     * - cluster machines, 8–14 days ago  → missed (the skipped-week story)
     * - MATAN daily day-shift, 6 & 13 days ago → rejected (a couple)
     * - last 2 days → 50/50 submitted / approved
     * - everything else → approved  (~90% of all runs end up approved)
     */
    private function runStatusFor(ChecklistTemplate $template, Machine $machine, Shift $shift, int $offset): RunStatus
    {
        if ($offset >= 8 && $offset <= 14 && in_array($machine->code, self::MISSED_CLUSTER_MACHINES, true)) {
            return RunStatus::Missed;
        }

        if ($machine->code === 'matan'
            && $template->work_category->value === 'daily'
            && $shift === Shift::Day
            && in_array($offset, [6, 13], true)) {
            return RunStatus::Rejected;
        }

        if ($offset <= 2 && mt_rand(1, 100) <= 50) {
            return RunStatus::Submitted;
        }

        return RunStatus::Approved;
    }

    /**
     * @param  Collection<int, Part>  $partsById
     */
    private function createRun(
        ChecklistTemplate $template,
        Machine $machine,
        Carbon $date,
        Shift $shift,
        int $offset,
        string $timezone,
        User $operator,
        User $supervisor,
        User $manager,
        Collection $partsById,
    ): void {
        $status = $this->runStatusFor($template, $machine, $shift, $offset);
        $worked = $status !== RunStatus::Missed;

        // Work starts 5–150 minutes into the shift: day 07:00, night 19:00
        // local time. Night shift is booked against the date it starts
        // (seed-notes E1). Stored UTC.
        $shiftStartHour = $shift === Shift::Night ? 19 : 7;
        $startedAt = Carbon::parse($date->toDateString(), $timezone)
            ->setTime($shiftStartHour, 0)
            ->addMinutes(mt_rand(5, 150))
            ->utc();
        $durationMinutes = mt_rand(12, 45);
        $submittedAt = $startedAt->copy()->addMinutes($durationMinutes);

        $run = ChecklistRun::firstOrCreate(
            [
                'checklist_template_id' => $template->id,
                'scheduled_for' => $date->toDateString(),
                'shift' => $shift,
            ],
            [
                'machine_id' => $machine->id,
                'template_version' => $template->version,
                'status' => $status,
                'started_at' => $worked ? $startedAt : null,
                'submitted_at' => $worked ? $submittedAt : null,
                'operator_id' => $worked ? $operator->id : null,
                // operator_signature_path / operator_signed_at stay null:
                // signature capture lands in milestone 5. Same for the
                // supervisor signature block below.
                'operator_signature_path' => null,
                'operator_signed_at' => null,
                'supervisor_id' => in_array($status, [RunStatus::Approved, RunStatus::Rejected], true) ? $supervisor->id : null,
                'supervisor_signature_path' => null,
                'supervisor_signed_at' => null,
                'supervisor_comment' => match (true) {
                    $status === RunStatus::Rejected => 'Platen still had ink residue — clean again and resubmit.',
                    $status === RunStatus::Approved && mt_rand(1, 100) <= 10 => 'All good.',
                    default => null,
                },
                'notes' => $worked && mt_rand(1, 100) <= 15
                    ? self::NOTES_POOL[mt_rand(0, count(self::NOTES_POOL) - 1)]
                    : null,
                'downtime_minutes' => null,
            ],
        );

        if (! $run->wasRecentlyCreated) {
            return; // already seeded — keep the run idempotent
        }

        $this->summary['runs']++;
        $this->summary[$status->value] = ($this->summary[$status->value] ?? 0) + 1;

        $this->createRunItems($run, $template, $status, $startedAt, $durationMinutes, $operator, $manager, $offset);
        $this->createRunParts($run, $template, $worked, $partsById);
    }

    private function createRunItems(
        ChecklistRun $run,
        ChecklistTemplate $template,
        RunStatus $status,
        Carbon $startedAt,
        int $durationMinutes,
        User $operator,
        User $manager,
        int $offset,
    ): void {
        $items = ChecklistTemplateItem::where('checklist_template_id', $template->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $worked = $status !== RunStatus::Missed;

        // ~4% of worked runs contain one failed item (with a linked issue).
        $failedIndex = $worked && $items->count() > 0 && mt_rand(1, 100) <= 4
            ? mt_rand(0, $items->count() - 1)
            : null;

        foreach ($items as $index => $item) {
            $itemStatus = RunItemStatus::Pending;
            $failReason = null;

            if ($worked) {
                if ($failedIndex === $index) {
                    $itemStatus = RunItemStatus::Failed;
                    $failReason = self::FAIL_REASONS[mt_rand(0, count(self::FAIL_REASONS) - 1)];
                } elseif (mt_rand(1, 100) <= 2) {
                    $itemStatus = RunItemStatus::NotApplicable;
                } else {
                    $itemStatus = RunItemStatus::Done;
                }
            }

            // Responses spread evenly across the run's duration.
            $completedAt = $worked
                ? $startedAt->copy()->addMinutes(
                    (int) floor($durationMinutes * ($index + 1) / max($items->count(), 1))
                )
                : null;

            $runItem = ChecklistRunItem::create([
                'checklist_run_id' => $run->id,
                'checklist_template_item_id' => $item->id,
                'sort_order' => $item->sort_order,
                'description' => $item->description, // snapshot
                'response_type' => $item->response_type,
                'is_required' => $item->is_required,
                'status' => $itemStatus,
                'value_numeric' => null,
                'value_text' => null,
                'fail_reason' => $failReason,
                'completed_at' => $completedAt,
                'completed_by' => $worked ? $operator->id : null,
            ]);

            if ($itemStatus === RunItemStatus::Failed) {
                $this->summary['failed_items']++;
                $this->createIssue($run, $runItem, $completedAt, $operator, $manager, $offset);
            }
        }
    }

    private function createIssue(
        ChecklistRun $run,
        ChecklistRunItem $runItem,
        ?Carbon $completedAt,
        User $operator,
        User $manager,
        int $offset,
    ): void {
        $resolved = $offset > 7; // older issues got dealt with, recent ones are open

        Issue::create([
            'checklist_run_id' => $run->id,
            'checklist_run_item_id' => $runItem->id,
            'machine_id' => $run->machine_id,
            'raised_by' => $operator->id,
            'severity' => mt_rand(1, 100) <= 20 ? IssueSeverity::High : IssueSeverity::Medium,
            'description' => 'Failed during PM check "'.$runItem->description.'": '.$runItem->fail_reason,
            'status' => $resolved ? IssueStatus::Resolved : IssueStatus::Open,
            'assigned_to' => $manager->id,
            'resolved_at' => $resolved ? $completedAt?->copy()->addDays(2) : null,
            'resolution_notes' => $resolved ? 'Repaired and verified on next PM run.' : null,
        ]);

        $this->summary['issues']++;
    }

    /**
     * @param  Collection<int, Part>  $partsById
     */
    private function createRunParts(
        ChecklistRun $run,
        ChecklistTemplate $template,
        bool $worked,
        Collection $partsById,
    ): void {
        $templateParts = ChecklistTemplatePart::where('checklist_template_id', $template->id)
            ->orderBy('sort_order')
            ->get();

        foreach ($templateParts as $templatePart) {
            $part = $partsById[$templatePart->part_id];

            // Mostly nothing consumed; sometimes 1–2 of a consumable.
            $qty = $worked && mt_rand(1, 100) <= 35 ? mt_rand(1, 2) : 0;

            ChecklistRunPart::create([
                'checklist_run_id' => $run->id,
                'part_id' => $part->id,
                'part_code_snapshot' => $part->part_code, // string — 'XXX' is real
                'part_name_snapshot' => $part->name,
                'sort_order' => $templatePart->sort_order,
                'qty_used' => $qty,
            ]);
        }
    }
}
