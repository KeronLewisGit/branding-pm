<div>
    <x-page-header :title="__('app.holidays.title')">
        <x-slot:actions>
            @can('holiday.manage')
                <x-button
                    variant="ghost"
                    wire:click="copyRecurringToNextYear"
                    wire:confirm="{{ __('app.holidays.copy_confirm', ['from' => $year, 'to' => $year + 1]) }}"
                >
                    {{ __('app.holidays.copy_recurring', ['year' => $year + 1]) }}
                </x-button>
                <x-button wire:click="openCreateModal">
                    {{ __('app.holidays.add_holiday') }}
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (session('flash.success'))
        <x-alert type="success" class="mt-6">{{ session('flash.success') }}</x-alert>
    @endif
    @if (session('flash.error'))
        <x-alert type="error" class="mt-6">{{ session('flash.error') }}</x-alert>
    @endif

    {{-- Movable feasts must be re-entered every year --}}
    <x-alert type="info" class="mt-6">{{ __('app.holidays.movable_note') }}</x-alert>

    {{-- Year switcher --}}
    <div class="card mt-6 p-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-button variant="ghost" wire:click="previousYear" aria-label="{{ __('app.holidays.previous_year') }}">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                {{ $year - 1 }}
            </x-button>

            <x-select wire:model.live="year" aria-label="{{ __('app.holidays.year') }}" class="w-40 text-center">
                @foreach ($this->yearOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </x-select>

            <x-button variant="ghost" wire:click="nextYear" aria-label="{{ __('app.holidays.next_year') }}">
                {{ $year + 1 }}
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
            </x-button>

            <p class="ml-auto text-base text-slate-600">{{ __('app.holidays.recurring_hint') }}</p>
        </div>
    </div>

    {{-- List --}}
    @if ($holidays->count() === 0)
        <x-empty-state
            class="mt-6"
            :title="__('app.holidays.empty_title', ['year' => $year])"
            :description="__('app.holidays.empty_description')"
        >
            @can('holiday.manage')
                <x-slot:action>
                    <x-button wire:click="openCreateModal">{{ __('app.holidays.add_holiday') }}</x-button>
                </x-slot:action>
            @endcan
        </x-empty-state>
    @else
        <div class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-base">
                    <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('app.holidays.date') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.common.name') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.holidays.applies_to') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.holidays.type') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($holidays as $holiday)
                            <tr wire:key="holiday-{{ $holiday->id }}">
                                <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums">
                                    {{ $holiday->date->format('D, j M Y') }}
                                </td>
                                <td class="px-4 py-3">{{ $holiday->name }}</td>
                                <td class="px-4 py-3">
                                    {{ $holiday->site?->name ?? __('app.holidays.all_sites') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($holiday->is_recurring)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1 text-sm font-semibold text-sky-800">
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                            {{ __('app.holidays.recurring') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-sm font-semibold text-slate-700">
                                            {{ __('app.holidays.one_off') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('holiday.manage')
                                            <x-icon-button icon="edit" :label="__('app.actions.edit')"
                                                wire:click="openEditModal({{ $holiday->id }})" />
                                            <x-icon-button icon="delete" variant="danger" :label="__('app.actions.delete')"
                                                wire:click="confirmDelete({{ $holiday->id }})" />
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $holidays->links() }}
        </div>
    @endif

    {{-- Create / edit modal --}}
    <x-modal name="holiday-form" :title="$editingId ? __('app.holidays.edit_holiday') : __('app.holidays.add_holiday')">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="holiday-site" class="mb-1 block text-base font-semibold">{{ __('app.holidays.applies_to') }}</label>
                <x-select id="holiday-site" wire:model="siteId" class="w-full">
                    <option value="">{{ __('app.holidays.all_sites') }}</option>
                    @foreach ($this->sites as $site)
                        <option value="{{ $site->id }}">{{ $site->name }}</option>
                    @endforeach
                </x-select>
                @error('siteId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="holiday-date" class="mb-1 block text-base font-semibold">{{ __('app.holidays.date') }}</label>
                <x-input id="holiday-date" type="date" wire:model="date" class="w-full" />
                @error('date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="holiday-name" class="mb-1 block text-base font-semibold">{{ __('app.common.name') }}</label>
                <x-input id="holiday-name" wire:model="name" maxlength="120" class="w-full" />
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex min-h-14 cursor-pointer items-center gap-3">
                <input type="checkbox" wire:model="isRecurring" class="h-6 w-6 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                <span class="text-base font-semibold">{{ __('app.holidays.is_recurring') }}</span>
            </label>
            <p class="text-sm text-slate-500">{{ __('app.holidays.movable_note') }}</p>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit">{{ $editingId ? __('app.actions.update') : __('app.actions.create') }}</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Delete confirmation --}}
    <x-modal name="confirm-delete-holiday" :title="__('app.holidays.delete_holiday')">
        @if ($this->deletingHoliday)
            <p class="text-base text-slate-700">
                {{ __('app.holidays.delete_confirm', ['name' => $this->deletingHoliday->name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="deleteHoliday">{{ __('app.actions.delete') }}</x-button>
            </div>
        @endif
    </x-modal>
</div>
