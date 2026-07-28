<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('app.runs.title') }}</h1>

        {{-- Summary strip — always visible, reflects the current date/machine scope --}}
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex min-h-14 items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 font-semibold text-red-800">
                <span class="status-dot bg-status-missed" aria-hidden="true"></span>
                {{ __('app.runs.summary_missed', ['count' => $missedCount]) }}
            </span>
            <span class="inline-flex min-h-14 items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 font-semibold text-sky-800">
                <span class="status-dot bg-status-submitted" aria-hidden="true"></span>
                {{ __('app.runs.summary_awaiting_approval', ['count' => $awaitingCount]) }}
            </span>
        </div>
    </div>

    {{-- Filters — every control ≥56px (min-h-14), all reflected in the URL --}}
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.date_from') }}</span>
            <input type="date" wire:model.live="dateFrom"
                   class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.date_to') }}</span>
            <input type="date" wire:model.live="dateTo"
                   class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
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
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.common.status') }}</span>
            <select wire:model.live="status" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value="">{{ __('app.runs.all_statuses') }}</option>
                @foreach (\App\Enums\RunStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.shift') }}</span>
            <select wire:model.live="shift" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value="">{{ __('app.runs.all_shifts') }}</option>
                @foreach (\App\Enums\Shift::cases() as $shiftOption)
                    <option value="{{ $shiftOption->value }}">{{ $shiftOption->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="mb-6">
        <x-button variant="ghost" wire:click="clearFilters">{{ __('app.actions.clear') }}</x-button>
    </div>

    @if ($runs->isEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-lg text-slate-500">
            {{ __('app.runs.no_runs') }}
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-base">
                <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3">{{ __('app.runs.machine') }}</th>
                        <th class="px-4 py-3">{{ __('app.runs.template') }}</th>
                        <th class="px-4 py-3">{{ __('app.runs.scheduled_for') }}</th>
                        <th class="px-4 py-3">{{ __('app.runs.shift') }}</th>
                        <th class="px-4 py-3">{{ __('app.common.status') }}</th>
                        <th class="px-4 py-3">{{ __('app.runs.progress_label') }}</th>
                        <th class="px-4 py-3">{{ __('app.runs.operator') }}</th>
                        <th class="px-4 py-3">{{ __('app.runs.submitted_at') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($runs as $run)
                        <tr wire:key="run-{{ $run->id }}" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $run->machine->name }}</p>
                                <p class="text-sm text-slate-500">{{ $run->machine->location->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $run->template->name }}</td>
                            <td class="px-4 py-3 tabular-nums text-slate-700">
                                {{ $run->scheduled_for->format('D j M Y') }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $run->display_shift }}</td>
                            <td class="px-4 py-3"><x-status-dot :status="$run->status" /></td>
                            <td class="px-4 py-3 tabular-nums text-slate-700">
                                {{ __('app.runs.progress', ['done' => $run->items_done_count, 'total' => $run->items_total_count]) }}
                            </td>
                            <td class="px-4 py-3 text-slate-700">{{ $run->operator?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-slate-700">
                                {{ $run->submitted_at?->timezone($displayTz)->format('D j M, g:i A') ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('runs.show', $run) }}"
                                   class="inline-flex min-h-14 items-center rounded-lg px-4 font-semibold text-sky-700 hover:bg-sky-50">
                                    {{ __('app.actions.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $runs->links() }}
        </div>
    @endif
</div>
