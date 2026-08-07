<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Enums\RunStatus;
use Illuminate\Support\Collection;

/**
 * Every check in the window, one row per sheet: who did it and when.
 *
 * The other four reports are aggregates — a percentage per machine, a count
 * per operator. None of them answer the question an auditor actually asks
 * standing at a filing cabinet: *who signed this one, and on what day?*
 *
 * Deliberately one row per **run**, not per item. A run is what the paper
 * work order was, what carries a signature, and what the verification hash
 * covers. Per-item attribution exists in `checklist_run_items.completed_by`
 * and is shown on the run review screen; putting it here would multiply the
 * export by the number of items on a sheet to answer a narrower question.
 *
 * Two dates, because they are not the same thing and conflating them hides
 * lateness: `scheduled_for` is the day the work was due, `submitted_at` the
 * moment the operator signed it off.
 */
final class ChecksCompletedReport implements Report
{
    public function key(): string
    {
        return 'checks';
    }

    public function title(): string
    {
        return __('app.reports.checks.title');
    }

    public function description(): string
    {
        return __('app.reports.checks.description');
    }

    public function columns(): array
    {
        return [
            'operator' => __('app.runs.operator'),
            'employee_number' => __('app.kiosk.employee_number'),
            'machine' => __('app.machines.machine'),
            'checklist' => __('app.reports.column.checklist'),
            'shift' => __('app.runs.shift'),
            'scheduled_for' => __('app.reports.column.scheduled_for'),
            'completed_at' => __('app.reports.column.completed_at'),
            'status' => __('app.common.status'),
            'approved_by' => __('app.reports.column.approved_by'),
            'verified_by' => __('app.reports.column.verified_by'),
        ];
    }

    public function rows(ReportFilters $filters): Collection
    {
        $tz = (string) config('app.display_timezone', 'UTC');

        $runs = Compliance::query($filters)
            // Work that was actually done by somebody, and finished. A sheet
            // still in progress has an operator but no completion date, and a
            // row here with a dash in the date column answers no question —
            // outstanding and missed work is the missed-checks report's job.
            ->whereNotNull('operator_id')
            ->whereNotNull('submitted_at')
            ->with([
                'operator:id,full_name,employee_number',
                'supervisor:id,full_name',
                'qaVerifiedBy:id,full_name',
                'machine:id,name',
                'template:id,name',
            ])
            // Most recent work first: the usual reason for opening this
            // report is "what happened yesterday".
            ->orderByDesc('scheduled_for')
            ->orderByDesc('submitted_at')
            ->get();

        return $runs->map(fn ($run): array => [
            'operator' => $run->operator?->full_name ?? '—',
            'employee_number' => $run->operator?->employee_number ?? '—',
            'machine' => $run->machine?->name ?? '—',
            'checklist' => $run->template?->name ?? '—',
            'shift' => $run->shift->label(),
            // NOT timezone-converted. `scheduled_for` is a calendar date cast
            // to midnight UTC; shifting it into a UTC-4 zone renders every
            // run a day early — a report claiming the 6th for work scheduled
            // on the 7th is worse than no report.
            'scheduled_for' => $run->scheduled_for?->format('d M Y') ?? '—',
            // Signed, not merely opened. A sheet started on the late shift and
            // signed after midnight is stamped when it was signed.
            'completed_at' => $run->submitted_at?->timezone($tz)->format('d M Y, g:i A') ?? '—',
            'status' => $run->status->label(),
            'approved_by' => $run->supervisor?->full_name ?? '—',
            // Quality Assurance is a separate act from approval, so it gets
            // its own column rather than being folded into the status.
            'verified_by' => $run->qa_verified_at !== null
                ? ($run->qaVerifiedBy?->full_name ?? __('app.qa.verified'))
                : '—',
        ])->values();
    }

    public function totals(ReportFilters $filters): array
    {
        $runs = Compliance::query($filters)
            ->whereNotNull('operator_id')
            ->whereNotNull('submitted_at');

        $total = (clone $runs)->count();
        $approved = (clone $runs)->where('status', RunStatus::Approved)->count();
        $verified = (clone $runs)->whereNotNull('qa_verified_at')->count();

        return [
            'operator' => __('app.reports.column.total_checks'),
            'employee_number' => '',
            'machine' => '',
            'checklist' => '',
            'shift' => '',
            'scheduled_for' => '',
            'completed_at' => (string) $total,
            'status' => __('app.reports.checks.approved_count', ['count' => $approved]),
            'approved_by' => '',
            'verified_by' => __('app.qa.verified_count', ['count' => $verified]),
        ];
    }
}
