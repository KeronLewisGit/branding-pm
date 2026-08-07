<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KioskDeviceKind;
use App\Support\DeviceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskDevice extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'kind',
        'token',
        'location_id',
        'last_seen_at',
        'last_user_agent',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => KioskDeviceKind::class,
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * What the last request from this device claimed to be, or Unknown if it
     * has never been used. Display only — see KioskDeviceKind::matches().
     */
    public function detectedType(): DeviceType
    {
        return DeviceType::detect($this->last_user_agent);
    }

    /**
     * True when this device has been used from hardware that does not look
     * like what an administrator recorded it as.
     */
    public function kindLooksWrong(): bool
    {
        return $this->last_user_agent !== null
            && ! $this->kind->matches($this->detectedType());
    }

    // ── Relationships ────────────────────────────────────────────────

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
