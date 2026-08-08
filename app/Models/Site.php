<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'working_days',
        'timezone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'working_days' => 'array',
        ];
    }

    /**
     * ISO-8601 weekday numbers (1 = Monday … 7 = Sunday).
     * Defaults to Mon–Sat when the column is null.
     *
     * @return list<int>
     */
    public function getWorkingDaysAttribute(?string $value): array
    {
        if ($value === null) {
            return [1, 2, 3, 4, 5, 6];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [1, 2, 3, 4, 5, 6];
        }

        return array_map('intval', $decoded);
    }

    // ── Relationships ────────────────────────────────────────────────

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'default_site_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * True when $date is a working day for this site: its ISO weekday is in
     * `working_days` AND no holiday applies. Holidays with `site_id = null`
     * are site-wide; recurring holidays match on month + day of any year.
     */
    public function isWorkingDay(CarbonInterface $date): bool
    {
        if (! in_array($date->isoWeekday(), $this->working_days, true)) {
            return false;
        }

        $isHoliday = Holiday::query()
            ->where(function ($query) {
                $query->where('site_id', $this->id)
                    ->orWhereNull('site_id');
            })
            ->where(function ($query) use ($date) {
                $query->where('date', $date->toDateString())
                    ->orWhere(function ($query) use ($date) {
                        $query->where('is_recurring', true)
                            ->whereMonth('date', $date->month)
                            ->whereDay('date', $date->day);
                    });
            })
            ->exists();

        return ! $isHoliday;
    }
}
