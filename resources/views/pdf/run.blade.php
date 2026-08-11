{{--
    Per-run PDF — a facsimile of the paper work order (SPEC §"PDF Export").

    Auditors and ISO reviewers expect the familiar sheet, so the layout keeps
    the paper form's order: header block, two-column numbered task list,
    Notes box, then the two signature blocks with printed name, employee
    number and timestamp beneath each image.

    dompdf notes: no flexbox, no grid, no external assets. Layout is tables,
    CSS is inline in this file, and the signature images arrive as data URIs
    from RunPdfController.
--}}
@php
    $items = $run->items->sortBy('sort_order')->values();
    // Two columns, filled down the left then the right, exactly as the
    // printed form reads.
    $half = (int) ceil($items->count() / 2);
    $left = $items->slice(0, $half);
    $right = $items->slice($half);

    $statusLabel = fn ($item) => $item->status->label();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $run->template->name }} — {{ $run->machine->name }}</title>
    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #0f172a; }
        h1 { font-size: 13pt; margin: 0 0 2mm; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1mm 2mm; border: 0.4pt solid #94a3b8; vertical-align: top; }
        .meta .label { width: 22%; background: #f1f5f9; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #475569; }
        .section { margin-top: 5mm; }
        .section-title { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #475569; border-bottom: 0.8pt solid #0f172a; padding-bottom: 1mm; margin-bottom: 2mm; }
        .tasks td { vertical-align: top; width: 50%; padding-right: 3mm; }
        .task { border-bottom: 0.3pt solid #cbd5e1; padding: 1.2mm 0; }
        .task .no { display: inline-block; width: 6mm; font-weight: bold; }
        .task .state { float: right; font-size: 7.5pt; font-weight: bold; }
        .state-failed { color: #b91c1c; }
        .state-na { color: #64748b; }
        .reason { display: block; margin: 0.5mm 0 0 6mm; color: #b91c1c; font-size: 8pt; }
        .value { display: block; margin: 0.5mm 0 0 6mm; color: #334155; font-size: 8pt; }
        .notes { border: 0.4pt solid #94a3b8; min-height: 18mm; padding: 2mm; }
        .sign td { width: 50%; padding: 0 3mm 0 0; vertical-align: top; }
        .sign-box { border: 0.4pt solid #94a3b8; height: 26mm; padding: 1.5mm; text-align: center; }
        .sign-box img { max-height: 20mm; max-width: 100%; }
        .sign-none { color: #94a3b8; font-size: 8pt; padding-top: 8mm; }
        .sign-name { margin-top: 1mm; font-size: 8.5pt; }
        .sign-name strong { display: block; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #64748b; border-top: 0.3pt solid #cbd5e1; padding-top: 1.5mm; }
        .footer .right { float: right; }
    </style>
</head>
<body>

    <h1>{{ $run->template->name }}</h1>
    <p style="margin:0 0 3mm; font-size:9pt;">
        {{ $run->machine->location->site?->name }} · {{ __('app.runs.run') }} #{{ $run->id }}
    </p>

    {{-- Header block — the same fields, in the same order, as the paper form --}}
    <table class="meta">
        <tr>
            <td class="label">{{ __('app.kiosk.equipment') }}</td>
            <td>{{ $run->machine->name }} ({{ $run->machine->code }})</td>
            <td class="label">{{ __('app.runs.scheduled_for') }}</td>
            <td>{{ $run->scheduled_for->format('D, j M Y') }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('app.locations.location') }}</td>
            <td>{{ $run->machine->location->name }}</td>
            <td class="label">{{ __('app.runs.shift') }}</td>
            <td>{{ $run->display_shift }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('app.locations.building') }}</td>
            <td>{{ $run->machine->location->site?->name }}{{ $run->machine->location->floor ? ' · '.$run->machine->location->floor : '' }}</td>
            <td class="label">{{ __('app.templates.work_category') }}</td>
            <td>{{ $run->template->work_category->label() }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('app.templates.work_description') }}</td>
            <td colspan="3">{{ $run->template->work_description }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('app.common.status') }}</td>
            <td>{{ $run->status->label() }}</td>
            <td class="label">{{ __('app.runs.submitted_at') }}</td>
            <td>{{ $run->submitted_at?->timezone($displayTz)->format('j M Y, g:i A') ?? '—' }}</td>
        </tr>
    </table>

    {{--
        Late stamp. On the printed sheet above all, because this is the copy
        that ends up in the file and outlives the screen it was signed on.
        Derived from scheduled_for and submitted_at, so it cannot contradict
        the two dates printed in the table directly above it.
    --}}
    @php($lateDays = $run->completedLateByDays())
    @if ($lateDays !== null)
        <p style="margin:0 0 4mm; padding:2mm 3mm; border:0.4mm solid #b45309; background:#fef3c7; color:#78350f; font-size:9pt; font-weight:bold;">
            {{ __('app.runs.late_stamp', [
                'due' => $run->scheduled_for->format('j M Y'),
                'signed' => $run->submitted_at->timezone($displayTz)->format('j M Y'),
                'days' => $lateDays,
            ]) }}
        </p>
    @endif

    {{-- Two-column task list, numbered as on the form --}}
    <div class="section">
        <div class="section-title">{{ __('app.templates.items') }}</div>
        <table class="tasks">
            <tr>
                @foreach ([$left, $right] as $column)
                    <td>
                        @foreach ($column as $item)
                            <div class="task">
                                <span class="state {{ $item->status->value === 'failed' ? 'state-failed' : ($item->status->value === 'not_applicable' ? 'state-na' : '') }}">
                                    {{ $statusLabel($item) }}
                                </span>
                                <span class="no">{{ $item->sort_order }}.</span>{{ $item->description }}
                                @if ($item->fail_reason)
                                    <span class="reason">{{ $item->fail_reason }}</span>
                                @endif
                                @if ($item->value_numeric !== null)
                                    <span class="value">{{ __('app.runs.value_numeric') }}: {{ $item->value_numeric }}</span>
                                @endif
                                @if ($item->value_text)
                                    <span class="value">{{ $item->value_text }}</span>
                                @endif
                            </div>
                        @endforeach
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('app.runs.notes') }}</div>
        <div class="notes">{{ $run->notes }}</div>
    </div>

    @if ($run->supervisor_comment)
        <div class="section">
            <div class="section-title">{{ __('app.runs.supervisor_comment') }}</div>
            <div class="notes">{{ $run->supervisor_comment }}</div>
        </div>
    @endif

    {{-- Both signature blocks, name / number / timestamp beneath each --}}
    <div class="section">
        <table class="sign">
            <tr>
                @foreach ([
                    ['label' => __('app.runs.operator_signature'), 'image' => $operatorSignature, 'user' => $run->operator, 'at' => $run->operator_signed_at],
                    ['label' => __('app.runs.supervisor_signature'), 'image' => $supervisorSignature, 'user' => $run->supervisor, 'at' => $run->supervisor_signed_at],
                ] as $block)
                    <td>
                        <div class="section-title">{{ $block['label'] }}</div>
                        <div class="sign-box">
                            @if ($block['image'])
                                <img src="{{ $block['image'] }}" alt="">
                            @else
                                <div class="sign-none">{{ __('app.runs.not_signed') }}</div>
                            @endif
                        </div>
                        <div class="sign-name">
                            <strong>{{ $block['user']?->full_name ?? '—' }}</strong>
                            {{ $block['user']?->employee_number ? '#'.$block['user']->employee_number : '' }}
                            {{ $block['at'] ? '· '.$block['at']->timezone($displayTz)->format('j M Y, g:i A') : '' }}
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="footer">
        <span class="right">{{ __('app.reports.generated_at', ['at' => $generatedAt->timezone($displayTz)->format('j M Y, g:i A')]) }}</span>
        {{ __('app.runs.run') }} #{{ $run->id }}
        · {{ __('app.reports.verification') }} <strong>{{ $verification }}</strong>
    </div>

</body>
</html>
