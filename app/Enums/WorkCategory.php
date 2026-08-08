<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum WorkCategory: string
{
    use HasOptions;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case General = 'general';

    public function label(): string
    {
        return __('app.work_category.'.$this->value);
    }
}
