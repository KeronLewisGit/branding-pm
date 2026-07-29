<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Location;
use App\Support\MachineScope;
use App\Support\Reporting\ReportFilters;
use App\Support\Reporting\ReportRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Report viewer (milestone 7) — route `reports.index`.
 *
 * Picks a report, sets a window and a machine/location filter, and shows the
 * rows. The CSV and PDF buttons are plain links carrying the same query
 * string to ReportExportController, which rebuilds the identical
 * ReportFilters — so what is exported is what is on screen, including the
 * machine scope.
 */
#[Layout('layouts::app')]
class ReportViewer extends Component
{
    #[Url]
    public string $report = 'compliance';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url]
    public string $machine = '';

    #[Url]
    public string $location = '';

    public function mount(): void
    {
        abort_unless((bool) Auth::user()?->can('report.view'), 403);

        if (! ReportRegistry::has($this->report)) {
            $this->report = ReportRegistry::defaultKey();
        }

        // Default window: the last 30 days, matching ReportFilters.
        $filters = $this->filters();

        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : $filters->from->toDateString();
        $this->dateTo = $this->dateTo !== '' ? $this->dateTo : $filters->to->toDateString();
    }

    public function updatedReport(string $value): void
    {
        if (! ReportRegistry::has($value)) {
            $this->report = ReportRegistry::defaultKey();
        }
    }

    public function render(): View
    {
        $definition = ReportRegistry::make($this->report);
        $filters = $this->filters();

        $machines = MachineScope::for(Auth::user())
            ->orderBy('machines.name')
            ->get(['machines.id', 'machines.name', 'machines.location_id']);

        return view('livewire.reports.report-viewer', [
            'definition' => $definition,
            'filters' => $filters,
            'columns' => $definition->columns(),
            'rows' => $definition->rows($filters),
            'totals' => $definition->totals($filters),
            'reports' => ReportRegistry::all(),
            'machines' => $machines,
            'locations' => Location::query()
                ->whereIn('id', $machines->pluck('location_id')->unique()->all())
                ->orderBy('name')
                ->get(['id', 'name']),
            'exportQuery' => array_filter($filters->toQuery(), fn ($value): bool => $value !== null),
        ]);
    }

    private function filters(): ReportFilters
    {
        return ReportFilters::make(
            user: Auth::user(),
            from: $this->dateFrom !== '' ? $this->dateFrom : null,
            to: $this->dateTo !== '' ? $this->dateTo : null,
            machineId: $this->machine !== '' ? (int) $this->machine : null,
            locationId: $this->location !== '' ? (int) $this->location : null,
        );
    }
}
