<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Enums\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ChecklistRun extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'checklist_template_id',
        'machine_id',
        'template_version',
        'scheduled_for',
        'shift',
        'status',
        'started_at',
        'submitted_at',
        'operator_id',
        'operator_signature_path',
        'operator_signed_at',
        'supervisor_id',
        'supervisor_signature_path',
        'supervisor_signed_at',
        'supervisor_comment',
        'qa_verified_by',
        'qa_verified_at',
        'qa_comment',
        'notes',
        'downtime_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_version' => 'integer',
            'scheduled_for' => 'date',
            'shift' => Shift::class,
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'qa_verified_at' => 'datetime',
            'operator_signed_at' => 'datetime',
            'supervisor_signed_at' => 'datetime',
            'downtime_minutes' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('run')
            ->logOnly([
                'status',
                'started_at',
                'submitted_at',
                'operator_id',
                'operator_signed_at',
                'supervisor_id',
                'supervisor_signed_at',
                'supervisor_comment',
                'downtime_minutes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ── Relationships ────────────────────────────────────────────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'checklist_template_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistRunItem::class)->orderBy('sort_order');
    }

    public function runParts(): HasMany
    {
        return $this->hasMany(ChecklistRunPart::class)->orderBy('sort_order');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * The Quality Assurance officer who verified the completed work — a
     * third, separate sign-off after the supervisor's approval.
     */
    public function qaVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qa_verified_by');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    /**
     * Runs scheduled for a given day.
     *
     * Plain `where`, deliberately, not `whereDate`. `scheduled_for` is a MySQL
     * `DATE` column, so `date(scheduled_for)` is the identity function — it
     * changes no result, and it makes the predicate non-sargable: MySQL cannot
     * use `checklist_runs_scheduled_for_index` through a function call. On the
     * pilot data that is the difference between an index lookup reading 19
     * rows and a full scan of the table, and this is the busiest table there
     * is. The date is normalised here so the binding always matches the column
     * type.
     */
    public function scopeDueOn(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->where('scheduled_for', $date instanceof CarbonInterface ? $date->toDateString() : $date);
    }

    public function scopeForMachine(Builder $query, Machine|int $machine): Builder
    {
        return $query->where('machine_id', $machine instanceof Machine ? $machine->id : $machine);
    }

    /**
     * Runs an operator can still work on: pending, in progress or rejected.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RunStatus::Pending->value,
            RunStatus::InProgress->value,
            RunStatus::Rejected->value,
        ]);
    }

    /**
     * Open runs from before `$date` — work an operator can still rescue.
     *
     * `in_progress` and `rejected` ONLY, deliberately narrower than `open()`:
     *
     * - `pending` is excluded because `checklists:mark-missed` flips every
     *   untouched pending run to `missed` once its grace period expires. An
     *   old pending run is therefore a run the hourly command has not reached
     *   yet, not work anybody is waiting on.
     * - `missed` is excluded because a gap in the record IS the record.
     *   Re-opening one weeks later would rewrite a compliance figure that has
     *   already been reported.
     *
     * What is left is the work that genuinely strands: a sheet somebody
     * started and never signed, and a sheet a supervisor sent back for
     * rework. Both are invisible on the kiosk without this.
     */
    public function scopeOverdueOpenBefore(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query
            ->where('scheduled_for', '<', $date instanceof CarbonInterface ? $date->toDateString() : $date)
            ->whereIn('status', [
                RunStatus::InProgress->value,
                RunStatus::Rejected->value,
            ]);
    }

    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('status', RunStatus::Submitted->value);
    }

    public function scopeStatus(Builder $query, RunStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof RunStatus ? $status->value : $status);
    }

    // ── Accessors ────────────────────────────────────────────────────

    /**
     * How many days after its scheduled day this sheet was signed off, or
     * null when it was signed on time or is not signed at all.
     *
     * DERIVED, never stored, and that is the point: the late note on a sheet
     * has to be one an operator cannot edit away, and the surest way to get
     * that is for there to be nothing to edit. `scheduled_for` is set by the
     * generator and `submitted_at` by the server clock at submission; an
     * operator can reach neither, so the note cannot disagree with the record
     * it describes. A stored flag would be one more column to keep true.
     *
     * The two dates are handled differently ON PURPOSE, matching
     * ChecksCompletedReport: `scheduled_for` is a calendar date cast to
     * midnight UTC and must NOT be shifted into the plant zone — doing so
     * reads every run a day early in UTC-4. `submitted_at` is a real instant
     * and MUST be, or a sheet signed at 8pm on the due day counts as late.
     */
    public function completedLateByDays(): ?int
    {
        if ($this->submitted_at === null || $this->scheduled_for === null) {
            return null;
        }

        $tz = (string) config('app.display_timezone', 'UTC');

        $due = CarbonImmutable::parse($this->scheduled_for->toDateString());
        $signed = CarbonImmutable::parse($this->submitted_at->timezone($tz)->toDateString());

        return $signed->greaterThan($due) ? (int) $due->diffInDays($signed) : null;
    }

    /**
     * Progress for the "7 of 9" indicator. `done` counts every item that is
     * no longer pending (done, N/A or failed all count as answered).
     *
     * @return array{done: int, total: int}
     */
    public function getProgressAttribute(): array
    {
        $items = $this->loadMissing('items')->items;

        return [
            'done' => $items->filter(
                fn (ChecklistRunItem $item): bool => $item->status !== RunItemStatus::Pending
            )->count(),
            'total' => $items->count(),
        ];
    }

    /**
     * True when no REQUIRED item is still pending — the submit gate.
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->missing_required_items->isEmpty();
    }

    /**
     * The required items still pending, so the run form can name exactly
     * which ones block submission (BUILD-CONTRACT §9.4).
     *
     * @return Collection<int, ChecklistRunItem>
     */
    public function getMissingRequiredItemsAttribute(): Collection
    {
        return $this->loadMissing('items')->items->filter(
            fn (ChecklistRunItem $item): bool => $item->is_required
                && $item->status === RunItemStatus::Pending
        )->values();
    }

    /**
     * Shift label for lists and headers. Runs with shift `all` are not
     * shift-split, so an em dash is shown instead of a label.
     */
    public function getDisplayShiftAttribute(): string
    {
        return $this->shift->isSplit() ? $this->shift->label() : '—';
    }
}
