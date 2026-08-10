<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KioskRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An operator asking for the browser in front of them to become a kiosk.
 *
 * See the migration for why this exists rather than simply granting operators
 * `kiosk.activate`, and why `claim_token` is the piece that makes an approval
 * usable on a machine the approver was never sitting at.
 */
class KioskEnrolmentRequest extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'requested_by_id',
        'claim_token',
        'suggested_name',
        'note',
        'device_info',
        'user_agent',
        'requested_ip',
        'status',
        'reviewed_by_id',
        'reviewed_at',
        'decline_reason',
        'kiosk_device_id',
        'claimed_at',
    ];

    /**
     * The token is a bearer credential for one enrolment — it never belongs in
     * a payload, a log or an export.
     *
     * @var list<string>
     */
    protected $hidden = [
        'claim_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => KioskRequestStatus::class,
            'device_info' => 'array',
            'reviewed_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('kiosk')
            ->logOnly(['status', 'reviewed_by_id', 'kiosk_device_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ── Relations ────────────────────────────────────────────────────

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(KioskDevice::class, 'kiosk_device_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', KioskRequestStatus::Pending->value);
    }

    /**
     * Approved and not yet redeemed — what a claim looks for.
     */
    public function scopeClaimable(Builder $query): Builder
    {
        return $query->where('status', KioskRequestStatus::Approved->value)
            ->whereNotNull('kiosk_device_id');
    }

    // ── Behaviour ────────────────────────────────────────────────────

    /**
     * A token for the asking browser to redeem later.
     *
     * 64 characters from Str::random, the same shape and source as
     * kiosk_devices.token, so the two are equally hard to guess.
     */
    public static function newClaimToken(): string
    {
        return Str::random(64);
    }
}
