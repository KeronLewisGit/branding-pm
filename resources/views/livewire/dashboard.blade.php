{{--
    Dashboard. Every figure comes from App\Support\Reporting — the same code
    the reports screen and the CSV/PDF exports use.

    Colour never carries meaning alone: every tile, cell and bar is labelled,
    and the heat-map has a legend plus a title attribute per cell.
--}}
@use('App\Support\Reporting\Compliance')

@php
    $tz = (string) config('app.display_timezone', 'UTC');
    $heatTone = [
        'done' => 'bg-emerald-500',
        'partial' => 'bg-amber-400',
        'open' => 'bg-slate-300',
        'missed' => 'bg-red-600',
    ];
    $heatLabel = [
        'done' => __('app.dashboard.heat_done'),
        'partial' => __('app.dashboard.heat_partial'),
        'open' => __('app.dashboard.heat_open'),
        'missed' => __('app.dashboard.heat_missed'),
    ];
@endphp

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('app.dashboard.title') }}</h1>
            <p class="mt-1 text-base text-slate-600">{{ $filters->label() }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach ([7, 30, 90] as $window)
                <button type="button"
                    wire:key="window-{{ $window }}"
                    wire:click="$set('days', {{ $window }})"
                    class="min-h-14 rounded-xl border-2 px-5 font-semibold {{ $days === $window ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                    aria-pressed="{{ $days === $window ? 'true' : 'false' }}">
                    {{ __('app.dashboard.last_days', ['days' => $window]) }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Headline tiles --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.dashboard.compliance') }}</p>
            <p class="mt-1 text-4xl font-bold tabular-nums text-slate-900">{{ Compliance::format($summary['percentage']) }}</p>
            <p class="mt-1 text-base text-slate-600">
                {{ __('app.dashboard.completed_of_due', ['completed' => $summary['completed'], 'due' => $summary['due']]) }}
            </p>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.dashboard.due_today') }}</p>
            <p class="mt-1 text-4xl font-bold tabular-nums text-slate-900">
                {{ $dueToday['done'] }}<span class="text-2xl text-slate-400">/{{ $dueToday['due'] }}</span>
            </p>
            <p class="mt-1 text-base text-slate-600">{{ __('app.dashboard.done_today') }}</p>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.dashboard.overdue_missed') }}</p>
            <p class="mt-1 text-4xl font-bold tabular-nums {{ $dueToday['overdue'] + $summary['missed'] > 0 ? 'text-red-700' : 'text-slate-900' }}">
                {{ $dueToday['overdue'] + $summary['missed'] }}
            </p>
            <p class="mt-1 text-base text-slate-600">
                {{ __('app.dashboard.overdue_breakdown', ['overdue' => $dueToday['overdue'], 'missed' => $summary['missed']]) }}
            </p>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.dashboard.awaiting_approval') }}</p>
            <p class="mt-1 text-4xl font-bold tabular-nums text-slate-900">{{ $awaitingApproval }}</p>
            @can('run.approve')
                <a href="{{ route('runs.approvals') }}" class="mt-1 inline-block text-base font-semibold text-sky-700 hover:underline">
                    {{ __('app.approvals.title') }} &rarr;
                </a>
            @endcan
        </x-card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Compliance by week --}}
        <x-card class="lg:col-span-2">
            <h2 class="text-xl font-bold text-slate-900">{{ __('app.dashboard.compliance_by_week') }}</h2>

            @if ($byWeek->isEmpty())
                <p class="mt-3 text-base text-slate-500">{{ __('app.reports.no_rows') }}</p>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($byWeek as $week)
                        <li wire:key="week-{{ $week['week'] }}" class="flex items-center gap-4">
                            <span class="w-20 shrink-0 text-base tabular-nums text-slate-600">{{ $week['week'] }}</span>
                            <span class="h-4 flex-1 overflow-hidden rounded-full bg-slate-200"
                                  role="img"
                                  aria-label="{{ __('app.dashboard.week_compliance', ['week' => $week['week'], 'value' => Compliance::format($week['percentage'])]) }}">
                                <span class="block h-full rounded-full bg-emerald-500" style="width: {{ (int) round($week['percentage'] ?? 0) }}%"></span>
                            </span>
                            <span class="w-24 shrink-0 text-right text-base font-semibold tabular-nums text-slate-800">
                                {{ Compliance::format($week['percentage']) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Open issues by severity --}}
        <x-card>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-slate-900">{{ __('app.dashboard.open_issues') }}</h2>
                @can('issue.view')
                    <a href="{{ route('issues.index') }}" class="text-base font-semibold text-sky-700 hover:underline">{{ __('app.actions.view') }}</a>
                @endcan
            </div>

            <ul class="mt-4 space-y-2">
                @foreach ($openIssues as $row)
                    <li wire:key="sev-{{ $row['severity']->value }}" class="flex items-center justify-between gap-3">
                        <x-badge :color="$row['severity']->color()">{{ $row['severity']->label() }}</x-badge>
                        <span class="text-2xl font-bold tabular-nums text-slate-900">{{ $row['count'] }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>

    {{-- Completion heat-map --}}
    <x-card class="mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-bold text-slate-900">{{ __('app.dashboard.heatmap') }}</h2>
            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                @foreach ($heatTone as $key => $tone)
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block h-3 w-3 rounded-sm {{ $tone }}" aria-hidden="true"></span>
                        {{ $heatLabel[$key] }}
                    </span>
                @endforeach
            </div>
        </div>

        @if ($heatmap['machines']->isEmpty())
            <p class="mt-3 text-base text-slate-500">{{ __('app.reports.no_rows') }}</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="px-2 py-1 text-left font-semibold text-slate-600">{{ __('app.runs.machine') }}</th>
                            @foreach ($heatmap['days'] as $day)
                                <th class="px-1 py-1 text-center text-xs font-medium text-slate-500" wire:key="day-{{ $day }}">
                                    {{ \Illuminate\Support\Carbon::parse($day)->format('j') }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($heatmap['machines'] as $machine)
                            <tr wire:key="heat-{{ $machine->id }}">
                                <td class="whitespace-nowrap px-2 py-1 text-slate-800">{{ $machine->name }}</td>
                                @foreach ($heatmap['days'] as $day)
                                    @php($state = $heatmap['cells'][$machine->id][$day] ?? null)
                                    <td class="px-1 py-1" wire:key="heat-{{ $machine->id }}-{{ $day }}">
                                        <span class="mx-auto block h-5 w-5 rounded-sm {{ $state ? $heatTone[$state] : 'bg-slate-100' }}"
                                              title="{{ $machine->name }} · {{ \Illuminate\Support\Carbon::parse($day)->format('j M') }} · {{ $state ? $heatLabel[$state] : __('app.dashboard.heat_nothing') }}"></span>
                                        <span class="sr-only">{{ $state ? $heatLabel[$state] : __('app.dashboard.heat_nothing') }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Compliance by machine — worst first --}}
        <x-card>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-slate-900">{{ __('app.dashboard.by_machine') }}</h2>
                <a href="{{ route('reports.index', ['report' => 'compliance']) }}" class="text-base font-semibold text-sky-700 hover:underline">
                    {{ __('app.reports.title') }}
                </a>
            </div>

            <table class="data-table data-table-bare mt-3">
                <thead>
                    <tr>
                        <th>{{ __('app.runs.machine') }}</th>
                        <th class="text-right">{{ __('app.reports.column.due') }}</th>
                        <th class="text-right">{{ __('app.reports.column.missed') }}</th>
                        <th class="text-right">{{ __('app.reports.column.compliance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byMachine->take(10) as $row)
                        <tr wire:key="machine-row-{{ $loop->index }}">
                            <td class="text-slate-800">{{ $row['machine'] }}</td>
                            <td class="text-right tabular-nums text-slate-600">{{ $row['due'] }}</td>
                            <td class="py-2 text-right tabular-nums {{ $row['missed'] > 0 ? 'font-semibold text-red-700' : 'text-slate-600' }}">{{ $row['missed'] }}</td>
                            <td class="text-right font-semibold tabular-nums text-slate-900">{{ $row['compliance'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

    </div>
</div>
