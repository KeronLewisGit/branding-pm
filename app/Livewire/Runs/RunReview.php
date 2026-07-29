<?php

declare(strict_types=1);

namespace App\Livewire\Runs;

use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use App\Models\User;
use App\Support\SignatureImage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

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
        'runParts',
        'attachments',
        'operator',
        'supervisor',
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
        abort_unless((bool) Auth::user()?->can('run.approve'), 403);

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
}
