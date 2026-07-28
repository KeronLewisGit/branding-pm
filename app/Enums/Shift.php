<?php

declare(strict_types=1);

namespace App\Enums;

enum Shift: string
{
    case Day = 'day';
    case Night = 'night';
    case All = 'all';

    public function label(): string
    {
        return __('app.shift.'.$this->value);
    }

    /**
     * @return array<string, string> value => label, for select boxes.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
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
