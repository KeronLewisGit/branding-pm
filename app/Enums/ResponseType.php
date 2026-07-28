<?php

declare(strict_types=1);

namespace App\Enums;

enum ResponseType: string
{
    case Check = 'check';
    case PassFail = 'pass_fail';
    case Numeric = 'numeric';
    case Text = 'text';

    public function label(): string
    {
        return __('app.response_type.'.$this->value);
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
