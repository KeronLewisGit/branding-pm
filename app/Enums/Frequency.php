<?php

declare(strict_types=1);

namespace App\Enums;

enum Frequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case OnDemand = 'on_demand';

    public function label(): string
    {
        return __('app.frequency.'.$this->value);
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
