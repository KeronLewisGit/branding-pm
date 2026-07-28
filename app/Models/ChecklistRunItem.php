<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResponseType;
use App\Enums\RunItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ChecklistRunItem extends Model
{
    use HasFactory;
    use LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'checklist_run_id',
        'checklist_template_item_id',
        'sort_order',
        'description',
        'response_type',
        'is_required',
        'status',
        'value_numeric',
        'value_text',
        'fail_reason',
        'completed_at',
        'completed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'response_type' => ResponseType::class,
            'is_required' => 'boolean',
            'status' => RunItemStatus::class,
            'value_numeric' => 'decimal:3',
            'completed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('run_item')
            ->logOnly([
                'status',
                'value_numeric',
                'value_text',
                'fail_reason',
                'completed_at',
                'completed_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ── Relationships ────────────────────────────────────────────────

    public function run(): BelongsTo
    {
        return $this->belongsTo(ChecklistRun::class, 'checklist_run_id');
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplateItem::class, 'checklist_template_item_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function issue(): HasOne
    {
        return $this->hasOne(Issue::class, 'checklist_run_item_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────
    //
    // `completed_at` is ALWAYS the server clock (BUILD-CONTRACT §8):
    // never accept a client-supplied timestamp.

    public function markDone(User $user): void
    {
        $this->status = RunItemStatus::Done;
        $this->completed_at = now();
        $this->completed_by = $user->id;
        $this->save();
    }

    public function markNotApplicable(User $user): void
    {
        $this->status = RunItemStatus::NotApplicable;
        $this->completed_at = now();
        $this->completed_by = $user->id;
        $this->save();
    }

    public function markFailed(User $user, string $reason): void
    {
        $this->status = RunItemStatus::Failed;
        $this->fail_reason = $reason;
        $this->completed_at = now();
        $this->completed_by = $user->id;
        $this->save();
    }
}
