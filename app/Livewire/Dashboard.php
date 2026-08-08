<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\IssueSeverity;
use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use App\Models\Issue;
use App\Models\Machine;
use App\Support\MachineScope;
use App\Support\Reporting\Compliance;
use App\Support\Reporting\ComplianceReport;
use App\Support\Reporting\PartsUsageReport;
use App\Support\Reporting\ReportFilters;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard (milestone 7) — route `dashboard`, replacing the placeholder.
 *
 * Every number here comes from the same Compliance/report classes the
 * reports screen and the exports use. A dashboard that computed its own
 * version of "compliance" would eventually disagree with the report a
 * manager exports, and then neither could be trusted.
 *
 * Query budget is deliberate: the heat-map and the per-machine table are two
 * grouped aggregates, not a query per machine per day.
 */
#[Layout('layouts::app')]
class Dashboard extends Component
{
    /** Window in days for the compliance figures. */
    #[Url]
    public int $days = 30;

    public function mount(): mixed
    {
        // `/` redirects here, so this is where a password login lands. An
        // operator has no `report.view` and must not be met with a 403 on
        // their own front door — send them to the work instead.
        if (! Auth::user()?->can('report.view')) {
            return $this->redirectRoute('runs.index', navigate: false);
        }

        // Anything outside this set is somebody editing the URL.
        if (! in_array($this->days, [7, 30, 90], true)) {
            $this->days = 30;
        }

        return null;
    }

    public function render(): View
    {
        $user = Auth::user();
        $timezone = (string) config('app.display_timezone', 'UTC');
        $today = Carbon::today($timezone);

        $filters = ReportFilters::make(
            user: $user,
            from: $today->copy()->subDays($this->days - 1)->toDateString(),
            to: $today->toDateString(),
        );

        $compliance = new ComplianceReport;

        return view('livewire.dashboard', [
            'filters' => $filters,
            'summary' => Compliance::summarise(Compliance::query($filters)),
            'byMachine' => $compliance->rows($filters),
            'byWeek' => $compliance->byWeek($filters),
            'dueToday' => $this->dueToday($filters, $today),
            'awaitingApproval' => (clone $this->scopedRuns($user))->awaitingApproval()->count(),
            'openIssues' => $this->openIssuesBySeverity($user),
            'partsThisMonth' => (new PartsUsageReport)->rows(ReportFilters::make(
                user: $user,
                from: $today->copy()->startOfMonth()->toDateString(),
                to: $today->toDateString(),
            ))->take(8),
            'heatmap' => $this->heatmap($filters),
            'today' => $today,
        ]);
    }

    private function scopedRuns($user): Builder
    {
        return ChecklistRun::query()->whereIn('machine_id', MachineScope::for($user)->select('machines.id'));
    }

    /**
     * @return array{due: int, done: int, overdue: int}
     */
    private function dueToday(ReportFilters $filters, Carbon $today): array
    {
        $runs = ChecklistRun::query()
            ->whereIn('machine_id', $filters->machineIds())
            ->where('scheduled_for', $today->toDateString())
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $done = (int) ($runs[RunStatus::Submitted->value] ?? 0) + (int) ($runs[RunStatus::Approved->value] ?? 0);

        $overdue = ChecklistRun::query()
            ->whereIn('machine_id', $filters->machineIds())
            ->where('scheduled_for', '<', $today->toDateString())
            ->whereIn('status', Compliance::OUTSTANDING)
            ->count();

        return [
            'due' => (int) $runs->sum(),
            'done' => $done,
            'overdue' => $overdue,
        ];
    }

    /**
     * Open issues by severity, highest first. Breakdown is what stops
     * production, so it leads.
     *
     * @return Collection<int, array{severity: IssueSeverity, count: int}>
     */
    private function openIssuesBySeverity($user): Collection
    {
        $counts = Issue::query()
            // The issue scope, matching the register this tile links into —
            // a count that does not agree with the list behind it is worse
            // than no count.
            ->whereIn('machine_id', MachineScope::forIssues($user)->select('machines.id'))
            ->open()
            ->toBase()
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        return collect(IssueSeverity::cases())
            ->sortBy(fn (IssueSeverity $severity): int => $severity->rank())
            ->map(fn (IssueSeverity $severity): array => [
                'severity' => $severity,
                'count' => (int) ($counts[$severity->value] ?? 0),
            ])
            ->values();
    }

    /**
     * Completion heat-map: machine × day, one grouped query for the whole
     * grid. Values are 'done' | 'partial' | 'missed' | 'none'.
     *
     * @return array{machines: Collection<int, Machine>, days: list<string>, cells: array<int, array<string, string>>}
     */
    private function heatmap(ReportFilters $filters): array
    {
        // 30+ day grids stop being readable on screen; the calendar shows the
        // most recent two weeks and the table below carries the full window.
        $end = $filters->to->copy();
        $start = $end->copy()->subDays(13);

        $machines = Machine::query()
            ->whereIn('id', $filters->machineIds())
            ->orderBy('name')
            ->get(['id', 'name']);

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        $rows = ChecklistRun::query()
            ->whereIn('machine_id', $machines->pluck('id'))
            ->where('scheduled_for', '>=', $start->toDateString())
            ->where('scheduled_for', '<=', $end->toDateString())
            ->toBase()
            ->selectRaw(
                'machine_id,
                 scheduled_for,
                 COUNT(*) as total,
                 SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as done,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as missed',
                [RunStatus::Submitted->value, RunStatus::Approved->value, RunStatus::Missed->value]
            )
            ->groupBy('machine_id', 'scheduled_for')
            ->get();

        $cells = [];

        foreach ($rows as $row) {
            $date = Carbon::parse((string) $row->scheduled_for)->toDateString();

            $cells[(int) $row->machine_id][$date] = match (true) {
                (int) $row->missed > 0 => 'missed',
                (int) $row->done >= (int) $row->total => 'done',
                (int) $row->done > 0 => 'partial',
                default => 'open',
            };
        }

        return [
            'machines' => $machines,
            'days' => $days,
            'cells' => $cells,
        ];
    }
}
