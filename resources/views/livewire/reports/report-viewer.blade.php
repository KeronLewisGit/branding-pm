{{--
    Report viewer. The export links carry the same query string the table was
    built from, so the CSV and the PDF are the same numbers — see
    ReportExportController.
--}}
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.reports.title') }}</h1>
        <p class="mt-1 text-base text-slate-600">{{ __('app.reports.subtitle') }}</p>
    </div>

    {{-- Report picker --}}
    <div class="mb-6 flex flex-wrap gap-3">
        @foreach ($reports as $option)
            <button type="button"
                wire:key="report-{{ $option->key() }}"
                wire:click="$set('report', '{{ $option->key() }}')"
                class="min-h-14 rounded-xl border-2 px-5 text-lg font-semibold transition-colors {{ $report === $option->key() ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                aria-pressed="{{ $report === $option->key() ? 'true' : 'false' }}">
                {{ $option->title() }}
            </button>
        @endforeach
    </div>

    <x-card class="mb-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.date_from') }}</span>
                <input type="date" wire:model.live="dateFrom" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.date_to') }}</span>
                <input type="date" wire:model.live="dateTo" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.machine') }}</span>
                <select wire:model.live="machine" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                    <option value="">{{ __('app.runs.all_machines') }}</option>
                    @foreach ($machines as $machineOption)
                        <option value="{{ $machineOption->id }}">{{ $machineOption->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.locations.location') }}</span>
                <select wire:model.live="location" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                    <option value="">{{ __('app.runs.all_locations') }}</option>
                    @foreach ($locations as $locationOption)
                        <option value="{{ $locationOption->id }}">{{ $locationOption->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-base text-slate-600">
                <span class="font-semibold">{{ __('app.reports.window') }}:</span> {{ $filters->label() }}
            </p>

            @can('export.data')
                <div class="flex flex-wrap gap-3">
                    <x-button variant="ghost" href="{{ route('reports.csv', array_merge(['report' => $definition->key()], $exportQuery)) }}">
                        {{ __('app.reports.download_csv') }}
                    </x-button>
                    <x-button variant="ghost" href="{{ route('reports.pdf', array_merge(['report' => $definition->key()], $exportQuery)) }}">
                        {{ __('app.reports.download_pdf') }}
                    </x-button>
                </div>
            @endcan
        </div>
    </x-card>

    <div class="mb-4">
        <h2 class="text-xl font-bold text-slate-900">{{ $definition->title() }}</h2>
        <p class="text-base text-slate-600">{{ $definition->description() }}</p>
    </div>

    @if ($rows->isEmpty())
        <x-empty-state :title="__('app.reports.no_rows')" :description="__('app.reports.no_rows_hint')" />
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach ($columns as $key => $header)
                            <th wire:key="head-{{ $key }}">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        <tr wire:key="row-{{ $index }}" class="hover:bg-slate-50">
                            @foreach ($columns as $key => $header)
                                <td class="text-slate-800 {{ is_numeric($row[$key] ?? null) ? 'tabular-nums' : '' }}">
                                    {{ $row[$key] ?? '' }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if (! empty($totals))
                    <tfoot class="bg-slate-100 font-semibold text-slate-900">
                        <tr>
                            @foreach ($columns as $key => $header)
                                <td wire:key="total-{{ $key }}">{{ $totals[$key] ?? '' }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
</div>
