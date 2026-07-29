<?php

declare(strict_types=1);

namespace App\Livewire\Runs;

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Enums\Shift;
use App\Models\ChecklistRun;
use App\Models\Location;
use App\Support\MachineScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Run listing (route `runs.index`). Machines are always scoped through
 * MachineScope — an operator only ever sees runs for machines they may see.
 */
#[Layout('layouts::app')]
class RunIndex extends Component
{
    use WithPagination;

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url]
    public string $machine = '';

    #[Url]
    public string $location = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $shift = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ChecklistRun::class);

        // Default range: today in the plant's display timezone.
        $today = now(config('app.display_timezone', 'UTC'))->toDateString();
        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : $today;
        $this->dateTo = $this->dateTo !== '' ? $this->dateTo : $today;
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $today = now(config('app.display_timezone', 'UTC'))->toDateString();
        $this->reset('machine', 'location', 'status', 'shift');
        $this->dateFrom = $today;
        $this->dateTo = $today;
        $this->resetPage();
    }

    /**
     * Everything except the status/shift dimension — the summary strip counts
     * missed and awaiting-approval within the SAME date/machine/location
     * scope, regardless of the status filter currently applied to the table.
     */
    private function scopedQuery(): Builder
    {
        $user = Auth::user();

        return ChecklistRun::query()
            // Never a raw machine query — MachineScope is the single
            // implementation of operator scoping (BUILD-CONTRACT §5).
            ->whereIn('machine_id', MachineScope::for($user)->select('machines.id'))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('scheduled_for', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('scheduled_for', '<=', $this->dateTo))
            ->when($this->machine !== '', fn (Builder $q) => $q->where('machine_id', (int) $this->machine))
            ->when($this->location !== '', fn (Builder $q) => $q->whereHas(
                'machine',
                fn (Builder $mq) => $mq->where('location_id', (int) $this->location),
            ));
    }

    public function render(): View
    {
        $user = Auth::user();
        $displayTz = (string) config('app.display_timezone', 'UTC');
        $today = now($displayTz)->toDateString();

        // Filter option lists, scoped the same way as the table.
        $machines = MachineScope::for($user)
            ->orderBy('machines.name')
            ->get(['machines.id', 'machines.name', 'machines.location_id']);

        $locations = Location::query()
            ->whereIn('id', $machines->pluck('location_id')->unique()->all())
            ->orderBy('name')
            ->get(['id', 'name']);

        // Whitelist enum-backed filters — anything else is ignored.
        $statusFilter = in_array($this->status, array_column(RunStatus::cases(), 'value'), true)
            ? $this->status : '';
        $shiftFilter = in_array($this->shift, array_column(Shift::cases(), 'value'), true)
            ? $this->shift : '';

        $scoped = $this->scopedQuery();

        $missedCount = (clone $scoped)->where('status', RunStatus::Missed->value)->count();
        $awaitingCount = (clone $scoped)->awaitingApproval()->count();

        $runs = $scoped
            ->when($statusFilter !== '', fn (Builder $q) => $q->where('status', $statusFilter))
            ->when($shiftFilter !== '', fn (Builder $q) => $q->where('shift', $shiftFilter))
            // preventLazyLoading() is on outside production — every relation
            // the view touches is eager-loaded here.
            ->with([
                'template:id,name',
                'machine:id,name,code,location_id',
                'machine.location:id,name',
                'operator:id,full_name',
            ])
            // Progress as aggregates — no item hydration per row.
            ->withCount([
                'items as items_total_count',
                'items as items_done_count' => fn ($q) => $q->where('status', '!=', RunItemStatus::Pending->value),
            ])
            // Default sort: overdue + missed first, then today's open runs,
            // then everything else, newest date first within each band.
            ->orderByRaw(
                "CASE
                    WHEN status = 'missed' THEN 0
                    WHEN status IN ('pending', 'in_progress', 'rejected') AND scheduled_for < ? THEN 0
                    WHEN status IN ('pending', 'in_progress', 'rejected') AND scheduled_for = ? THEN 1
                    ELSE 2
                END",
                [$today, $today],
            )
            ->orderByDesc('scheduled_for')
            ->orderBy('machine_id')
            ->orderBy('id')
            ->paginate(25);

        return view('livewire.runs.run-index', [
            'runs' => $runs,
            'machines' => $machines,
            'locations' => $locations,
            'missedCount' => $missedCount,
            'awaitingCount' => $awaitingCount,
            'displayTz' => $displayTz,
            'today' => $today,
        ]);
    }
}
