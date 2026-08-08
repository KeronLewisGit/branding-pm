<?php

declare(strict_types=1);

namespace App\Livewire\Issues;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Machine;
use App\Models\User;
use App\Support\MachineScope;
use App\Support\SqlOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Issue register (milestone 6) — route `issues.index`.
 *
 * Most issues arrive here on their own: RunForm raises one from every failed
 * checklist item, pre-filled with the machine, the item and the operator's
 * reason. The register is where they are then triaged, assigned and closed —
 * and it is also the one place an issue can be raised outside a run, for the
 * fault somebody notices between checklists.
 *
 * Default view is the open work only. Resolved and closed issues stay
 * reachable through the status filter; nothing is ever deleted, because an
 * issue is part of the same maintenance record the checklists are.
 *
 * Scoped by MachineScope like runs — an operator sees issues for their own
 * machines, a manager sees everything.
 */
#[Layout('layouts::app')]
class IssueRegister extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    /** Pseudo-status meaning "any of open / acknowledged / in progress". */
    public const FILTER_OPEN = 'open_all';

    #[Url]
    public string $machine = '';

    #[Url]
    public string $location = '';

    #[Url]
    public string $severity = '';

    #[Url]
    public string $status = self::FILTER_OPEN;

    /** '', 'me', 'unassigned', or a user id. */
    #[Url]
    public string $assignee = '';

    #[Url(as: 'q')]
    public string $search = '';

    // ── New-issue form (modal) ──────────────────────────────────────

    public bool $creating = false;

    public string $newMachineId = '';

    public string $newSeverity = 'medium';

    public string $newDescription = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Issue::class);
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'new') && $property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('machine', 'location', 'severity', 'assignee', 'search');
        $this->status = self::FILTER_OPEN;
        $this->resetPage();
    }

    // ── Raising an issue outside a run ──────────────────────────────

    public function openCreate(): void
    {
        $this->authorize('create', Issue::class);

        $this->newMachineId = '';
        $this->newSeverity = IssueSeverity::Medium->value;
        $this->newDescription = '';
        $this->resetErrorBag();
        $this->creating = true;

        $this->dispatch('open-modal', name: 'create-issue');
    }

    public function createIssue(): void
    {
        $this->authorize('create', Issue::class);

        $this->validate([
            // Only machines the user may see — the id comes from the client.
            'newMachineId' => [
                'required',
                Rule::exists('machines', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'id',
                        MachineScope::forIssues(Auth::user())->select('machines.id'),
                    ),
                ),
            ],
            'newSeverity' => ['required', Rule::enum(IssueSeverity::class)],
            'newDescription' => ['required', 'string', 'max:2000'],
        ], [
            'newMachineId.required' => __('app.issues.machine_required'),
            'newMachineId.exists' => __('app.issues.machine_required'),
            'newDescription.required' => __('app.issues.description_required'),
        ]);

        $issue = Issue::query()->create([
            // No run and no run item: this fault was not found by a checklist.
            'checklist_run_id' => null,
            'checklist_run_item_id' => null,
            'machine_id' => (int) $this->newMachineId,
            'raised_by' => Auth::id(),
            'severity' => $this->newSeverity,
            'description' => trim($this->newDescription),
            'status' => IssueStatus::Open->value,
        ]);

        $this->creating = false;
        $this->dispatch('close-modal', name: 'create-issue');

        session()->flash('status', __('app.issues.created_message'));

        $this->redirectRoute('issues.show', ['issue' => $issue->id]);
    }

    public function cancelCreate(): void
    {
        $this->creating = false;
        $this->resetErrorBag();
        $this->dispatch('close-modal', name: 'create-issue');
    }

    // ── Listing ─────────────────────────────────────────────────────

    /**
     * Everything except the severity/status/assignee dimensions, so the
     * summary strip counts within the same machine scope no matter which
     * filter the table is currently showing.
     */
    private function scopedQuery(): Builder
    {
        return Issue::query()
            ->whereIn('machine_id', MachineScope::forIssues(Auth::user())->select('machines.id'))
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

        $machines = MachineScope::forIssues($user)
            ->orderBy('machines.name')
            ->get(['machines.id', 'machines.name', 'machines.location_id']);

        $locations = Location::query()
            ->whereIn('id', $machines->pluck('location_id')->unique()->all())
            ->orderBy('name')
            ->get(['id', 'name']);

        // Whitelist the enum-backed filters — anything else is ignored.
        $severityFilter = in_array($this->severity, array_column(IssueSeverity::cases(), 'value'), true)
            ? $this->severity : '';
        $statusFilter = in_array($this->status, array_column(IssueStatus::cases(), 'value'), true)
            ? $this->status
            : ($this->status === self::FILTER_OPEN ? self::FILTER_OPEN : '');

        $scoped = $this->scopedQuery();

        // Open counts by severity, for the strip above the table.
        $openCounts = (clone $scoped)->open()
            ->toBase()
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $issues = $scoped
            ->when($severityFilter !== '', fn (Builder $q) => $q->where('severity', $severityFilter))
            ->when($statusFilter === self::FILTER_OPEN, fn (Builder $q) => $q->open())
            ->when(
                $statusFilter !== '' && $statusFilter !== self::FILTER_OPEN,
                fn (Builder $q) => $q->where('status', $statusFilter),
            )
            ->when($this->assignee === 'me', fn (Builder $q) => $q->where('assigned_to', $user->id))
            ->when($this->assignee === 'unassigned', fn (Builder $q) => $q->whereNull('assigned_to'))
            ->when(
                is_numeric($this->assignee),
                fn (Builder $q) => $q->where('assigned_to', (int) $this->assignee),
            )
            ->when($this->search !== '', function (Builder $q): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->search)).'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('description', 'like', $term)
                    ->orWhereHas('machine', fn (Builder $mq) => $mq
                        ->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)));
            })
            ->with([
                'machine:id,name,code,location_id',
                'machine.location:id,name',
                'raisedBy:id,full_name',
                'assignedTo:id,full_name',
            ])
            // Open before closed, then breakdown first, then oldest — the
            // register reads top-down as "what needs doing next".
            ->orderByRaw(...SqlOrder::first('status', array_column(IssueStatus::openStatuses(), 'value')))
            ->orderByRaw(...SqlOrder::rank('severity', IssueSeverity::mostUrgentFirst()))
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(25);

        return view('livewire.issues.issue-register', [
            'issues' => $issues,
            'machines' => $machines,
            'locations' => $locations,
            'assignees' => $this->assignableUsers(),
            'openCounts' => $openCounts,
            'displayTz' => $displayTz,
        ]);
    }

    /**
     * Who an issue can be assigned to: active users who are allowed to
     * resolve one. Used by the assignee filter here and by the assignment
     * control on the detail screen.
     *
     * @return Collection<int, User>
     */
    private function assignableUsers(): Collection
    {
        return User::query()
            ->active()
            ->permission('issue.resolve')
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }

    /**
     * Machines for the "raise an issue" picker.
     *
     * Uses the wider run scope, NOT `forIssues()`, on purpose: reporting a
     * fault must never be harder than walking past one. An operator who
     * notices a bearing screaming on a machine they are not assigned to
     * should be able to say so, and the register is filtered for reading, not
     * for reporting.
     *
     * Consequence, accepted: they may raise a fault that then does not appear
     * in their own register. The alternative is an unreported fault.
     *
     * @return Collection<int, Machine>
     */
    public function creatableMachines(): Collection
    {
        return MachineScope::for(Auth::user())
            ->where('machines.is_active', true)
            ->orderBy('machines.name')
            ->get(['machines.id', 'machines.name']);
    }
}
