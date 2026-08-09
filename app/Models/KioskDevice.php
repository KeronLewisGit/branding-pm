<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KioskDeviceKind;
use App\Support\DeviceReport;
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
        'device_info',
        'enrolled_at',
        'enrolled_by_id',
        'enrolled_ip',
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
            'enrolled_at' => 'datetime',
            'device_info' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * What the last request from this device claimed to be, or Unknown if it
     * has never been used. Display only — see KioskDeviceKind::matches().
     */
    /**
     * Who turned this browser into a kiosk. Null for devices enrolled by
     * signed link, where nobody was authenticated on the tablet.
     */
    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by_id');
    }

    /**
     * A short human label for what the hardware appears to be.
     */
    public function deviceSummary(): string
    {
        return DeviceReport::summarise($this->device_info);
    }

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
