<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum IssueSeverity: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Breakdown = 'breakdown';

    public function label(): string
    {
        return __('app.issue_severity.'.$this->value);
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

    /**
     * Values in queue order, most urgent first — `rank()`, for the query
     * layer, so a list can be sorted without re-typing the order in SQL.
     *
     * @return list<string>
     */
    public static function mostUrgentFirst(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b): int => $a->rank() <=> $b->rank());

        return array_map(fn (self $case): string => $case->value, $cases);
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
