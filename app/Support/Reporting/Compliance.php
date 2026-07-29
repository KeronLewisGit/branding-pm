<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single definition of "compliance" (milestone 7).
 *
 * Everything that quotes a percentage — the dashboard tiles, the heat-map,
 * the compliance report, the CSV — goes through here, so the number cannot
 * mean one thing on screen and another in an export.
 *
 * The definition, stated plainly because an auditor will ask:
 *
 *   completed   = submitted or approved       (the work was done and recorded)
 *   missed      = status `missed`             (the grace period expired untouched)
 *   outstanding = pending / in progress / rejected, scheduled ON OR BEFORE the
 *                 end of the window — still owed, and now late
 *   compliance% = completed / (completed + missed + outstanding) × 100
 *
 * Runs scheduled after the window are not counted at all: work that is not
 * yet due is neither compliant nor non-compliant. A window with nothing due
 * has no percentage (null) rather than 0% or 100%, both of which would lie.
 *
 * Note what is NOT here: lateness. A run completed inside its grace period
 * and one completed on the dot both count as completed — see seed-notes §E6,
 * which is an open question for the maintenance manager.
 */
final class Compliance
{
    /** @var list<string> */
    public const COMPLETED = [
        'submitted',
        'approved',
    ];

    /** @var list<string> */
    public const OUTSTANDING = [
        'pending',
        'in_progress',
        'rejected',
    ];

    /**
     * Base query: runs in scope, scheduled inside the window.
     */
    public static function query(ReportFilters $filters): Builder
    {
        return ChecklistRun::query()
            ->whereIn('machine_id', $filters->machineIds())
            ->whereDate('scheduled_for', '>=', $filters->from->toDateString())
            ->whereDate('scheduled_for', '<=', $filters->to->toDateString());
    }

    /**
     * Counts and percentage for any run query.
     *
     * @return array{completed: int, missed: int, outstanding: int, due: int, percentage: float|null}
     */
    public static function summarise(Builder $runs): array
    {
        $counts = (clone $runs)
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $completed = 0;
        $outstanding = 0;

        foreach (self::COMPLETED as $status) {
            $completed += (int) ($counts[$status] ?? 0);
        }

        foreach (self::OUTSTANDING as $status) {
            $outstanding += (int) ($counts[$status] ?? 0);
        }

        $missed = (int) ($counts[RunStatus::Missed->value] ?? 0);
        $due = $completed + $missed + $outstanding;

        return [
            'completed' => $completed,
            'missed' => $missed,
            'outstanding' => $outstanding,
            'due' => $due,
            'percentage' => $due > 0 ? round($completed / $due * 100, 1) : null,
        ];
    }

    /**
     * Percentage formatted for display. A window with nothing due prints an
     * em dash, never "0%".
     */
    public static function format(?float $percentage): string
    {
        return $percentage === null ? '—' : number_format($percentage, 1).'%';
    }
}
