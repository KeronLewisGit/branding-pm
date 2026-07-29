{{--
    Generic report PDF. Every report declares its own columns, so this one
    template serves all four — and prints exactly the rows the viewer showed.

    dompdf: tables only, inline CSS, no external assets.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        @page { margin: 12mm 10mm 16mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #0f172a; }
        h1 { font-size: 13pt; margin: 0 0 1mm; }
        .sub { color: #475569; font-size: 8.5pt; margin: 0 0 1mm; }
        .window { font-size: 9pt; font-weight: bold; margin: 0 0 4mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.4pt solid #94a3b8; padding: 1.2mm 2mm; text-align: left; }
        th { background: #f1f5f9; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #475569; }
        tr:nth-child(even) td { background: #f8fafc; }
        .totals td { font-weight: bold; background: #e2e8f0 !important; }
        .empty { color: #64748b; padding: 6mm 0; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7pt; color: #64748b; border-top: 0.3pt solid #cbd5e1; padding-top: 1.5mm; }
        .footer .right { float: right; }
    </style>
</head>
<body>

    <h1>{{ $report->title() }}</h1>
    <p class="sub">{{ $report->description() }}</p>
    <p class="window">{{ __('app.reports.window') }}: {{ $filters->label() }}</p>

    @if ($rows->isEmpty())
        <p class="empty">{{ __('app.reports.no_rows') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($columns as $key => $header)
                            <td>{{ $row[$key] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @if (! empty($totals))
                    <tr class="totals">
                        @foreach ($columns as $key => $header)
                            <td>{{ $totals[$key] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    <div class="footer">
        <span class="right">{{ __('app.reports.generated_at', ['at' => $generatedAt->timezone(config('app.display_timezone', 'UTC'))->format('j M Y, g:i A')]) }}</span>
        {{ config('app.name', 'Branding PM') }} · {{ $report->title() }}
    </div>

</body>
</html>
