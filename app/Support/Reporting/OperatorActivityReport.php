<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Enums\RunItemStatus;
use App\Models\ChecklistRunItem;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who did the work over the window.
 *
 * Read this as a record of activity, not a performance score. In particular
 * "failures found" is deliberately not framed as a bad number — an operator
 * who reports faults is doing the job the checklist exists for, and a column
 * that made them look worse than one who ticks everything would quietly
 * destroy the record.
 */
final class OperatorActivityReport implements Report
{
    public function key(): string
    {
        return 'operators';
    }

    public function title(): string
    {
        return __('app.reports.operators.title');
    }

    public function description(): string
    {
        return __('app.reports.operators.description');
    }

    public function columns(): array
    {
        return [
            'operator' => __('app.runs.operator'),
            'employee_number' => __('app.kiosk.employee_number'),
            'runs_submitted' => __('app.reports.column.runs_submitted'),
            'items_answered' => __('app.reports.column.items_answered'),
            'failures_found' => __('app.reports.column.failures_found'),
            'issues_raised' => __('app.reports.column.issues_raised'),
            'avg_minutes' => __('app.reports.column.avg_minutes'),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $runs = Compliance::query($filters)->whereNotNull('operator_id');

        // One aggregate per operator over the runs they submitted.
        $perOperator = (clone $runs)
            ->toBase()
            ->selectRaw(
                'operator_id,
                 COUNT(*) as runs_submitted,
                 AVG(CASE
                     WHEN started_at IS NOT NULL AND submitted_at IS NOT NULL
                     THEN TIMESTAMPDIFF(MINUTE, started_at, submitted_at)
                 END) as avg_minutes'
            )
            ->whereIn('status', array_merge(Compliance::COMPLETED, ['rejected']))
            ->groupBy('operator_id')
            ->get()
            ->keyBy('operator_id');

        if ($perOperator->isEmpty()) {
            return collect();
        }

        $operatorIds = $perOperator->keys()->map(fn ($id): int => (int) $id)->all();

        $items = ChecklistRunItem::query()
            ->whereIn('completed_by', $operatorIds)
            ->whereHas('run', fn (Builder $q) => $this->withinWindow($q, $filters))
            ->toBase()
            ->selectRaw(
                'completed_by,
                 COUNT(*) as answered,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed',
                [RunItemStatus::Failed->value]
            )
            ->groupBy('completed_by')
            ->get()
            ->keyBy('completed_by');

        $issues = Issue::query()
            ->whereIn('raised_by', $operatorIds)
            ->whereIn('machine_id', $filters->machineIds())
            ->whereDate('created_at', '>=', $filters->from->toDateString())
            ->whereDate('created_at', '<=', $filters->to->toDateString())
            ->toBase()
            ->selectRaw('raised_by, COUNT(*) as raised')
            ->groupBy('raised_by')
            ->get()
            ->keyBy('raised_by');

        $users = User::query()
            ->whereIn('id', $operatorIds)
            ->get(['id', 'full_name', 'employee_number'])
            ->keyBy('id');

        return collect($operatorIds)
            ->map(function (int $id) use ($perOperator, $items, $issues, $users): array {
                $row = $perOperator[$id];
                $user = $users[$id] ?? null;

                return [
                    'operator' => $user?->full_name ?? __('app.runs.operator').' #'.$id,
                    'employee_number' => $user?->employee_number ?? '—',
                    'runs_submitted' => (int) $row->runs_submitted,
                    'items_answered' => (int) ($items[$id]->answered ?? 0),
                    'failures_found' => (int) ($items[$id]->failed ?? 0),
                    'issues_raised' => (int) ($issues[$id]->raised ?? 0),
                    'avg_minutes' => $row->avg_minutes !== null ? round((float) $row->avg_minutes) : '—',
                ];
            })
            ->sortByDesc('runs_submitted')
            ->values();
    }

    public function totals(ReportFilters $filters): array
    {
        $rows = $this->rows($filters);

        return [
            'operator' => __('app.reports.total'),
            'employee_number' => '',
            'runs_submitted' => $rows->sum('runs_submitted'),
            'items_answered' => $rows->sum('items_answered'),
            'failures_found' => $rows->sum('failures_found'),
            'issues_raised' => $rows->sum('issues_raised'),
            'avg_minutes' => '',
        ];
    }

    private function withinWindow(Builder $query, ReportFilters $filters): Builder
    {
        return $query
            ->whereIn('machine_id', $filters->machineIds())
            ->where('scheduled_for', '>=', $filters->from->toDateString())
            ->where('scheduled_for', '<=', $filters->to->toDateString());
    }
}
