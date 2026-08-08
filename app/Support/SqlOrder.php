<?php

declare(strict_types=1);

namespace App\Support;

/**
 * `ORDER BY` clauses that rank a column by a known list of values.
 *
 * Three screens each wrote their own version of this by hand, with the status
 * and severity values typed out again inside the SQL string. Two of them were
 * merely duplicates. The third was wrong:
 *
 *     ORDER BY FIELD(status, 'open', 'acknowledged', ...)
 *
 * MySQL's `FIELD()` returns **0** for a value it does not find, and matches
 * start at 1 — so a status added to the enum but not to that string would not
 * sort last, it would sort **first**, above open breakdowns, on the screen a
 * maintenance manager uses to decide what to fix next. Nothing would fail;
 * the list would just quietly be in the wrong order.
 *
 * `rank()` takes the values from the enum and gives unknowns an explicit
 * last place, so neither half of that can happen.
 */
final class SqlOrder
{
    /** Where anything not in the list sorts: after everything that is. */
    private const UNRANKED = 9999;

    /**
     * Rank `$column` by `$values`, first value first.
     *
     * Returns `[sql, bindings]`, so it spreads straight into the builder:
     *
     *     ->orderByRaw(...SqlOrder::rank('status', IssueStatus::values()))
     *
     * @param  list<string>  $values
     * @return array{string, list<string>}
     */
    public static function rank(string $column, array $values): array
    {
        if ($values === []) {
            return ['1', []];
        }

        // The column name is ours, never user input; the values are bound.
        $whens = implode(' ', array_map(
            static fn (int $i): string => "WHEN ? THEN {$i}",
            array_keys($values),
        ));

        return ["CASE {$column} {$whens} ELSE ".self::UNRANKED.' END', array_values($values)];
    }

    /**
     * Rank `$column` so the given values come first, everything else after —
     * a two-way split rather than a full ordering.
     *
     * @param  list<string>  $values
     * @return array{string, list<string>}
     */
    public static function first(string $column, array $values): array
    {
        if ($values === []) {
            return ['1', []];
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return ["CASE WHEN {$column} IN ({$placeholders}) THEN 0 ELSE 1 END", array_values($values)];
    }
}
