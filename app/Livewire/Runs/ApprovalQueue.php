<?php

declare(strict_types=1);

namespace App\Livewire\Runs;

use App\Enums\IssueStatus;
use App\Enums\RunItemStatus;
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
 * Supervisor approval queue (milestone 5) — route `runs.approvals`.
 *
 * Everything a supervisor has to sign, oldest first, because the oldest
 * unsigned run is the one closest to being useless as a record. Runs are
 * scoped through MachineScope exactly like the run listing, so a supervisor
 * only queues work for machines they may see.
 *
 * The two counts on each row (failed items, open issues) exist so the queue
 * itself shows where the attention is needed — a supervisor should not have
 * to open eleven clean runs to find the one with a broken sensor.
 */
#[Layout('layouts::app')]
class ApprovalQueue extends Component
{
    use WithPagination;

    #[Url]
    public string $machine = '';

    #[Url]
    public string $location = '';

    #[Url]
    public string $shift = '';

    /** `oldest` (queue order) or `newest`. */
    #[Url]
    public string $sort = 'oldest';

    public function mount(): void
    {
        // The route carries `permission:run.approve` as a coarse gate; this
        // is the component's own check, because a Livewire component is a
        // public endpoint in its own right.
        abort_unless((bool) Auth::user()?->can('run.approve'), 403);
    }

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('machine', 'location', 'shift');
        $this->resetPage();
    }

    public function render(): View
    {
        $user = Auth::user();
        $displayTz = (string) config('app.display_timezone', 'UTC');

        $machines = MachineScope::for($user)
            ->orderBy('machines.name')
            ->get(['machines.id', 'machines.name', 'machines.location_id']);

        $locations = Location::query()
            ->whereIn('id', $machines->pluck('location_id')->unique()->all())
            ->orderBy('name')
            ->get(['id', 'name']);

        $shiftFilter = in_array($this->shift, array_column(Shift::cases(), 'value'), true)
            ? $this->shift : '';

        $runs = ChecklistRun::query()
            ->awaitingApproval()
            ->whereIn('machine_id', MachineScope::for($user)->select('machines.id'))
            ->when($this->machine !== '', fn (Builder $q) => $q->where('machine_id', (int) $this->machine))
            ->when($this->location !== '', fn (Builder $q) => $q->whereHas(
                'machine',
                fn (Builder $mq) => $mq->where('location_id', (int) $this->location),
            ))
            ->when($shiftFilter !== '', fn (Builder $q) => $q->where('shift', $shiftFilter))
            ->with([
                'template:id,name,requires_supervisor_signoff',
                'machine:id,name,code,location_id',
                'machine.location:id,name',
                'operator:id,full_name,employee_number',
            ])
            ->withCount([
                'items as items_total_count',
                'items as items_done_count' => fn (Builder $q) => $q->where('status', '!=', RunItemStatus::Pending->value),
                'items as items_failed_count' => fn (Builder $q) => $q->where('status', RunItemStatus::Failed->value),
                'issues as open_issues_count' => fn (Builder $q) => $q->whereNotIn('status', [
                    IssueStatus::Resolved->value,
                    IssueStatus::Closed->value,
                ]),
            ])
            // Oldest submission first: the queue is a queue.
            ->orderBy('submitted_at', $this->sort === 'newest' ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate(25);

        return view('livewire.runs.approval-queue', [
            'runs' => $runs,
            'machines' => $machines,
            'locations' => $locations,
            'displayTz' => $displayTz,
        ]);
    }
}
