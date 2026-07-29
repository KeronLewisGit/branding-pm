<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Issue extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'checklist_run_id',
        'checklist_run_item_id',
        'machine_id',
        'raised_by',
        'severity',
        'description',
        'status',
        'assigned_to',
        'resolved_at',
        'resolution_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('issue')
            ->logOnly([
                'machine_id',
                'severity',
                'status',
                'assigned_to',
                'resolved_at',
                'resolution_notes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ── Relationships ────────────────────────────────────────────────

    public function run(): BelongsTo
    {
        return $this->belongsTo(ChecklistRun::class, 'checklist_run_id');
    }

    public function runItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistRunItem::class, 'checklist_run_item_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    /**
     * Issues not yet resolved or closed.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn(
            'status',
            array_map(fn (IssueStatus $status): string => $status->value, IssueStatus::openStatuses()),
        );
    }
}
