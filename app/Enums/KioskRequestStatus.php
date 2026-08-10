<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where an operator's request for a kiosk has got to.
 *
 * `approved` and `claimed` are separate states because they happen on two
 * different machines: a supervisor approves from wherever they are, and the
 * asking browser redeems it later. Between the two the device exists and
 * nothing is using it, which is exactly what the review screen must be able
 * to show.
 */
enum KioskRequestStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';
    case Claimed = 'claimed';

    public function label(): string
    {
        return __('app.kiosk_requests.status_'.$this->value);
    }

    /** Colour token for the status dot. Never the only cue — always paired. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'bg-status-pending',
            self::Approved => 'bg-status-submitted',
            self::Declined => 'bg-status-rejected',
            self::Claimed => 'bg-status-approved',
        };
    }

    /** Still waiting on somebody. */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
