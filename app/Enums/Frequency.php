<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Frequency: string
{
    use HasOptions;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case OnDemand = 'on_demand';

    public function label(): string
    {
        return __('app.frequency.'.$this->value);
    }
}
