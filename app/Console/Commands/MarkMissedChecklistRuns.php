<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * `checklists:mark-missed` — flips untouched pending runs to `missed` once
 * their grace period has expired (BUILD-CONTRACT §7, missed rules).
 *
 * A run is missed when status = pending AND started_at IS NULL AND
 * now > (end of scheduled_for day, plant local time) + template grace hours.
 *
 * Missed runs are NEVER deleted — they ARE the compliance record. This
 * command only ever moves pending → missed, so it is idempotent: a run it
 * has already flipped no longer matches the filter.
 */
class MarkMissedChecklistRuns extends Command
{
    protected $signature = 'checklists:mark-missed
        {--now= : Treat this instant (parseable datetime, plant local) as "now" — for testing}';

    protected $description = 'Mark expired pending checklist runs as missed (idempotent, never deletes).';

    public function handle(): int
    {
        $displayTz = (string) config('app.display_timezone', 'UTC');

        try {
            // --now is interpreted in the plant's timezone; without it we use
            // the true current instant (comparison is instant-vs-instant, so
            // the server timezone is irrelevant).
            $now = $this->option('now') !== null
                ? CarbonImmutable::parse((string) $this->option('now'), $displayTz)
                : CarbonImmutable::now();
        } catch (InvalidFormatException) {
            $this->error('The --now option must be a parseable datetime.');

            return self::FAILURE;
        }

        $todayLocal = $now->setTimezone($displayTz)->toDateString();
        $missed = 0;

        ChecklistRun::query()
            ->where('status', RunStatus::Pending->value)
            ->whereNull('started_at')
            // Cheap SQL pre-filter: a run scheduled after today (plant local)
            // cannot possibly be past end-of-day + a non-negative grace
            // period. The exact per-template deadline is checked in PHP below
            // because it depends on grace_period_hours and the plant timezone.
            ->where('scheduled_for', '<=', $todayLocal)
            ->with('template:id,grace_period_hours')
            // chunkById pages on the primary key, so flipping rows out of the
            // filtered set mid-iteration is safe (no skipped records).
            ->chunkById(200, function (Collection $runs) use ($now, $displayTz, &$missed): void {
                foreach ($runs as $run) {
                    // Null-safe: a soft-deleted template still leaves the run
                    // as a compliance record — fall back to the default grace.
                    $graceHours = $run->template?->grace_period_hours
                        ?? (int) config('checklists.default_grace_period_hours', 24);

                    // End of the scheduled calendar day in PLANT local time,
                    // plus the template's grace period.
                    $deadline = CarbonImmutable::parse($run->scheduled_for->toDateString(), $displayTz)
                        ->endOfDay()
                        ->addHours($graceHours);

                    if ($now->lessThanOrEqualTo($deadline)) {
                        continue;
                    }

                    // System causer: cron has no authenticated user, and we do
                    // not fabricate a "system" User row — fake users pollute
                    // the same audit trail this exists to protect. Instead the
                    // model's automatic activity log is disabled for this save
                    // and ONE explicit entry is written with a null causer and
                    // an unambiguous `causer => system` property.
                    $run->disableLogging();
                    $run->forceFill(['status' => RunStatus::Missed])->save();

                    activity('run')
                        ->performedOn($run)
                        ->event('missed')
                        ->withProperties([
                            'causer' => 'system',
                            'command' => 'checklists:mark-missed',
                            'old' => ['status' => RunStatus::Pending->value],
                            'attributes' => ['status' => RunStatus::Missed->value],
                            'deadline' => $deadline->toIso8601String(),
                        ])
                        ->log('Run marked missed by the scheduler after the grace period expired');

                    $missed++;
                }
            });

        $this->info(sprintf('%d run(s) marked missed.', $missed));

        return self::SUCCESS;
    }
}
