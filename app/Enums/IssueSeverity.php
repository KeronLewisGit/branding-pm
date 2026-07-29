<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Breakdown = 'breakdown';

    public function label(): string
    {
        return __('app.issue_severity.'.$this->value);
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
     * Queue order: a breakdown stops production, so it outranks everything.
     * Lower sorts first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Breakdown => 0,
            self::High => 1,
            self::Medium => 2,
            self::Low => 3,
        };
    }

    /** Badge token — always shown with the label, never colour alone. */
    public function color(): string
    {
        return match ($this) {
            self::Breakdown => 'red',
            self::High => 'rose',
            self::Medium => 'amber',
            self::Low => 'slate',
        };
    }
}
