<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * `value => label` for a `<select>`, for any backed enum with a `label()`.
 *
 * All nine enums carried a byte-identical copy of this loop. Nine copies of
 * eight lines is not a bug today, but it is the shape that produces one: the
 * next person to change how options are built changes the copy in front of
 * them, and the other eight quietly disagree.
 */
trait HasOptions
{
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
