<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Machine extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'location_id',
        'code',
        'name',
        'manufacturer',
        'model',
        'asset_tag',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * `/m/{machine}` binds on the QR slug, not the id (BUILD-CONTRACT §4).
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('machine')
            ->logOnly([
                'location_id',
                'code',
                'name',
                'manufacturer',
                'model',
                'asset_tag',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ── Relationships ────────────────────────────────────────────────

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The machine's site, through its location.
     */
    public function site(): HasOneThrough
    {
        return $this->hasOneThrough(
            Site::class,
            Location::class,
            'id',            // locations.id …
            'id',            // … sites.id
            'location_id',   // machines.location_id
            'site_id',       // locations.site_id
        );
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ChecklistTemplate::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ChecklistRun::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Parts normally consumed on this machine, in form order.
     */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'machine_part')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * Operators assigned to this machine.
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_machine')->withTimestamps();
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Accessors ────────────────────────────────────────────────────

    /**
     * True when the machine has an unresolved breakdown-severity issue.
     * Dashboards flag these machines everywhere (SPEC §Issues).
     */
    public function getOpenBreakdownAttribute(): bool
    {
        return $this->issues()
            ->where('severity', IssueSeverity::Breakdown->value)
            ->whereNotIn('status', [IssueStatus::Resolved->value, IssueStatus::Closed->value])
            ->exists();
    }
}
