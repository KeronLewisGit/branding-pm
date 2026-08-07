<div>
    <x-page-header :title="__('app.qa.queue_title')" />

    <p class="mt-2 max-w-3xl text-base text-slate-600">{{ __('app.qa.queue_description') }}</p>

    @if (session('flash.success'))
        <x-alert type="success" class="mt-6">{{ session('flash.success') }}</x-alert>
    @endif

    <div class="card mt-6 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <x-select wire:model.live="locationFilter" aria-label="{{ __('app.locations.location') }}" class="w-full md:w-64">
                <option value="">{{ __('app.machines.all_locations') }}</option>
                @foreach ($this->locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </x-select>

            <x-checkbox id="show-verified" wire:model.live="showVerified">
                {{ __('app.qa.show_verified') }}
            </x-checkbox>

            <p class="text-base font-semibold text-slate-700 md:ml-auto">
                {{ trans_choice('app.qa.outstanding', $this->outstandingCount, ['count' => $this->outstandingCount]) }}
            </p>
        </div>
    </div>

    @if ($runs->count() === 0)
        <x-empty-state
            class="mt-6"
            :title="__('app.qa.empty_title')"
            :description="__('app.qa.empty_description')"
        />
    @else
        <div class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-base">
                    <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('app.machines.machine') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.runs.template') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.reports.column.scheduled_for') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.runs.operator') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.runs.supervisor') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.qa.verified') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($runs as $run)
                            <tr wire:key="verify-run-{{ $run->id }}">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-900">{{ $run->machine->name }}</p>

                                    {{-- Failures on the row: the sheets worth opening first. --}}
                                    @if ($run->failed_items_count > 0)
                                        <p class="mt-1 text-sm font-semibold text-rose-700">
                                            {{ trans_choice('app.approvals.failed_summary', $run->failed_items_count, ['count' => $run->failed_items_count]) }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $run->template?->name ?? '—' }}</td>
                                {{-- A calendar date: never timezone-converted. --}}
                                <td class="px-4 py-3 tabular-nums">{{ $run->scheduled_for->format('d M Y') }}</td>
                                <td class="px-4 py-3">{{ $run->operator?->full_name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $run->supervisor?->full_name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($run->qa_verified_at)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                            {{ $run->qaVerifiedBy?->full_name ?? __('app.qa.verified') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-800">
                                            <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                                            {{ __('app.qa.awaiting') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-button variant="ghost" :href="route('runs.review', $run)">
                                        {{ $run->qa_verified_at ? __('app.actions.view') : __('app.qa.verify') }}
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $runs->links() }}</div>
    @endif
</div>
