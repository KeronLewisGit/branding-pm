<?php

declare(strict_types=1);

namespace App\Livewire\Runs;

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use App\Models\User;
use App\Support\SignatureImage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Supervisor review and sign-off (milestone 5) — route `runs.review`.
 *
 * Read-only in every respect except the two decisions at the bottom:
 *
 *   Approve — supervisor signature required, comment optional. Status
 *             `approved`, and from that moment the run is immutable
 *             (RunStatus::isEditable() excludes it); a correction needs a
 *             logged amendment by a holder of `run.amend`.
 *   Reject  — comment REQUIRED and no signature taken. A rejection is not a
 *             sign-off, so nothing is signed; the run drops back to
 *             `rejected`, which is editable again, and the operator sees the
 *             comment at the top of their sheet.
 *
 * Both decisions re-read the run from the database first: two supervisors
 * can have the same queue open, and the second one must not overwrite the
 * first one's signature.
 */
#[Layout('layouts::app')]
class RunReview extends Component
{
    use AuthorizesRequests;

    /** Everything the review screen renders — preventLazyLoading() is on. */
    private const EAGER_LOADS = [
        'template',
        'machine.location.site',
        'items.attachments',
        'items.completedBy',
        'attachments',
        'operator',
        'supervisor',
        'qaVerifiedBy',
        'issues.raisedBy',
    ];

    public ChecklistRun $run;

    /** Supervisor comment — required to reject, optional to approve. */
    public string $comment = '';

    public function mount(ChecklistRun $run): void
    {
        $this->authorize('view', $run);

        // Reviewing is a supervisor act even when the run turns out to be
        // one this user may not decide on (their own work — see the
        // two-person rule in ChecklistRunPolicy).
        //
        // `run.verify` too: a Quality Assurance officer holds neither
        // `run.approve` nor `run.reject`, and cannot verify a sheet they are
        // not allowed to read.
        $user = Auth::user();

        abort_unless($user?->can('run.approve') === true || $user?->can('run.verify') === true, 403);

        $run->load(self::EAGER_LOADS);

        $this->run = $run;
        $this->comment = (string) ($run->supervisor_comment ?? '');
    }

    public function render(): View
    {
        $this->run->loadMissing(self::EAGER_LOADS);

        return view('livewire.runs.run-review', [
            'progress' => $this->run->progress,
        ])->title(__('app.approvals.review').' — '.$this->run->machine->name);
    }

    /** Whether this user may still act on this run, right now. */
    #[Computed]
    public function canDecide(): bool
    {
        $user = Auth::user();

        return $user !== null
            && $user->can('approve', $this->run)
            && $user->can('reject', $this->run);
    }

    /**
     * Reason the decision buttons are absent, so the screen never just goes
     * quiet on a supervisor who expected to be able to sign.
     */
    #[Computed]
    public function blockedReason(): ?string
    {
        $user = Auth::user();

        if ($this->run->status !== RunStatus::Submitted) {
            return __('app.approvals.already_decided', ['status' => $this->run->status->label()]);
        }

        if ($user !== null && $user->id === $this->run->operator_id) {
            return __('app.approvals.self_signoff_blocked');
        }

        return __('app.common.not_authorized');
    }

    /**
     * Sign off. The signature arrives as an action argument for the same
     * reason it does on the run form — a PNG data URL has no business in the
     * component snapshot.
     */
    public function approve(string $signature = ''): void
    {
        $this->run->refresh();

        // Policy: run.approve + status still `submitted` + not the operator.
        $this->authorize('approve', $this->run);

        $this->resetErrorBag(['signature', 'comment']);

        $this->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($signature === '') {
            $this->addError('signature', __('app.runs.signature_required'));

            return;
        }

        if (! SignatureImage::isValid($signature)) {
            $this->addError('signature', __('app.runs.signature_invalid'));

            return;
        }

        /** @var User $user */
        $user = Auth::user();
        $comment = trim($this->comment);

        DB::transaction(function () use ($comment, $signature, $user): void {
            $this->run->update([
                'status' => RunStatus::Approved,
                'supervisor_id' => $user->id,
                'supervisor_signature_path' => SignatureImage::store($signature, $this->run, 'supervisor'),
                'supervisor_signed_at' => now(), // server clock, never the client's
                'supervisor_comment' => $comment !== '' ? $comment : null,
            ]);
        });

        session()->flash('status', __('app.approvals.approved_message', [
            'machine' => $this->run->loadMissing('machine')->machine->name,
        ]));

        $this->redirectRoute('runs.approvals');
    }

