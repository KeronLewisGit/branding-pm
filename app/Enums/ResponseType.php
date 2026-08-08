<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ResponseType: string
{
    use HasOptions;

    case Check = 'check';
    case PassFail = 'pass_fail';
    case Numeric = 'numeric';
    case Text = 'text';

    public function label(): string
    {
        return __('app.response_type.'.$this->value);
    }
}
