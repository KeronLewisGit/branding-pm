<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\ChecklistRunPart;
use Illuminate\Support\Collection;

/**
 * What was consumed, by part, over the window.
 *
 * Reads the SNAPSHOT columns on `checklist_run_parts`, not the parts
 * catalogue: a part renamed or re-coded last month must still report under
 * the name it was consumed as, or the historical totals rewrite themselves
 * every time somebody tidies the catalogue.
 */
final class PartsUsageReport implements Report
{
    public function key(): string
    {
        return 'parts';
    }

    public function title(): string
    {
        return __('app.reports.parts.title');
    }

    public function description(): string
    {
        return __('app.reports.parts.description');
    }

    public function columns(): array
    {
        return [
            'part_code' => __('app.parts.part_code'),
            'part_name' => __('app.parts.part'),
            'qty_used' => __('app.runs.qty_used'),
            'runs' => __('app.reports.column.runs'),
            'machines' => __('app.reports.column.machines'),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        return ChecklistRunPart::query()
            ->join('checklist_runs', 'checklist_runs.id', '=', 'checklist_run_parts.checklist_run_id')
            ->whereIn('checklist_runs.machine_id', $filters->machineIds())
            ->where('checklist_runs.scheduled_for', '>=', $filters->from->toDateString())
            ->where('checklist_runs.scheduled_for', '<=', $filters->to->toDateString())
            ->where('checklist_run_parts.qty_used', '>', 0)
            ->groupBy('checklist_run_parts.part_code_snapshot', 'checklist_run_parts.part_name_snapshot')
            ->selectRaw(
                'checklist_run_parts.part_code_snapshot as part_code,
                 checklist_run_parts.part_name_snapshot as part_name,
                 SUM(checklist_run_parts.qty_used) as qty_used,
                 COUNT(DISTINCT checklist_run_parts.checklist_run_id) as runs,
                 COUNT(DISTINCT checklist_runs.machine_id) as machines'
            )
            ->orderByDesc('qty_used')
            ->orderBy('part_code')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                // part_code is a string by contract — 'XXX' is a real code.
                'part_code' => (string) $row->part_code,
                'part_name' => (string) $row->part_name,
                'qty_used' => (float) $row->qty_used,
                'runs' => (int) $row->runs,
                'machines' => (int) $row->machines,
            ])
            ->values();
    }

    public function totals(ReportFilters $filters): array
    {
        $rows = $this->rows($filters);

        return [
            'part_code' => __('app.reports.total'),
            'part_name' => '',
            'qty_used' => $rows->sum('qty_used'),
            'runs' => '',
            'machines' => '',
        ];
    }
}
