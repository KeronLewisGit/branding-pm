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
}
