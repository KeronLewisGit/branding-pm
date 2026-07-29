<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use Illuminate\Support\Collection;

/**
 * Every check that was not done: `missed` runs, plus runs still open past
 * their scheduled date. Both are gaps in the record; a report that showed
 * only the first would let an overdue-but-not-yet-flagged run hide.
 */
final class MissedChecksReport implements Report
{
    public function key(): string
    {
        return 'missed';
    }

    public function title(): string
    {
        return __('app.reports.missed.title');
    }

    public function description(): string
    {
        return __('app.reports.missed.description');
    }

    public function columns(): array
    {
        return [
            'date' => __('app.runs.scheduled_for'),
            'machine' => __('app.runs.machine'),
            'template' => __('app.runs.template'),
            'shift' => __('app.runs.shift'),
            'status' => __('app.common.status'),
            'days_late' => __('app.reports.column.days_late'),
            'operator' => __('app.runs.operator'),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $today = now((string) config('app.display_timezone', 'UTC'))->startOfDay();

        return Compliance::query($filters)
            ->where(function ($query) use ($today): void {
                $query->where('status', RunStatus::Missed->value)
                    ->orWhere(fn ($q) => $q
                        ->whereIn('status', Compliance::OUTSTANDING)
                        ->whereDate('scheduled_for', '<', $today->toDateString()));
            })
            ->with(['machine:id,name', 'template:id,name', 'operator:id,full_name'])
            ->orderBy('scheduled_for')
            ->orderBy('machine_id')
            ->get()
            ->map(fn (ChecklistRun $run): array => [
                'date' => $run->scheduled_for->format('j M Y'),
                'machine' => $run->machine?->name ?? '—',
                'template' => $run->template?->name ?? '—',
                'shift' => $run->display_shift,
                'status' => $run->status->label(),
                'days_late' => max(0, (int) $run->scheduled_for->startOfDay()->diffInDays($today)),
                'operator' => $run->operator?->full_name ?? '—',
            ])
            ->values();
    }

    public function totals(ReportFilters $filters): array
    {
        $rows = $this->rows($filters);

        return [
            'date' => __('app.reports.total_missed', ['count' => $rows->count()]),
            'machine' => '',
            'template' => '',
            'shift' => '',
            'status' => '',
            'days_late' => '',
            'operator' => '',
        ];
    }
}
