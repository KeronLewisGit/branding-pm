<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Enums\Shift;
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

    public function scopeDueOn(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->whereDate('scheduled_for', $date instanceof CarbonInterface ? $date->toDateString() : $date);
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
