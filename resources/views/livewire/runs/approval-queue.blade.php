{{--
    Supervisor approval queue. Oldest submission first — the row at the top
    is the one that has been waiting longest for a signature.

    Failed items and open issues are surfaced on the row itself, always with
    a text label beside the colour, so the queue shows where the attention is
    needed before anything is opened.
--}}
@use('App\Enums\Shift')

<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('app.approvals.title') }}</h1>
            <p class="mt-1 text-base text-slate-600">{{ __('app.approvals.subtitle') }}</p>
        </div>

        <span class="inline-flex min-h-14 items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 font-semibold text-sky-800">
            <span class="status-dot bg-status-submitted" aria-hidden="true"></span>
            {{ __('app.runs.summary_awaiting_approval', ['count' => $runs->total()]) }}
        </span>
    </div>

    {{-- Filters --}}
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.shift') }}</span>
            <select wire:model.live="shift" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value="">{{ __('app.runs.all_shifts') }}</option>
                @foreach (Shift::cases() as $shiftOption)
                    <option value="{{ $shiftOption->value }}">{{ $shiftOption->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.approvals.sort') }}</span>
            <select wire:model.live="sort" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value="oldest">{{ __('app.approvals.sort_oldest') }}</option>
                <option value="newest">{{ __('app.approvals.sort_newest') }}</option>
            </select>
        </label>
    </div>

    <div class="mb-6">
        <x-button variant="ghost" wire:click="clearFilters">{{ __('app.actions.clear') }}</x-button>
    </div>

    @if ($runs->isEmpty())
        <x-empty-state
            :title="__('app.approvals.empty_title')"
            :description="__('app.approvals.empty_description')" />
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('app.runs.machine') }}</th>
                        <th>{{ __('app.runs.template') }}</th>
                        <th>{{ __('app.runs.scheduled_for') }}</th>
                        <th>{{ __('app.runs.shift') }}</th>
                        <th>{{ __('app.runs.operator') }}</th>
                        <th>{{ __('app.runs.submitted_at') }}</th>
                        <th>{{ __('app.approvals.attention') }}</th>
                        <th><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($runs as $run)
                        @php
                            $waitingHours = $run->submitted_at?->diffInHours(now());
                        @endphp
                        <tr wire:key="approval-{{ $run->id }}" class="hover:bg-slate-50">
                            <td>
                                <p class="font-semibold text-slate-900">{{ $run->machine->name }}</p>
                                <p class="text-sm text-slate-500">{{ $run->machine->location->name }}</p>
                            </td>
                            <td class="text-slate-700">{{ $run->template->name }}</td>
                            <td class="tabular-nums text-slate-700">{{ $run->scheduled_for->format('D j M Y') }}</td>
                            <td class="text-slate-700">{{ $run->display_shift }}</td>
                            <td class="text-slate-700">{{ $run->operator?->full_name ?? '—' }}</td>
                            <td class="tabular-nums text-slate-700">
                                {{ $run->submitted_at?->timezone($displayTz)->format('D j M, g:i A') ?? '—' }}
                                @if ($waitingHours !== null && $waitingHours >= 24)
                                    @php($waitingDays = intdiv((int) $waitingHours, 24))
                                    <span class="mt-1 block text-sm font-semibold text-amber-700">
                                        {{ trans_choice('app.approvals.waiting_days', $waitingDays, ['days' => $waitingDays]) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-2">
                                    @if ($run->items_failed_count > 0)
                                        <x-badge color="rose">{{ __('app.approvals.failed_items', ['count' => $run->items_failed_count]) }}</x-badge>
                                    @endif
                                    @if ($run->open_issues_count > 0)
                                        @can('issue.view')
                                            {{-- Straight into the register, filtered to this machine's open work. --}}
                                            <a href="{{ route('issues.index', ['machine' => $run->machine_id]) }}">
                                                <x-badge color="amber">{{ trans_choice('app.approvals.open_issues', $run->open_issues_count, ['count' => $run->open_issues_count]) }}</x-badge>
                                            </a>
                                        @else
                                            <x-badge color="amber">{{ trans_choice('app.approvals.open_issues', $run->open_issues_count, ['count' => $run->open_issues_count]) }}</x-badge>
                                        @endcan
                                    @endif
                                    @if ($run->items_done_count < $run->items_total_count)
                                        <x-badge color="slate">{{ __('app.runs.progress', ['done' => $run->items_done_count, 'total' => $run->items_total_count]) }}</x-badge>
                                    @endif
                                    @if ($run->items_failed_count === 0 && $run->open_issues_count === 0 && $run->items_done_count >= $run->items_total_count)
                                        <span class="text-base text-slate-400">{{ __('app.approvals.nothing_flagged') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('runs.review', $run) }}"
                                   class="inline-flex min-h-14 items-center rounded-lg px-4 font-semibold text-sky-700 hover:bg-sky-50">
                                    {{ __('app.approvals.review') }}
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
