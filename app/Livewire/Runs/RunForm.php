<?php

declare(strict_types=1);

namespace App\Livewire\Runs;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\ResponseType;
use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Models\Attachment;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistRunPart;
use App\Models\Issue;
use App\Models\User;
use App\Support\SignatureImage;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * THE run completion form (milestone 4) — route `runs.show`.
 *
 * Design rules, from SPEC §2 and BUILD-CONTRACT §8:
 * - Every item response persists IMMEDIATELY through a dedicated action.
 *   There is no save button for item state.
 * - `completed_at` / `started_at` / `submitted_at` are always the server
 *   clock (`now()`). A client timestamp is never accepted.
 * - Every Livewire action is a public HTTP endpoint, so every mutating
 *   action re-checks the policy — mounting alone is never enough.
 * - Offline (milestone 8) is not built here, but every mutation is kept
 *   small, absolute-state and idempotent so an offline queue can replay it
 *   safely: actions set "status = X, value = Y", never "toggle"/"increment"
 *   relative to unknown server state (toggleDone passes the expected status
 *   for exactly this reason — replaying it is a no-op, not a flip-flop).
 */
#[Layout('layouts::kiosk')]
class RunForm extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    /**
     * Relations the form renders. Eager-loaded because
     * Model::preventLazyLoading() is active outside production, and Livewire
     * re-hydrates the model WITHOUT relations on every request.
     */
    private const EAGER_LOADS = [
        'template',
        'machine.location.site',
        'items.templateItem',
        'items.attachments',
        'items.completedBy',
        'runParts',
        'attachments',
        // Signature blocks (milestone 5) print the signer's name and number.
        'operator',
        'supervisor',
    ];

    public ChecklistRun $run;

    /** The "Notes:" box from the paper sheet. Debounced autosave. */
    public string $notes = '';

    public bool $notesSaved = false;

    /** @var array<int|string, mixed> run part id => qty_used (stepper binding) */
    public array $qty = [];

    // ── Item options / fail flow state ──────────────────────────────

    public ?int $actionItemId = null;

    /** Status the row showed when the operator opened the options sheet. */
    public string $actionItemExpectedStatus = '';

    public string $failReason = '';

    /** @var TemporaryUploadedFile|null */
    public $failPhoto = null;

    // ── Issue prompt state (pre-filled after a fail) ────────────────

    public ?int $issueItemId = null;

    public string $issueDescription = '';

    public string $issueSeverity = 'medium';

    // ── Photos ──────────────────────────────────────────────────────

    /** @var array<int|string, mixed> item id => pending upload */
    public array $itemPhotos = [];

    /** @var TemporaryUploadedFile|null run-level photo pending upload */
    public $runPhoto = null;

    // ── Submission / messaging state ────────────────────────────────

    /** Show the "these required items are still pending" panel. */
    public bool $showMissing = false;

    /** Amber banner: someone else answered an item this screen showed as open. */
    public ?string $conflictNotice = null;

    /** Green banner: e.g. "issue raised". */
    public ?string $notice = null;

    // ── Lifecycle ───────────────────────────────────────────────────

    public function mount(ChecklistRun $run): void
    {
        $this->authorize('view', $run);

        $run->load(self::EAGER_LOADS);

        $this->run = $run;
        $this->notes = (string) ($run->notes ?? '');

        foreach ($run->runParts as $part) {
            $this->qty[$part->id] = (string) $part->qty_used;
        }
    }

    public function render(): View
    {
        $this->run->loadMissing(self::EAGER_LOADS);

        return view('livewire.runs.run-form', [
            'actionItem' => $this->actionItemId !== null
                ? $this->run->items->firstWhere('id', $this->actionItemId)
                : null,
            'progress' => $this->run->progress,
        ])->title($this->run->template->name.' — '.$this->run->machine->name);
    }

    /**
     * True while the operator can still change answers. The policy repeats
     * this check server-side on every mutation — this is for rendering.
     */
    #[Computed]
    public function isEditable(): bool
    {
        return $this->run->status->isEditable()
            && auth()->user()->can('update', $this->run);
    }

    /**
     * How the signer re-confirms their identity at the moment of signing
     * (SPEC §2: "signature … plus the PIN confirmation").
     *
     * `pin` for anyone with a PIN — every shop-floor operator, and the only
     * path that matters on a shared kiosk tablet, where the session belongs
     * to whoever tapped their name last. `password` for office users who
     * have no PIN (a supervisor covering a shift). `none` only for an
     * account with neither, which cannot have logged in at all.
     */
    #[Computed]
    public function confirmationMethod(): string
    {
        $user = auth()->user();

        return match (true) {
            $user?->hasPin() => 'pin',
            (bool) $user?->canLoginWithPassword() => 'password',
            default => 'none',
        };
    }

    // ── Item actions (each one writes immediately) ──────────────────

    /**
     * Tap anywhere on a `check` row: pending → done, done → pending (undo).
     * Failed / N/A rows never toggle by tap — undo goes through the options
     * sheet so a stray tap cannot wipe a fail reason.
     */
    public function toggleDone(int $itemId, string $expectedStatus): void
    {
        $this->authorize('update', $this->run);

        $item = $this->freshItem($itemId);

        if ($item === null || $item->response_type !== ResponseType::Check) {
            return;
        }

        if ($this->isConflicted($item, $expectedStatus)) {
            return;
        }

        $this->ensureStarted();

        if ($item->status === RunItemStatus::Pending) {
            $item->markDone(auth()->user());
        } elseif ($item->status === RunItemStatus::Done) {
            $this->resetItem($item);
        }
    }

    /** Big "Pass" button on a pass_fail row. */
    public function markPass(int $itemId, string $expectedStatus): void
    {
        $this->authorize('update', $this->run);

        $item = $this->freshItem($itemId);

        if ($item === null || $item->response_type !== ResponseType::PassFail) {
            return;
        }

        if ($this->isConflicted($item, $expectedStatus)) {
            return;
        }

        $this->ensureStarted();

        if ($item->status !== RunItemStatus::Done) {
            $item->markDone(auth()->user());
        }
    }

    /** Numeric reading → value_numeric, status done. Clearing it → pending. */
    public function setNumeric(int $itemId, ?string $value, string $expectedStatus): void
    {
        $this->authorize('update', $this->run);

        $item = $this->freshItem($itemId);

        if ($item === null || $item->response_type !== ResponseType::Numeric) {
            return;
        }

        if ($this->isConflicted($item, $expectedStatus)) {
            return;
        }

        $value = trim((string) $value);

        if ($value === '') {
            $this->resetItem($item);
            $this->resetErrorBag('value.'.$itemId);

            return;
        }

        if (! is_numeric($value) || abs((float) $value) >= 1_000_000_000) { // decimal(12,3)
            $this->addError('value.'.$itemId, __('app.runs.invalid_number'));

            return;
        }

        $this->ensureStarted();

        // Absolute value + absolute status — idempotent under offline replay.
        $item->value_numeric = $value;
        $item->markDone(auth()->user()); // saves value + server-side completed_at/by

        $this->resetErrorBag('value.'.$itemId);
    }

    /** Text response → value_text, status done. Clearing it → pending. */
    public function setText(int $itemId, ?string $value, string $expectedStatus): void
    {
        $this->authorize('update', $this->run);

        $item = $this->freshItem($itemId);

        if ($item === null || $item->response_type !== ResponseType::Text) {
            return;
        }

        if ($this->isConflicted($item, $expectedStatus)) {
            return;
        }

        $value = trim((string) $value);

        if ($value === '') {
            $this->resetItem($item);
            $this->resetErrorBag('value.'.$itemId);

            return;
        }

        if (mb_strlen($value) > 2000) {
            $this->addError('value.'.$itemId, __('app.runs.text_too_long'));

            return;
        }

        $this->ensureStarted();

        $item->value_text = $value;
        $item->markDone(auth()->user());

        $this->resetErrorBag('value.'.$itemId);
    }

    // ── Options sheet (N/A · Fail · Undo · photo) ───────────────────

    public function openActions(int $itemId, string $expectedStatus): void
    {
        if (! $this->isEditable) {
            return;
        }

        $item = $this->freshItem($itemId);

        if ($item === null) {
            return;
        }

        if ($item->status->value !== $expectedStatus) {
            // Row was stale — say so, then open against the real state.
            $this->isConflicted($item, $expectedStatus);
        }

        $this->actionItemId = $item->id;
        $this->actionItemExpectedStatus = $item->status->value;
        $this->failReason = '';
        $this->failPhoto = null;
        $this->resetErrorBag();

        $this->dispatch('open-modal', name: 'item-actions');
    }

    public function markNotApplicable(): void
    {
        $this->authorize('update', $this->run);

        if ($this->actionItemId === null) {
            return;
        }

        $item = $this->freshItem($this->actionItemId);

        if ($item === null || $this->isConflicted($item, $this->actionItemExpectedStatus)) {
            $this->closeItemModals();

            return;
        }

        $this->ensureStarted();
        $item->markNotApplicable(auth()->user());

        $this->closeItemModals();
    }

    /** Reset an answered item back to pending, from the options sheet. */
    public function undoItem(): void
    {
        $this->authorize('update', $this->run);

        if ($this->actionItemId === null) {
            return;
        }

        $item = $this->freshItem($this->actionItemId);

        if ($item === null || $this->isConflicted($item, $this->actionItemExpectedStatus)) {
            $this->closeItemModals();

            return;
        }

        $this->ensureStarted();
        $this->resetItem($item);

        $this->closeItemModals();
    }

    /** "Mark Failed" inside the options sheet. */
    public function openFailForm(): void
    {
        if ($this->actionItemId !== null) {
            $this->openFailFormFor($this->actionItemId, $this->actionItemExpectedStatus);
        }
    }

    /** Direct "Fail" button on a pass_fail row (skips the options sheet). */
    public function openFailFormFor(int $itemId, string $expectedStatus): void
    {
        if (! $this->isEditable) {
            return;
        }

        $item = $this->freshItem($itemId);

        if ($item === null) {
            return;
        }

        if ($item->status->value !== $expectedStatus) {
            $this->isConflicted($item, $expectedStatus);
        }

        $this->actionItemId = $item->id;
        $this->actionItemExpectedStatus = $item->status->value;
        $this->failReason = '';
        $this->failPhoto = null;
        $this->resetErrorBag();

        $this->dispatch('close-modal', name: 'item-actions');
        $this->dispatch('open-modal', name: 'item-fail');
    }

    /**
     * Failed requires a reason; a photo too when the (template) item says so.
     * Then the pre-filled issue prompt opens.
     */
    public function confirmFail(): void
    {
        $this->authorize('update', $this->run);

        if ($this->actionItemId === null) {
            return;
        }

        $item = $this->freshItem($this->actionItemId);

        if ($item === null) {
            $this->closeItemModals();

            return;
        }

        // requires_photo_on_fail is not snapshotted onto run items (contract
        // §2), so it is read from the linked template item; a deleted template
        // item (nullOnDelete) simply means no photo requirement.
        $needsPhoto = (bool) ($item->templateItem?->requires_photo_on_fail ?? false);

        $this->validate([
            'failReason' => ['required', 'string', 'max:500'],
            'failPhoto' => $this->photoRules($needsPhoto),
        ], [
            'failReason.required' => __('app.runs.fail_reason_required'),
            'failPhoto.required' => __('app.runs.photo_required_on_fail'),
        ]);

        if ($this->isConflicted($item, $this->actionItemExpectedStatus)) {
            $this->closeItemModals();

            return;
        }

        $this->ensureStarted();

        $reason = trim($this->failReason);

        DB::transaction(function () use ($item, $reason): void {
            $item->markFailed(auth()->user(), $reason);

            if ($this->failPhoto instanceof TemporaryUploadedFile) {
                $this->storeAttachment($this->failPhoto, $item);
            }
        });

        // Pre-fill the issue prompt: machine, run + run item (FKs), a
        // description built from the failure, and a default severity.
        $this->issueItemId = $item->id;
        $this->issueSeverity = IssueSeverity::Medium->value;
        $this->issueDescription = __('app.runs.issue_prefill', [
            'machine' => $this->run->loadMissing('machine')->machine->name,
            'number' => $item->sort_order,
            'item' => $item->description,
            'reason' => $reason,
        ]);

        $this->failReason = '';
        $this->failPhoto = null;
        $this->actionItemId = null;
        $this->actionItemExpectedStatus = '';

        $this->dispatch('close-modal', name: 'item-fail');
        $this->dispatch('open-modal', name: 'item-issue');
    }

    // ── Issue prompt ────────────────────────────────────────────────

    public function raiseIssue(): void
    {
        $this->authorize('update', $this->run);
        abort_unless((bool) auth()->user()?->can('issue.create'), 403);

        if ($this->issueItemId === null) {
            return;
        }

        $this->validate([
            'issueDescription' => ['required', 'string', 'max:2000'],
            'issueSeverity' => ['required', Rule::enum(IssueSeverity::class)],
        ]);

        Issue::query()->create([
            'checklist_run_id' => $this->run->id,
            'checklist_run_item_id' => $this->issueItemId,
            'machine_id' => $this->run->machine_id,
            'raised_by' => auth()->id(),
            'severity' => $this->issueSeverity,
            'description' => trim($this->issueDescription),
            'status' => IssueStatus::Open->value,
        ]);

        $this->notice = __('app.runs.issue_raised');

        $this->skipIssue();
    }

    public function skipIssue(): void
    {
        $this->issueItemId = null;
        $this->issueDescription = '';
        $this->issueSeverity = IssueSeverity::Medium->value;

        $this->dispatch('close-modal', name: 'item-issue');
    }

    // ── Notes / used parts autosave ─────────────────────────────────

    public function updatedNotes(): void
    {
        $this->authorize('update', $this->run);

        $this->notes = mb_substr($this->notes, 0, 5000);

        $this->ensureStarted();
        $this->run->update(['notes' => $this->notes]);

        $this->notesSaved = true;
    }

    /** Stepper wrote qty.{runPartId} — persist that one part immediately. */
    public function updatedQty(mixed $value, string $key): void
    {
        $this->authorize('update', $this->run);

        /** @var ChecklistRunPart|null $part */
        $part = $this->run->runParts()->whereKey((int) $key)->first();

        if ($part === null) {
            return;
        }

        $qtyUsed = is_numeric($value) ? (float) $value : 0.0;
        $qtyUsed = max(0.0, min($qtyUsed, 999999.99)); // decimal(8,2)

        $this->ensureStarted();

        // Absolute quantity, not an increment — idempotent under offline replay.
        $part->update(['qty_used' => $qtyUsed]);
    }

    // ── Photos ──────────────────────────────────────────────────────

    /** A per-item photo finished uploading — persist it as an Attachment. */
    public function updatedItemPhotos(mixed $value, string $key): void
    {
        $this->authorize('update', $this->run);

        $item = $this->freshItem((int) $key);

        if ($item === null || ! $value instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate(['itemPhotos.'.$key => $this->photoRules(false)]);

        $this->ensureStarted();
        $this->storeAttachment($value, $item);

        unset($this->itemPhotos[$key]);
    }

    /** A run-level photo finished uploading. */
    public function updatedRunPhoto(): void
    {
        $this->authorize('update', $this->run);

        if (! $this->runPhoto instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate(['runPhoto' => $this->photoRules(false)]);

        $this->ensureStarted();
        $this->storeAttachment($this->runPhoto, $this->run);

        $this->runPhoto = null;
    }

    public function removeAttachment(int $attachmentId): void
    {
        $this->authorize('update', $this->run);

        $attachment = Attachment::query()->find($attachmentId);

        if ($attachment === null) {
            return; // already gone — removing twice is a harmless no-op
        }

        // Only attachments belonging to THIS run (or one of its items) may be
        // removed here — the id arrives from the client and is not trusted.
        $belongsToRun = $attachment->attachable_type === $this->run->getMorphClass()
            && (int) $attachment->attachable_id === (int) $this->run->id;

        $belongsToItem = $attachment->attachable_type === (new ChecklistRunItem)->getMorphClass()
            && $this->run->items()->whereKey($attachment->attachable_id)->exists();

        abort_unless($belongsToRun || $belongsToItem, 403);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    // ── Submission ──────────────────────────────────────────────────

    /**
     * Submit tapped. If any REQUIRED item is still pending, name exactly
     * which ones instead of submitting; otherwise open the confirm dialog.
     */
    public function attemptSubmit(): void
    {
        $this->authorize('update', $this->run);

        // Check completeness against the database, not the rendered page.
        $this->run->unsetRelation('items');

        if ($this->run->missing_required_items->isNotEmpty()) {
            $this->showMissing = true;

            return;
        }

        $this->showMissing = false;
        $this->dispatch('open-modal', name: 'confirm-submit');
    }

    /**
     * Sign and submit. The signature arrives as an ACTION ARGUMENT, not a
     * bound property: a ~20 KB PNG data URL as a public property would ride
     * in the Livewire snapshot on every request, in both directions, for the
     * whole time the sheet is open. The confirmation secret is passed the
     * same way so it is never held in component state (and so never echoed
     * back to the browser in the next snapshot).
     */
    public function submit(string $signature = '', string $confirmation = ''): void
    {
        $this->run->unsetRelation('items');

        // Someone may have undone an item since the confirm dialog opened.
        if ($this->run->missing_required_items->isNotEmpty()) {
            $this->showMissing = true;
            $this->dispatch('close-modal', name: 'confirm-submit');

            return;
        }

        // Policy: run.submit permission + editable status + completeness
        // + operator machine scope.
        $this->authorize('submit', $this->run);

        /** @var User $user */
        $user = auth()->user();

        $this->resetErrorBag(['signature', 'confirmation']);

        if ($signature === '') {
            $this->addError('signature', __('app.runs.signature_required'));

            return;
        }

        // Not a "the pad was empty" case — the payload is not a PNG we are
        // willing to store. Say so differently, or the operator signs again
        // and again against an error that is not about their signing.
        if (! SignatureImage::isValid($signature)) {
            $this->addError('signature', __('app.runs.signature_invalid'));

            return;
        }

        if (! $this->confirmIdentity($user, $confirmation)) {
            return;
        }

        // A template that does not require sign-off has nobody to route to,
        // so submission completes it outright (seed-notes §D10). One that
        // does goes to the supervisor queue as `submitted`.
        $needsSignoff = (bool) $this->run->loadMissing('template')->template->requires_supervisor_signoff;

        DB::transaction(function () use ($needsSignoff, $signature, $user): void {
            $superseded = $this->run->operator_signature_path;

            $this->run->update([
                'status' => $needsSignoff ? RunStatus::Submitted : RunStatus::Approved,
                'submitted_at' => now(), // server clock — never the client's
                // The signature identifies who attests to the work, so the
                // signer becomes the operator of record even if someone else
                // started the sheet.
                'operator_id' => $user->id,
                'operator_signature_path' => SignatureImage::store($signature, $this->run, 'operator'),
                'operator_signed_at' => now(),
            ]);

            // A rejected run being re-signed supersedes its earlier image.
            SignatureImage::delete($superseded);
        });

        $this->dispatch('close-modal', name: 'confirm-submit');

        session()->flash('status', $needsSignoff
            ? __('app.runs.submitted_message')
            : __('app.runs.submitted_no_signoff_message'));

        // Back to the machine's run list on a kiosk tablet; an office user
        // who signed a run with their password has no device cookie and
        // would land on the "this tablet is not enrolled" screen.
        if (session()->has('kiosk.device_id')) {
            $this->redirectRoute('kiosk.machine', [
                'code' => $this->run->loadMissing('machine')->machine->code,
            ]);

            return;
        }

        $this->redirectRoute('runs.index');
    }

    /**
     * Re-confirm the signer's identity, rate-limited per run and user with
     * the same lockout the kiosk PIN pad uses. On a shared tablet the session
     * alone proves nothing about who is holding it right now.
     */
    private function confirmIdentity(User $user, string $confirmation): bool
    {
        $method = $this->confirmationMethod;

        if ($method === 'none') {
            return true; // no PIN and no password — nothing to check against
        }

        $key = 'run-sign:'.$this->run->id.':'.$user->id;
        $maxAttempts = (int) config('checklists.pin_max_attempts');
        $lockoutSeconds = (int) config('checklists.pin_lockout_minutes') * 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->addError('confirmation', __('app.kiosk.pin_locked', [
                'minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60)),
            ]));

            return false;
        }

        $secret = $method === 'pin' ? $user->pin : $user->password;

        if ($confirmation === '' || ! Hash::check($confirmation, (string) $secret)) {
            RateLimiter::hit($key, $lockoutSeconds);

            // The attempt is logged; the value tried never is.
            activity('run')
                ->performedOn($this->run)
                ->causedBy($user)
                ->withProperties(['method' => $method, 'ip' => request()->ip()])
                ->log('run.sign_confirmation_failed');

            $this->addError('confirmation', $method === 'pin'
                ? __('app.kiosk.pin_incorrect')
                : __('app.runs.password_incorrect'));

            return false;
        }

        RateLimiter::clear($key);

        return true;
    }

    // ── Internals ───────────────────────────────────────────────────

    /**
     * First interaction flips pending → in_progress exactly once, with a
     * server-side started_at.
     */
    private function ensureStarted(): void
    {
        if ($this->run->status !== RunStatus::Pending) {
            return;
        }

        $this->run->update([
            'status' => RunStatus::InProgress,
            'started_at' => now(),
            // Not spelled out in the contract: the first user to touch the
            // run is recorded as its operator so listings show who is on it.
            // Milestone 5's signature + PIN step re-confirms identity.
            'operator_id' => $this->run->operator_id ?? auth()->id(),
        ]);
    }

    /** Fetch the item fresh, scoped to THIS run (the id is client input). */
    private function freshItem(int $itemId): ?ChecklistRunItem
    {
        /** @var ChecklistRunItem|null */
        return $this->run->items()->with('templateItem')->find($itemId);
    }

    /**
     * Two operators can have the same run open. If the database no longer
     * matches what this operator's row showed, never overwrite silently —
     * surface who answered it and let this render sync the row.
     */
    private function isConflicted(ChecklistRunItem $item, string $expectedStatus): bool
    {
        if ($item->status->value === $expectedStatus) {
            return false;
        }

        $item->loadMissing('completedBy');

        $this->conflictNotice = __('app.runs.answered_by_other', [
            'name' => $item->completedBy?->full_name ?? __('app.runs.another_user'),
        ]);

        return true;
    }

    /** Absolute reset to pending — safe to replay (offline queue, milestone 8). */
    private function resetItem(ChecklistRunItem $item): void
    {
        $item->update([
            'status' => RunItemStatus::Pending,
            'value_numeric' => null,
            'value_text' => null,
            'fail_reason' => null,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    private function storeAttachment(TemporaryUploadedFile $file, ChecklistRun|ChecklistRunItem $attachable): void
    {
        $disk = (string) config('filesystems.default', 'public');

        $attachable->attachments()->create([
            'disk' => $disk,
            'path' => $file->store('run-attachments/'.$this->run->id, $disk),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function closeItemModals(): void
    {
        $this->actionItemId = null;
        $this->actionItemExpectedStatus = '';
        $this->failReason = '';
        $this->failPhoto = null;

        $this->dispatch('close-modal', name: 'item-actions');
        $this->dispatch('close-modal', name: 'item-fail');
    }

    /**
     * @return list<string>
     */
    private function photoRules(bool $required): array
    {
        return [
            $required ? 'required' : 'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:10240', // 10 MB — plant tablets take large photos
        ];
    }
}
