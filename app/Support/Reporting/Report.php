<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use Illuminate\Support\Collection;

/**
 * One report. Deliberately narrow: a report knows its columns and how to
 * produce rows for a filter set, and nothing about HTML, CSV or PDF.
 *
 * That is what lets the on-screen table, the CSV and the PDF share a single
 * source of numbers — an auditor comparing an exported CSV against the screen
 * must never find them disagreeing.
 */
interface Report
{
    /** Stable key used in URLs and the report picker. */
    public function key(): string;

    public function title(): string;

    /** One-line explanation shown under the title. */
    public function description(): string;

    /**
     * Column key => header label, in display order.
     *
     * @return array<string, string>
     */
    public function columns(): array;

    /**
     * Rows as plain arrays keyed by column key. Values are scalars already
     * formatted for display — the CSV and the PDF print them verbatim.
     *
     * @return Collection<int, array<string, string|int|float|null>>
     */
    public function rows(ReportFilters $filters): Collection;

    /**
     * Optional totals row, keyed the same way as columns(). Empty when the
     * report does not total meaningfully.
     *
     * @return array<string, string|int|float|null>
     */
    public function totals(ReportFilters $filters): array;
}
