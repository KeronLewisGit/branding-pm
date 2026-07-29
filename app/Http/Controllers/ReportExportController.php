<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Reporting\Report;
use App\Support\Reporting\ReportFilters;
use App\Support\Reporting\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV and PDF for any report (milestone 7).
 *
 * Both formats render the SAME rows the viewer shows, through the same
 * Report object and the same ReportFilters — including the machine scope, so
 * an export can never widen what its requester may see.
 *
 * Gated on `export.data` (maintenance manager and admin). `report.view`
 * alone shows the numbers on screen; taking them out of the building is a
 * separate grant, which is what the permission split in contract §5 is for.
 */
class ReportExportController extends Controller
{
    public function csv(Request $request, string $report): StreamedResponse
    {
        [$definition, $filters] = $this->resolve($request, $report);

        $columns = $definition->columns();
        $rows = $definition->rows($filters);
        $totals = $definition->totals($filters);

        $filename = $this->filename($definition, $filters, 'csv');

        return response()->streamDownload(function () use ($columns, $rows, $totals, $definition, $filters): void {
            $handle = fopen('php://output', 'wb');

            // Excel on Windows reads a bare UTF-8 CSV as Latin-1 and mangles
            // the machine names; the BOM is what makes it open correctly.
            fwrite($handle, "\xEF\xBB\xBF");

            // Two header lines of provenance: a CSV that leaves the building
            // must say what it is and what window it covers.
            fputcsv($handle, [$definition->title()]);
            fputcsv($handle, [__('app.reports.window'), $filters->label()]);
            fputcsv($handle, []);

            fputcsv($handle, array_values($columns));

            foreach ($rows as $row) {
                fputcsv($handle, $this->orderRow($row, $columns));
            }

            if ($totals !== []) {
                fputcsv($handle, $this->orderRow($totals, $columns));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request, string $report): Response
    {
        [$definition, $filters] = $this->resolve($request, $report);

        $pdf = Pdf::loadView('pdf.report', [
            'report' => $definition,
            'filters' => $filters,
            'columns' => $definition->columns(),
            'rows' => $definition->rows($filters),
            'totals' => $definition->totals($filters),
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($this->filename($definition, $filters, 'pdf'));
    }

    /**
     * @return array{0: Report, 1: ReportFilters}
     */
    private function resolve(Request $request, string $report): array
    {
        abort_unless($request->user()?->can('export.data'), 403);

        // An unknown key falls back to the default report rather than 404 —
        // see ReportRegistry::make().
        return [
            ReportRegistry::make($report),
            ReportFilters::fromRequest($request, $request->user()),
        ];
    }

    /**
     * Columns are printed in the order columns() declares, not the order the
     * row array happens to be built in.
     *
     * @param  array<string, string|int|float|null>  $row
     * @param  array<string, string>  $columns
     * @return list<string|int|float|null>
     */
    private function orderRow(array $row, array $columns): array
    {
        return array_map(fn (string $key) => $row[$key] ?? '', array_keys($columns));
    }

    private function filename(Report $report, ReportFilters $filters, string $extension): string
    {
        return sprintf(
            '%s-%s-to-%s.%s',
            Str::slug($report->key()),
            $filters->from->toDateString(),
            $filters->to->toDateString(),
            $extension,
        );
    }
}
