<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\Machine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Compliance by machine over the window: how much was due, how much got done,
 * and what is missing. The row order is worst-first — the point of the report
 * is the bottom of the table, so it is put at the top.
 */
final class ComplianceReport implements Report
{
    public function key(): string
    {
        return 'compliance';
    }

    public function title(): string
    {
        return __('app.reports.compliance.title');
    }

    public function description(): string
    {
        return __('app.reports.compliance.description');
    }

    public function columns(): array
    {
        return [
            'machine' => __('app.runs.machine'),
            'location' => __('app.locations.location'),
            'due' => __('app.reports.column.due'),
            'completed' => __('app.reports.column.completed'),
            'missed' => __('app.reports.column.missed'),
            'outstanding' => __('app.reports.column.outstanding'),
            'compliance' => __('app.reports.column.compliance'),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $machines = Machine::query()
            ->whereIn('id', $filters->machineIds())
            ->with('location:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'location_id']);

        return $machines
            ->map(function (Machine $machine) use ($filters): array {
                $summary = Compliance::summarise(
                    Compliance::query($filters)->where('machine_id', $machine->id)
                );

                return [
                    'machine' => $machine->name,
                    'location' => $machine->location?->name ?? '—',
                    'due' => $summary['due'],
                    'completed' => $summary['completed'],
                    'missed' => $summary['missed'],
                    'outstanding' => $summary['outstanding'],
                    'compliance' => Compliance::format($summary['percentage']),
                    // Sort key only — stripped before display.
                    '_sort' => $summary['percentage'] ?? 101.0,
                ];
            })
            // Worst compliance first; machines with nothing due sort last.
            ->sortBy('_sort')
            ->map(function (array $row): array {
                unset($row['_sort']);

                return $row;
            })
            ->values();
    }

    public function totals(ReportFilters $filters): array
    {
        $summary = Compliance::summarise(Compliance::query($filters));

        return [
            'machine' => __('app.reports.total_all_machines'),
            'location' => '',
            'due' => $summary['due'],
            'completed' => $summary['completed'],
            'missed' => $summary['missed'],
            'outstanding' => $summary['outstanding'],
            'compliance' => Compliance::format($summary['percentage']),
        ];
    }

    /**
     * Compliance per ISO week, for the dashboard trend. Kept here so the
     * weekly figure is the same calculation as the table above it.
     *
     * @return Collection<int, array{week: string, percentage: float|null, due: int}>
     */
    public function byWeek(ReportFilters $filters): Collection
    {
        $weeks = collect();
        $cursor = $filters->from->copy()->startOfWeek();

        while ($cursor->lessThanOrEqualTo($filters->to)) {
            $end = $cursor->copy()->endOfWeek();

            $summary = Compliance::summarise(
                Compliance::query($filters)
                    ->whereDate('scheduled_for', '>=', $cursor->toDateString())
                    ->whereDate('scheduled_for', '<=', $end->toDateString())
            );

            $weeks->push([
                'week' => $cursor->format('j M'),
                'percentage' => $summary['percentage'],
                'due' => $summary['due'],
            ]);

            $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * Query helper for callers that want to narrow further before summarising.
     */
    public function scopedRuns(ReportFilters $filters): Builder
    {
        return Compliance::query($filters);
    }
}
