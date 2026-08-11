<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Frequency;
use App\Enums\WorkCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ChecklistTemplate extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'machine_id',
        'name',
        'work_category',
        'work_description',
        'frequency',
        'per_shift',
        'weekly_weekday',
        'monthly_day',
        'requires_supervisor_signoff',
        'grace_period_hours',
        'version',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_category' => WorkCategory::class,
            'frequency' => Frequency::class,
            'per_shift' => 'boolean',
            'weekly_weekday' => 'integer',
            'monthly_day' => 'integer',
            'requires_supervisor_signoff' => 'boolean',
            'grace_period_hours' => 'integer',
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('template')
            ->logOnly([
                'machine_id',
                'name',
                'work_category',
                'frequency',
                'per_shift',
                'weekly_weekday',
                'monthly_day',
                'requires_supervisor_signoff',
                'grace_period_hours',
                'version',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ── Relationships ────────────────────────────────────────────────

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('sort_order');
    }

    /**
     * Only items still on the form — the generator copies these into runs.
     */
    public function activeItems(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }


    public function runs(): HasMany
    {
        return $this->hasMany(ChecklistRun::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Increment the template version. Called whenever items change so
     * historical runs stay tied to what was actually asked (SPEC §Template
     * management).
     */
    public function bumpVersion(): void
    {
        $this->increment('version');
    }

    /**
     * Frequency rule ONLY (BUILD-CONTRACT §7 point 3):
     * daily → every day, weekly → ISO weekday matches `weekly_weekday`,
     * monthly → day-of-month matches `monthly_day`, on_demand → never.
     *
     * Working-day and holiday filtering deliberately does NOT live here —
     * it belongs to the `checklists:generate` command
     * (App\Console\Commands\GenerateChecklistRuns), which consults
     * Site::isWorkingDay() for the machine's site.
     */
    public function isDueOn(CarbonInterface $date): bool
    {
        return match ($this->frequency) {
            Frequency::Daily => true,
            Frequency::Weekly => $date->isoWeekday() === $this->weekly_weekday,
            Frequency::Monthly => $date->day === $this->monthly_day,
            Frequency::OnDemand => false,
        };
    }
}
