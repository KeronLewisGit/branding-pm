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

    /**
     * The backing values, in declaration order.
     *
     * Chiefly for the query layer: an `ORDER BY` that ranks a status column
     * should be derived from the enum, never re-typed as a list of strings in
     * a SQL fragment where nothing keeps the two in step.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
