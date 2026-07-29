<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use Illuminate\Support\Collection;

/**
 * The list of available reports, resolved by key. The viewer, the CSV
 * endpoint and the PDF endpoint all resolve through here, so a report key
 * that works on screen works in an export by construction.
 */
final class ReportRegistry
{
    /** @var array<string, class-string<Report>> */
    private const REPORTS = [
        'compliance' => ComplianceReport::class,
        'missed' => MissedChecksReport::class,
        'parts' => PartsUsageReport::class,
        'operators' => OperatorActivityReport::class,
    ];

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::REPORTS);
    }

    /**
     * The report for a key, or the compliance report — never an exception
     * from a stale bookmark or a hand-edited query string.
     */
    public static function make(string $key): Report
    {
        $class = self::REPORTS[$key] ?? ComplianceReport::class;

        return new $class;
    }

    public static function defaultKey(): string
    {
        return 'compliance';
    }

    /**
     * @return Collection<int, Report>
     */
    public static function all(): Collection
    {
        return collect(self::REPORTS)->map(fn (string $class): Report => new $class)->values();
    }
}
