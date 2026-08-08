<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Shift: string
{
    use HasOptions;

    case Day = 'day';
    case Night = 'night';
    case All = 'all';

    public function label(): string
    {
        return __('app.shift.'.$this->value);
    }

    /**
     * True when the run belongs to a specific shift.
     * `All` means the template is not shift-split (BUILD-CONTRACT §0.1).
     */
    public function isSplit(): bool
    {
        return $this !== self::All;
    }
}
