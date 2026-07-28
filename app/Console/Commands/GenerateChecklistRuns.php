<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Enums\Shift;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistRunPart;
use App\Models\ChecklistTemplate;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * `checklists:generate` — creates pending checklist runs for every active
 * template due on the target date (BUILD-CONTRACT §7, rules 1–7).
 *
 * Idempotent: `firstOrCreate` on (checklist_template_id, scheduled_for, shift),
 * backed by the unique index `runs_template_date_shift_unique`. Re-running the
 * command — or two schedulers racing — can never double-create a run.
 */
class GenerateChecklistRuns extends Command
{
    protected $signature = 'checklists:generate
        {--date= : Generate for this date (Y-m-d, plant local) instead of today}
        {--dry-run : Report what would be created without writing anything}';

    protected $description = 'Create pending checklist runs for every active template due on the given date (idempotent).';

    public function handle(): int
    {
        // "Today" MUST be evaluated in the plant's display timezone, never UTC.
        // The scheduler fires at 05:00 local; in America/Port_of_Spain (UTC-4)
        // that is 09:00 UTC — same calendar day — but any run near local
        // midnight (or a manual re-run) would otherwise generate runs for the
        // WRONG day, and the unique index would then block the right day's
        // generation from ever happening.
        $displayTz = (string) config('app.display_timezone', 'UTC');

        try {
            $date = $this->option('date') !== null
                ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->option('date'), $displayTz)
                : CarbonImmutable::now($displayTz)->startOfDay();
        } catch (InvalidFormatException) {
            $this->error('The --date option must be a valid date in Y-m-d format.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // 13 templates in production — loading them all with their children in
        // one pass is deliberate; no chunking needed at this scale. Parts are
        // loaded withTrashed so a soft-deleted part still gets snapshotted —
        // the template row referencing it is the operative record.
        $templates = ChecklistTemplate::query()
            ->with([
                'machine.location.site',
                'activeItems',
                'templateParts.part' => fn ($query) => $query->withTrashed(),
            ])
            ->get();

        $created = 0;
        $skippedExisting = 0;
        $skippedNonWorkingDay = 0;
        $skippedInactive = 0;
        $skippedNotDue = 0;

        /** @var array<int, bool> $workingDayCache one holiday lookup per site, not per template */
        $workingDayCache = [];

        foreach ($templates as $template) {
            $machine = $template->machine;

            // Rule 1 — skip inactive templates and inactive (or missing /
            // soft-deleted) machines. A machine without a resolvable site is
            // a data problem and is treated the same way rather than crashing.
            $site = $machine?->location?->site;

            if (! $template->is_active || $machine === null || ! $machine->is_active || $site === null) {
                $skippedInactive++;

                continue;
            }

            // Rule 2 — working days + holidays (site-wide null rows and
            // recurring month+day matches are handled in Site::isWorkingDay).
            $workingDayCache[$site->id] ??= $site->isWorkingDay($date);

            if (! $workingDayCache[$site->id]) {
                $skippedNonWorkingDay++;

                continue;
            }

            // Rule 3 — frequency (daily every working day, weekly on
            // weekly_weekday, monthly on monthly_day, on_demand never).
            if (! $template->isDueOn($date)) {
                $skippedNotDue++;

                continue;
            }

            // Rule 4 — shift split.
            $shifts = $template->per_shift ? [Shift::Day, Shift::Night] : [Shift::All];

            foreach ($shifts as $shift) {
                $alreadyExists = ChecklistRun::query()
                    ->where('checklist_template_id', $template->id)
                    ->whereDate('scheduled_for', $date->toDateString())
                    ->where('shift', $shift->value)
                    ->exists();

                if ($alreadyExists) {
                    $skippedExisting++;

                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[dry-run] would create: %s / %s / %s',
                        $template->name,
                        $date->toDateString(),
                        $shift->value,
                    ));
                    $created++;

                    continue;
                }

                try {
                    // Rules 5 + 6 — run + snapshots, atomically. If the
                    // transaction fails, no half-populated run is left behind.
                    $wasCreated = DB::transaction(
                        fn (): bool => $this->createRun($template, $date, $shift)
                    );

                    $wasCreated ? $created++ : $skippedExisting++;
                } catch (QueryException $exception) {
                    // Two schedulers racing: both passed the exists() check,
                    // one hit runs_template_date_shift_unique. That is the
                    // index doing its job — "skipped, already exists", not
                    // an error. Anything else is a real failure: rethrow.
                    if (($exception->errorInfo[0] ?? null) !== '23000') {
                        throw $exception;
                    }

                    $skippedExisting++;
                }
            }
        }

        // Rule 7 — report.
        if ($dryRun) {
            $this->warn('Dry run — nothing was written.');
        }

        $this->info(sprintf('Checklist run generation for %s', $date->toDateString()));
        $this->table(['Result', 'Count'], [
            ['created', $created],
            ['skipped-existing', $skippedExisting],
            ['skipped-non-working-day', $skippedNonWorkingDay],
            ['skipped-inactive', $skippedInactive],
            ['skipped-not-due-today', $skippedNotDue],
        ]);

        return self::SUCCESS;
    }

    /**
     * Create one run plus its snapshotted children. Returns false when the
     * run already existed (firstOrCreate found it).
     */
    private function createRun(ChecklistTemplate $template, CarbonImmutable $date, Shift $shift): bool
    {
        $run = ChecklistRun::query()->firstOrCreate(
            [
                'checklist_template_id' => $template->id,
                'scheduled_for' => $date->toDateString(),
                'shift' => $shift->value,
            ],
            [
                'machine_id' => $template->machine_id,
                'template_version' => $template->version,
                'status' => RunStatus::Pending->value,
            ],
        );

        if (! $run->wasRecentlyCreated) {
            return false;
        }

        $now = now();

        // Rule 6 — snapshot every ACTIVE template item. description,
        // response_type, is_required and sort_order are all copied so a run
        // completed in March is judged by March's form, whatever the template
        // becomes later. Bulk insert — one statement, not a loop of saves.
        $items = $template->activeItems
            ->map(fn ($item): array => [
                'checklist_run_id' => $run->id,
                'checklist_template_item_id' => $item->id,
                'sort_order' => $item->sort_order,
                'description' => $item->description,
                'response_type' => $item->response_type->value,
                'is_required' => $item->is_required,
                'status' => RunItemStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($items !== []) {
            ChecklistRunItem::query()->insert($items);
        }

        // Every template part, code + name snapshotted, qty_used = 0.
        $parts = $template->templateParts
            ->map(fn ($templatePart): array => [
                'checklist_run_id' => $run->id,
                'part_id' => $templatePart->part_id,
                'part_code_snapshot' => $templatePart->part?->part_code ?? '',
                'part_name_snapshot' => $templatePart->part?->name ?? '',
                'sort_order' => $templatePart->sort_order,
                'qty_used' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($parts !== []) {
            ChecklistRunPart::query()->insert($parts);
        }

        return true;
    }
}
