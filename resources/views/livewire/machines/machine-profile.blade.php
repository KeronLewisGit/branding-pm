<div>
    <x-page-header :title="$machine->name">
        <x-slot:actions>
            @can('update', $machine)
                <x-button variant="ghost" :href="route('admin.machines')">{{ __('app.machines.title') }}</x-button>
            @endcan
            @can('machine.manage')
                <x-button variant="ghost" :href="route('admin.machines.qr', ['location' => $machine->location_id])">
                    {{ __('app.qr.title') }}
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mt-2 flex flex-wrap items-center gap-3 text-base text-slate-600">
        <span class="font-mono text-sm">{{ $machine->code }}</span>
        <span aria-hidden="true">·</span>
        <span>{{ $machine->location?->name }}{{ $machine->location?->floor ? ' — '.$machine->location->floor : '' }}</span>

        @unless ($machine->is_active)
            <x-badge>{{ __('app.common.inactive') }}</x-badge>
        @endunless

        @if ($machine->open_breakdown)
            {{-- A machine that is broken down leads with that, everywhere. --}}
            <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1 text-sm font-semibold text-rose-800">
                <span class="h-2 w-2 rounded-full bg-rose-500" aria-hidden="true"></span>
                {{ __('app.machines.open_breakdown') }}
            </span>
        @endif
    </div>

    {{-- Window --}}
    <div class="mt-6 flex flex-wrap items-center gap-2">
        <span class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('app.reports.window') }}</span>
        @foreach ($this->windows() as $window)
            <x-button
                :variant="$days === $window ? 'primary' : 'ghost'"
                wire:click="setWindow({{ $window }})"
            >
                {{ __('app.machines.last_days', ['days' => $window]) }}
            </x-button>
        @endforeach
    </div>

    {{-- Counts --}}
    @php($stats = $this->runStats)
    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="stat-tile">
            <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('app.reports.column.completed') }}</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $stats['completed'] }}</p>
        </div>
        <div class="stat-tile">
            <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('app.reports.column.missed') }}</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $stats['missed'] }}</p>
        </div>
        <div class="stat-tile">
            <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('app.reports.column.outstanding') }}</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $stats['outstanding'] }}</p>
        </div>
        <div class="stat-tile">
            <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ __('app.reports.column.compliance') }}</p>
            <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900">
                {{-- No percentage at all when nothing was due — 0% and 100% both lie. --}}
                {{ $stats['total'] === 0 ? '—' : round($stats['completed'] / $stats['total'] * 100).'%' }}
            </p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- Left: details + QR --}}
        <div class="space-y-6">
            <div class="card card-body">
                <h2 class="text-lg font-bold text-slate-900">{{ __('app.machines.details') }}</h2>
                <dl class="mt-3 space-y-2 text-base">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('app.machines.manufacturer') }}</dt>
                        <dd class="text-right font-medium">{{ $machine->manufacturer ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('app.machines.model') }}</dt>
                        <dd class="text-right font-medium">{{ $machine->model ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('app.machines.asset_tag') }}</dt>
                        <dd class="text-right font-medium">{{ $machine->asset_tag ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($machine->notes)
                    <p class="mt-4 whitespace-pre-line border-t border-slate-200 pt-3 text-base text-slate-700">{{ $machine->notes }}</p>
                @endif
            </div>

            <div class="card card-body text-center">
                <h2 class="text-lg font-bold text-slate-900">{{ __('app.qr.title') }}</h2>
                <div class="mt-3 flex justify-center">{!! $this->qrSvg() !!}</div>
                {{-- The code in plain text, for when the sticker is over-sprayed. --}}
                <p class="mt-2 font-mono text-sm text-slate-600">{{ $machine->code }}</p>
            </div>

            <div class="card card-body">
                <h2 class="text-lg font-bold text-slate-900">{{ __('app.machines.assigned_operators') }}</h2>
                @if ($this->operators->isEmpty())
                    <p class="mt-2 text-base text-slate-500">{{ __('app.machines.no_operators') }}</p>
                @else
                    <ul class="mt-2 space-y-1 text-base">
                        @foreach ($this->operators as $operator)
                            <li class="flex justify-between gap-3">
                                <span>{{ $operator->full_name }}</span>
                                <span class="text-sm text-slate-500">#{{ $operator->employee_number }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-sm text-slate-500">{{ __('app.machines.operators_not_a_gate_short') }}</p>
            </div>
        </div>

        {{-- Right: checklists, runs, issues --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="card card-body">
                <h2 class="text-lg font-bold text-slate-900">{{ __('app.machines.checklists') }}</h2>
                @if ($this->templates->isEmpty())
                    <p class="mt-2 text-base text-slate-500">{{ __('app.machines.no_checklists') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($this->templates as $template)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $template->name }}</p>
                                    <p class="text-sm text-slate-500">
                                        {{ $template->frequency->label() }}
                                        · {{ __('app.machines.items_count', ['count' => $template->items_count]) }}
                                        · v{{ $template->version }}
                                    </p>
                                </div>
                                @unless ($template->is_active)
                                    <x-badge>{{ __('app.common.inactive') }}</x-badge>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card card-body">
                <h2 class="text-lg font-bold text-slate-900">{{ __('app.machines.run_history') }}</h2>
                @if ($this->recentRuns->isEmpty())
                    <p class="mt-2 text-base text-slate-500">{{ __('app.machines.no_runs') }}</p>
                @else
                    <div class="mt-3 overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('app.reports.column.scheduled_for') }}</th>
                                    <th scope="col">{{ __('app.runs.template') }}</th>
                                    <th scope="col">{{ __('app.runs.operator') }}</th>
                                    <th scope="col">{{ __('app.common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->recentRuns as $run)
                                    <tr wire:key="run-{{ $run->id }}">
                                        {{-- A calendar date: never timezone-converted. --}}
                                        <td class="tabular-nums">{{ $run->scheduled_for->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ route('runs.show', $run) }}" class="font-medium text-slate-900 underline decoration-slate-300 underline-offset-2 hover:decoration-slate-900">
                                                {{ $run->template?->name ?? '—' }}
                                            </a>
                                        </td>
                                        <td>{{ $run->operator?->full_name ?? '—' }}</td>
                                        {{-- x-status-dot renders the dot AND the label. --}}
                                        <td><x-status-dot :status="$run->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card card-body">
                <h2 class="text-lg font-bold text-slate-900">{{ __('app.machines.issue_history') }}</h2>
                @if ($this->issues->isEmpty())
                    <p class="mt-2 text-base text-slate-500">{{ __('app.machines.no_issues') }}</p>
                @else
                    <ul class="mt-3 divide-y divide-slate-100">
                        @foreach ($this->issues as $issue)
                            <li class="flex flex-wrap items-start justify-between gap-3 py-2">
                                <div class="min-w-0">
                                    <a href="{{ route('issues.show', $issue) }}" class="font-medium text-slate-900 underline decoration-slate-300 underline-offset-2 hover:decoration-slate-900">
                                        {{ Str::limit($issue->description, 90) }}
                                    </a>
                                    <p class="text-sm text-slate-500">
                                        {{ $issue->raisedBy?->full_name ?? '—' }}
                                        · {{ $issue->created_at->timezone(config('app.display_timezone'))->format('d M Y') }}
                                    </p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <x-badge :color="$issue->severity->color()">{{ $issue->severity->label() }}</x-badge>
                                    <x-badge :color="$issue->status->color()">{{ $issue->status->label() }}</x-badge>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</div>
