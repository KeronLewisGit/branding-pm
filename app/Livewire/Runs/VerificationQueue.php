<?php

declare(strict_types=1);

namespace App\Livewire\Runs;

use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use App\Models\Location;
use App\Support\MachineScope;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Quality Assurance queue (route `runs.verifications`) — approved sheets
 * that nobody has verified yet, oldest first.
 *
 * The mirror of the supervisor's approval queue, one step further along. A
 * supervisor clears "submitted"; a QA officer clears "approved but not
 * verified". Without this the third sign-off would exist but be invisible,
 * and a backlog nobody can see is a backlog nobody clears.
 *
 * Oldest first for the same reason as the approval queue: the sheet that has
 * been waiting longest is the one at risk of being forgotten.
 */
#[Layout('layouts::app')]
class VerificationQueue extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    #[Url(as: 'location')]
    public string $locationFilter = '';

    /**
     * Verified sheets are hidden by default — this is a worklist, not an
     * archive. The record of what was verified lives on each sheet and in
     * the Checks completed report.
     */
    #[Url(as: 'done')]
    public bool $showVerified = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('run.verify') === true, 403);
    }

    public function updating(string $property, mixed $value): void
    {
        if (in_array($property, ['locationFilter', 'showVerified'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<int, Location>
     */
    #[Computed]
    public function locations(): Collection
    {
        return Location::query()
            ->whereIn('id', MachineScope::for(Auth::user())->select('machines.location_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * How many sheets are waiting — shown on the screen so the size of the
     * job is visible before anything is opened.
     */
    #[Computed]
    public function outstandingCount(): int
    {
        return $this->baseQuery()->whereNull('qa_verified_at')->count();
    }

    private function baseQuery(): Builder
    {
        return ChecklistRun::query()
            ->where('status', RunStatus::Approved)
            ->whereIn('machine_id', MachineScope::for(Auth::user())->select('machines.id'))
            ->when($this->locationFilter !== '', fn (Builder $query) => $query->whereIn(
                'machine_id',
                MachineScope::for(Auth::user())
                    ->where('machines.location_id', (int) $this->locationFilter)
                    ->select('machines.id'),
            ));
    }

    public function render(): View
    {
        $runs = $this->baseQuery()
            ->when(! $this->showVerified, fn (Builder $query) => $query->whereNull('qa_verified_at'))
            ->with(['machine:id,name,code', 'template:id,name', 'operator:id,full_name', 'supervisor:id,full_name', 'qaVerifiedBy:id,full_name'])
            // Counted on the row so the queue shows where attention is needed
            // before anything is opened — same reasoning as the approval queue.
            ->withCount([
                'items as failed_items_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.runs.verification-queue', [
            'runs' => $runs,
        ])->title(__('app.qa.queue_title'));
    }
}
