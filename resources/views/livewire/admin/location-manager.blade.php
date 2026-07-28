<div>
    <x-page-header :title="__('app.locations.title')">
        <x-slot:actions>
            @can('machine.manage')
                <x-button wire:click="openCreateModal">
                    {{ __('app.locations.add_location') }}
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

    {{-- Filters --}}
    <div class="card mt-6 p-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <x-input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('app.locations.search_placeholder') }}"
                aria-label="{{ __('app.actions.search') }}"
                class="w-full md:max-w-xs"
            />

            <x-select wire:model.live="siteFilter" aria-label="{{ __('app.locations.site') }}" class="w-full md:w-64">
                <option value="">{{ __('app.locations.all_sites') }}</option>
                @foreach ($this->sites as $site)
                    <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
            </x-select>

            @if ($search !== '' || $siteFilter !== '')
                <x-button variant="ghost" wire:click="clearFilters">
                    {{ __('app.actions.clear') }}
                </x-button>
            @endif
        </div>
    </div>

    {{-- List --}}
    @if ($locations->count() === 0)
        @if ($search !== '' || $siteFilter !== '')
            <x-empty-state
                class="mt-6"
                :title="__('app.locations.empty_filtered_title')"
                :description="__('app.locations.empty_filtered_description')"
            >
                <x-slot:action>
                    <x-button variant="ghost" wire:click="clearFilters">{{ __('app.actions.clear') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state
                class="mt-6"
                :title="__('app.locations.empty_title')"
                :description="__('app.locations.empty_description')"
            >
                @can('machine.manage')
                    <x-slot:action>
                        <x-button wire:click="openCreateModal">{{ __('app.locations.add_location') }}</x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @endif
    @else
        <div class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-base">
                    <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('app.common.name') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.locations.site') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.locations.floor') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.locations.machines_count') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($locations as $location)
                            <tr wire:key="location-{{ $location->id }}">
                                <td class="px-4 py-3 font-semibold">{{ $location->name }}</td>
                                <td class="px-4 py-3">{{ $location->site?->name }}</td>
                                <td class="px-4 py-3">{{ $location->floor ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $location->machines_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @can('machine.manage')
                                            <x-button variant="ghost" wire:click="openEditModal({{ $location->id }})">
                                                {{ __('app.actions.edit') }}
                                            </x-button>
                                            <x-button variant="danger" wire:click="confirmDelete({{ $location->id }})">
                                                {{ __('app.actions.delete') }}
                                            </x-button>
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
            {{ $locations->links() }}
        </div>
    @endif

    {{-- Create / edit modal --}}
    <x-modal name="location-form" :title="$editingId ? __('app.locations.edit_location') : __('app.locations.add_location')">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="location-site" class="mb-1 block text-base font-semibold">{{ __('app.locations.site') }}</label>
                <x-select id="location-site" wire:model="siteId" class="w-full">
                    <option value="">{{ __('app.locations.site') }}…</option>
                    @foreach ($this->sites as $site)
                        <option value="{{ $site->id }}">{{ $site->name }}</option>
                    @endforeach
                </x-select>
                @error('siteId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="location-name" class="mb-1 block text-base font-semibold">{{ __('app.common.name') }}</label>
                <x-input id="location-name" wire:model="name" maxlength="120" class="w-full" />
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="location-floor" class="mb-1 block text-base font-semibold">
                    {{ __('app.locations.floor') }}
                    <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                </label>
                <x-input id="location-floor" wire:model="floor" maxlength="60" class="w-full" />
                @error('floor') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit">{{ $editingId ? __('app.actions.update') : __('app.actions.create') }}</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Delete confirmation --}}
    <x-modal name="confirm-delete-location" :title="__('app.locations.delete_location')">
        @if ($this->deletingLocation)
            <p class="text-base text-slate-700">
                {{ __('app.locations.delete_confirm', ['name' => $this->deletingLocation->name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="deleteLocation">{{ __('app.actions.delete') }}</x-button>
            </div>
        @endif
    </x-modal>
</div>
