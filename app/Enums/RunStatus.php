<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RunStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Missed = 'missed';

    public function label(): string
    {
        return __('app.status.'.$this->value);
    }

    /**
     * A run can still be worked on by the operator in these states
     * (rejected runs are bounced back for correction).
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Pending, self::InProgress, self::Rejected => true,
            default => false,
        };
    }

    /**
     * Tailwind colour token for the status dot (BUILD-CONTRACT §8).
     * Never rely on this colour alone — always pair with a text label or icon.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'status-pending',
            self::InProgress => 'status-in_progress',
            self::Submitted => 'status-submitted',
            self::Approved => 'status-approved',
            self::Rejected => 'status-rejected',
            self::Missed => 'status-missed',
        };
    }
}