    /**
     * Send the run back to the operator. A rejection without a reason is
     * useless to the person who has to fix it, so the comment is required.
     */
    public function reject(): void
    {
        $this->run->refresh();

        $this->authorize('reject', $this->run);

        $this->resetErrorBag(['signature', 'comment']);

        $this->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ], [
            'comment.required' => __('app.approvals.reject_reason_required'),
        ]);

        /** @var User $user */
        $user = Auth::user();

        DB::transaction(function () use ($user): void {
            $this->run->update([
                'status' => RunStatus::Rejected,
                'supervisor_id' => $user->id,
                'supervisor_comment' => trim($this->comment),
                // Deliberately unsigned: a rejection is not a sign-off. The
                // operator's own signature is kept until they re-sign, so
                // the record still shows who submitted what was refused.
                'supervisor_signature_path' => null,
                'supervisor_signed_at' => null,
            ]);
        });

        session()->flash('status', __('app.approvals.rejected_message', [
            'machine' => $this->run->loadMissing('machine')->machine->name,
        ]));

        $this->redirectRoute('runs.approvals');
    }

    /*
    |--------------------------------------------------------------------------
    | Amendment (SPEC §Supervisor sign-off)
    |--------------------------------------------------------------------------
    | "Approved runs are immutable — corrections happen by an admin-only
    | amendment that is logged, never by silent edit."
    |
    | An approved sheet is a signed record, and sometimes a signed record is
    | wrong: an item ticked on the wrong row, a quantity fat-fingered, a note
    | that says the opposite of what happened. The choice is between letting a
    | holder of `run.amend` correct it in the open, or watching somebody do it
    | in the database where nothing is recorded.
    |
    | So an amendment:
    |   - needs `run.amend` AND a status of `approved` (ChecklistRunPolicy)
    |   - requires a written reason, always
    |   - records the old value, the new value, the reason and the actor in
    |     the activity log, and shows that history on this screen
    |   - leaves the run `approved` and both signatures untouched. It is a
    |     correction to the record, not a re-approval, and re-signing on
    |     somebody else's behalf is exactly what the two-person rule forbids.
    |
    | It deliberately CANNOT touch signatures, timestamps, or the status. Those
    | are the attestation itself; a sheet that needs those changed needs a new
    | sheet.
    */

    /** `item` | `notes` — which kind of thing is being corrected. */
    public ?string $amendTarget = null;

    public ?int $amendTargetId = null;

    public string $amendItemStatus = '';

    public string $amendFailReason = '';

    public string $amendNotes = '';


    public string $amendReason = '';

    /** May this user amend this run, right now? */
    #[Computed]
    public function canAmend(): bool
    {
        return Auth::user()?->can('amend', $this->run) === true;
    }

    /**
     * Every amendment made to this run, newest first.
     *
     * Rendered from the activity log itself rather than a column on the run,
     * so the audit trail and what the screen shows cannot drift apart — the
     * same reasoning as the issue history.
     *
     * @return Collection<int, Activity>
     */
    #[Computed]
    public function amendments(): Collection
    {
        return Activity::query()
            ->where('log_name', 'run')
            ->where('description', 'run.amended')
            ->where('subject_type', $this->run->getMorphClass())
            ->where('subject_id', $this->run->getKey())
            ->with('causer')
            ->latest('id')
            ->get();
    }

    public function openAmendItem(int $itemId): void
    {
        $this->authorize('amend', $this->run);

        $item = $this->run->items()->findOrFail($itemId);

        $this->resetAmendForm();
        $this->amendTarget = 'item';
        $this->amendTargetId = $item->id;
        $this->amendItemStatus = $item->status->value;
        $this->amendFailReason = (string) ($item->fail_reason ?? '');

        $this->dispatch('open-modal', name: 'run-amend');
    }

    public function openAmendNotes(): void
    {
        $this->authorize('amend', $this->run);

        $this->resetAmendForm();
        $this->amendTarget = 'notes';
        $this->amendNotes = (string) ($this->run->notes ?? '');

        $this->dispatch('open-modal', name: 'run-amend');
    }

    /**
     * Apply the correction and write it to the audit trail.
     *
     * Re-reads the run first: `amend` requires the status to still be
     * `approved`, and a modal can sit open while somebody else acts.
     */
    public function saveAmendment(): void
    {
        $this->run->refresh();

        $this->authorize('amend', $this->run);

        $rules = ['amendReason' => ['required', 'string', 'min:5', 'max:2000']];

        $rules += match ($this->amendTarget) {
            'item' => [
                'amendItemStatus' => ['required', Rule::enum(RunItemStatus::class)],
                'amendFailReason' => ['nullable', 'string', 'max:500'],
            ],
            'notes' => ['amendNotes' => ['nullable', 'string', 'max:5000']],
            default => [],
        };

        $this->validate($rules);

        [$field, $before, $after] = match ($this->amendTarget) {
            'item' => $this->applyItemAmendment(),
            'notes' => $this->applyNotesAmendment(),
            default => [null, null, null],
        };

        if ($field === null) {
            return;
        }

        // Unchanged is not an amendment. Logging one would put a reason in
        // the record for a correction that never happened.
        if ($before === $after) {
            $this->addError('amendReason', __('app.amend.nothing_changed'));

            return;
        }

        activity('run')
            ->causedBy(Auth::user())
            ->performedOn($this->run)
            ->withProperties([
                'field' => $field,
                'old' => $before,
                'new' => $after,
                'reason' => trim($this->amendReason),
                'ip' => request()->ip(),
            ])
            ->log('run.amended');

        session()->flash('flash.success', __('app.amend.saved'));

        $this->dispatch('close-modal', name: 'run-amend');
        $this->resetAmendForm();

        $this->run->refresh()->load(self::EAGER_LOADS);
        unset($this->amendments);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function applyItemAmendment(): array
    {
        $item = $this->run->items()->findOrFail($this->amendTargetId);

        $status = RunItemStatus::from($this->amendItemStatus);

        // A reason only belongs on a failure; carrying one onto a passing
        // item would leave the sheet contradicting itself.
        $failReason = $status === RunItemStatus::Failed
            ? (trim($this->amendFailReason) !== '' ? trim($this->amendFailReason) : null)
            : null;

        $before = $item->status->label().($item->fail_reason ? ' — '.$item->fail_reason : '');
        $after = $status->label().($failReason ? ' — '.$failReason : '');

        DB::transaction(fn () => $item->forceFill([
            'status' => $status,
            'fail_reason' => $failReason,
        ])->save());

        return [__('app.amend.field_item', ['number' => $item->sort_order, 'description' => $item->description]), $before, $after];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function applyNotesAmendment(): array
    {
        $before = (string) ($this->run->notes ?? '');
        $after = trim($this->amendNotes);

        DB::transaction(fn () => $this->run->forceFill(['notes' => $after !== '' ? $after : null])->save());

        return [__('app.runs.notes'), $before, $after];
    }

    private function resetAmendForm(): void
    {
        $this->reset('amendTarget', 'amendTargetId', 'amendItemStatus', 'amendFailReason', 'amendNotes', 'amendReason');
        $this->resetErrorBag();
    }

    /*
    |--------------------------------------------------------------------------
    | Quality Assurance verification — the third sign-off
    |--------------------------------------------------------------------------
    | Operator signs, supervisor approves, QA verifies. Three people, three
    | separate acts, and the last one is performed by somebody who did neither
    | of the first two.
    |
    | Verification does NOT change the run's status. `approved` already means
    | "the supervisor signed this off"; adding a fourth status would make
    | every existing compliance figure mean something different overnight.
    | Verification is recorded alongside it and reported separately.
    */

    /** QA finding, optional — most verifications have nothing to say. */
    public string $qaComment = '';

    #[Computed]
    public function canVerify(): bool
    {
        return Auth::user()?->can('verify', $this->run) === true;
    }

    /**
     * Why the verify panel is absent, so a QA officer is never left guessing.
     */
    #[Computed]
    public function verifyBlockedReason(): ?string
    {
        $user = Auth::user();

        if ($user === null || ! $user->can('run.verify')) {
            return null; // Not a QA officer — say nothing at all.
        }

        if ($this->run->qa_verified_at !== null) {
            return __('app.qa.already_verified', [
                'name' => $this->run->qaVerifiedBy?->full_name ?? __('app.common.none'),
                'at' => $this->run->qa_verified_at
                    ->timezone((string) config('app.display_timezone', 'UTC'))
                    ->format('d M Y, g:i A'),
            ]);
        }

        if ($this->run->status !== RunStatus::Approved) {
            return __('app.qa.not_approved_yet');
        }

        if ($user->id === $this->run->operator_id || $user->id === $this->run->supervisor_id) {
            return __('app.qa.self_verify_blocked');
        }

        return __('app.common.not_authorized');
    }

    /**
     * Record the verification.
     *
     * Re-reads the run first: the policy requires it to still be approved and
     * still unverified, and two QA officers can hold the same queue open.
     */
    public function verify(): void
    {
        $this->run->refresh();

        $this->authorize('verify', $this->run);

        $this->validate(['qaComment' => ['nullable', 'string', 'max:2000']]);

        $comment = trim($this->qaComment);

        DB::transaction(fn () => $this->run->forceFill([
            'qa_verified_by' => Auth::id(),
            // Server clock, like every other timestamp on this record.
            'qa_verified_at' => now(),
            'qa_comment' => $comment !== '' ? $comment : null,
        ])->save());

        activity('run')
            ->causedBy(Auth::user())
            ->performedOn($this->run)
            ->withProperties(['comment' => $comment, 'ip' => request()->ip()])
            ->log('run.qa_verified');

        session()->flash('flash.success', __('app.qa.verified_message'));

        $this->qaComment = '';
        $this->run->refresh()->load(self::EAGER_LOADS);
        unset($this->canVerify, $this->verifyBlockedReason);
    }
}
