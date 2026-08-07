<?php

declare(strict_types=1);

namespace App\Livewire\Issues;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\User;
use App\Support\MachineScope;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Issue detail and workflow (milestone 6) — route `issues.show`.
 *
 * Shows where the fault came from — the run, the checklist item that failed,
 * the operator's reason and their photos — because a maintenance manager
 * deciding what to do needs the evidence, not just a sentence.
 *
 * The workflow itself is deliberately narrow: status moves only along
 * IssueStatus::allowedNext(), assignment only to someone who may actually
 * resolve an issue, and resolving requires notes. Everything is written
 * through the model, so spatie/activitylog records every transition with its
 * causer — that log is rendered back on this page as the issue's history.
 */
#[Layout('layouts::app')]
class IssueDetail extends Component
{
    use AuthorizesRequests;

    /** preventLazyLoading() is on outside production — load what is rendered. */
    private const EAGER_LOADS = [
        'machine.location.site',
        'raisedBy',
        'assignedTo',
        'run.template',
        'runItem.attachments',
        'activitiesAsSubject.causer',
    ];

    public Issue $issue;

    /** Required when resolving. Pre-filled if the issue was resolved before. */
    public string $resolutionNotes = '';

    public function mount(Issue $issue): void
    {
        $this->authorize('view', $issue);

        $issue->load(self::EAGER_LOADS);

        $this->issue = $issue;
        $this->resolutionNotes = (string) ($issue->resolution_notes ?? '');
    }

    public function render(): View
    {
        $this->issue->loadMissing(self::EAGER_LOADS);

        return view('livewire.issues.issue-detail')
            ->title(__('app.issues.issue').' #'.$this->issue->id);
    }

    /** Statuses this user may move the issue to right now. */
    #[Computed]
    public function nextStatuses(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            $this->issue->status->allowedNext(),
            fn (IssueStatus $status): bool => $this->mayMoveTo($user, $status),
        ));
    }

    #[Computed]
    public function canAssign(): bool
    {
        return (bool) Auth::user()?->can('assign', $this->issue);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableUsers(): Collection
    {
        return User::query()
            ->active()
            ->permission('issue.resolve')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number']);
    }

    // ── Workflow ────────────────────────────────────────────────────

    /**
     * Single entry point for every status change — the buttons differ, the
     * rule does not. `$target` arrives from the client and is validated
     * against the enum AND against what the CURRENT status allows, read
     * fresh: a button in a tab left open since this morning must not drive a
     * closed issue back through the workflow.
     */
    public function moveTo(string $target): void
    {
        $status = IssueStatus::tryFrom($target);

        if ($status === null) {
            return;
        }

        $this->issue->refresh();

        $user = Auth::user();

        abort_unless($user !== null && $this->mayMoveTo($user, $status), 403);

        if (! $this->issue->status->canTransitionTo($status)) {
            $this->addError('status', __('app.issues.transition_not_allowed', [
                'status' => $this->issue->status->label(),
            ]));

            return;
        }

        if ($status === IssueStatus::Resolved) {
            $this->validate([
                'resolutionNotes' => ['required', 'string', 'max:2000'],
            ], [
                'resolutionNotes.required' => __('app.issues.resolution_required'),
            ]);
        }

        DB::transaction(function () use ($status): void {
            $attributes = ['status' => $status->value];

            if ($status === IssueStatus::Resolved) {
                $attributes['resolution_notes'] = trim($this->resolutionNotes);
                $attributes['resolved_at'] = now(); // server clock, never the client's
            }

            if ($status === IssueStatus::Open) {
                // Reopening: the repair did not hold. The old notes are kept
                // — they are part of the history of this fault — but the
                // resolved timestamp is not, because it is no longer true.
                $attributes['resolved_at'] = null;
            }

            $this->issue->update($attributes);
        });

        $this->issue->refresh()->load(self::EAGER_LOADS);

        session()->flash('status', __('app.issues.status_changed', [
            'status' => $status->label(),
        ]));
    }

    public function assign(string $userId): void
    {
        $this->authorize('assign', $this->issue);

        // '' means unassign — a deliberate action, not an accident.
        if ($userId === '') {
            $this->issue->update(['assigned_to' => null]);
            $this->issue->refresh()->load(self::EAGER_LOADS);

            session()->flash('status', __('app.issues.unassigned_message'));

            return;
        }

        $assignee = $this->assignableUsers->firstWhere('id', (int) $userId);

        // The id comes from a select in the browser; only somebody who may
        // actually resolve an issue can be handed one.
        abort_if($assignee === null, 403);

        $this->issue->update(['assigned_to' => $assignee->id]);
        $this->issue->refresh()->load(self::EAGER_LOADS);

        session()->flash('status', __('app.issues.assigned_message', [
            'name' => $assignee->full_name,
        ]));
    }

    /**
     * Severity is triage, not a fact recorded at the machine: the operator
     * who raised it picked a default, and whoever triages may disagree.
     */
    public function setSeverity(string $severity): void
    {
        $this->authorize('assign', $this->issue);

        // Action argument, not a bound property — validated against the enum
        // itself rather than through $this->validate(), which only ever sees
        // component state.
        $value = IssueSeverity::tryFrom($severity);

        if ($value === null || $value === $this->issue->severity) {
            return;
        }

        $this->issue->update(['severity' => $value->value]);
        $this->issue->refresh()->load(self::EAGER_LOADS);

        session()->flash('status', __('app.issues.severity_changed', [
            'severity' => $value->label(),
        ]));
    }

    // ── Internals ───────────────────────────────────────────────────

    /**
     * Acknowledging, starting and reopening are triage (`issue.assign`);
     * resolving and closing are the repair itself (`issue.resolve`).
     */
    private function mayMoveTo(User $user, IssueStatus $status): bool
    {
        return match ($status) {
            IssueStatus::Resolved,
            IssueStatus::Closed => $user->can('resolve', $this->issue),
            default => $user->can('assign', $this->issue),
        };
    }

    /**
     * Whether the linked run is still visible to this user — the "open the
     * checklist" link is hidden rather than dead if it is not.
     */
    #[Computed]
    public function canViewRun(): bool
    {
        $run = $this->issue->run;

        return $run !== null && (bool) Auth::user()?->can('view', $run);
    }

    /** Machines this user may see, for the "other open issues" panel. */
    #[Computed]
    public function otherOpenIssues(): Collection
    {
        return Issue::query()
            ->where('machine_id', $this->issue->machine_id)
            ->whereKeyNot($this->issue->id)
            ->whereIn('machine_id', MachineScope::forIssues(Auth::user())->select('machines.id'))
            ->open()
            ->orderBy('created_at')
            ->limit(5)
            ->get(['id', 'severity', 'status', 'description']);
    }
}
