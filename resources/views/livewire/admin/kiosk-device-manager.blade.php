<div>
    <x-page-header :title="__('app.kiosk_devices.title')">
        <x-slot:actions>
            <x-button wire:click="openCreateModal">
                {{ __('app.kiosk_devices.add_device') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <p class="mt-2 max-w-3xl text-base text-slate-600">{{ __('app.kiosk_devices.description') }}</p>

    @if (session('flash.success'))
        <x-alert type="success" class="mt-6">{{ session('flash.success') }}</x-alert>
    @endif
    @if (session('flash.error'))
        <x-alert type="error" class="mt-6">{{ session('flash.error') }}</x-alert>
    @endif

    {{-- Filter --}}
    @if ($devices->isNotEmpty())
        <div class="card mt-6 p-4">
            <x-input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('app.kiosk_devices.search_placeholder') }}"
                aria-label="{{ __('app.actions.search') }}"
                class="w-full md:max-w-xs"
            />
        </div>
    @endif

    {{-- List --}}
    @if ($devices->isEmpty())
        <x-empty-state
            class="mt-6"
            :title="$search !== '' ? __('app.kiosk_devices.empty_filtered_title') : __('app.kiosk_devices.empty_title')"
            :description="$search !== '' ? __('app.kiosk_devices.empty_filtered_description') : __('app.kiosk_devices.empty_description')"
        >
            <x-slot:action>
                @if ($search !== '')
                    <x-button variant="ghost" wire:click="$set('search', '')">{{ __('app.actions.clear') }}</x-button>
                @else
                    <x-button wire:click="openCreateModal">{{ __('app.kiosk_devices.add_device') }}</x-button>
                @endif
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-base">
                    <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('app.common.name') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.kiosk_devices.location') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.kiosk_devices.status') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.kiosk_devices.last_seen') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($devices as $device)
                            <tr wire:key="kiosk-device-{{ $device->id }}">
                                <td class="px-4 py-3 font-semibold">{{ $device->name }}</td>
                                <td class="px-4 py-3">{{ $device->location?->name ?? __('app.kiosk_devices.any_location') }}</td>

                                {{-- Status is never carried by colour alone (contract §8). --}}
                                <td class="px-4 py-3">
                                    @if (! $device->is_active)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                                            <span class="h-2 w-2 rounded-full bg-slate-400" aria-hidden="true"></span>
                                            {{ __('app.kiosk_devices.status_inactive') }}
                                        </span>
                                    @elseif ($this->isOnline($device))
                                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                            {{ __('app.kiosk_devices.status_online') }}
                                        </span>
                                    @elseif ($device->last_seen_at === null)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-800">
                                            <span class="h-2 w-2 rounded-full bg-amber-500" aria-hidden="true"></span>
                                            {{ __('app.kiosk_devices.status_never_enrolled') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                                            <span class="h-2 w-2 rounded-full bg-slate-400" aria-hidden="true"></span>
                                            {{ __('app.kiosk_devices.status_idle') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-slate-600">
                                    @if ($device->last_seen_at)
                                        <span title="{{ $device->last_seen_at->timezone(config('app.display_timezone'))->format('D j M Y, g:i A') }}">
                                            {{ $device->last_seen_at->diffForHumans() }}
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @if ($device->is_active)
                                            <x-button wire:click="openEnrolModal({{ $device->id }})">
                                                {{ __('app.kiosk_devices.enrol_a_tablet') }}
                                            </x-button>
                                        @endif

                                        <x-button variant="ghost" wire:click="openEditModal({{ $device->id }})">
                                            {{ __('app.actions.edit') }}
                                        </x-button>

                                        <x-button variant="ghost" wire:click="toggleActive({{ $device->id }})">
                                            {{ $device->is_active ? __('app.actions.deactivate') : __('app.actions.activate') }}
                                        </x-button>

                                        @if ($device->last_seen_at)
                                            <x-button variant="ghost" wire:click="confirmRevoke({{ $device->id }})">
                                                {{ __('app.kiosk_devices.revoke') }}
                                            </x-button>
                                        @endif

                                        <x-button variant="danger" wire:click="confirmDelete({{ $device->id }})">
                                            {{ __('app.actions.delete') }}
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-sm text-slate-500">
            {{ __('app.kiosk_devices.online_hint', ['minutes' => $onlineWindowMinutes]) }}
        </p>
    @endif

    {{-- Create / edit --}}
    <x-modal name="kiosk-device-form" :title="$editingId ? __('app.kiosk_devices.edit_device') : __('app.kiosk_devices.add_device')">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="kiosk-name" class="mb-1 block text-base font-semibold">{{ __('app.common.name') }}</label>
                <x-input id="kiosk-name" wire:model="name" maxlength="120" class="w-full" />
                <p class="mt-1 text-sm text-slate-500">{{ __('app.kiosk_devices.name_hint') }}</p>
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="kiosk-location" class="mb-1 block text-base font-semibold">
                    {{ __('app.kiosk_devices.location') }}
                    <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                </label>
                <x-select id="kiosk-location" wire:model="locationId" class="w-full">
                    <option value="">{{ __('app.kiosk_devices.any_location') }}</option>
                    @foreach ($this->locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </x-select>
                <p class="mt-1 text-sm text-slate-500">{{ __('app.kiosk_devices.location_hint') }}</p>
                @error('locationId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-checkbox id="kiosk-active" wire:model="isActive">
                    {{ __('app.kiosk_devices.is_active') }}
                </x-checkbox>
                <p class="mt-1 text-sm text-slate-500">{{ __('app.kiosk_devices.is_active_hint') }}</p>
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit">{{ $editingId ? __('app.actions.update') : __('app.actions.create') }}</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Enrolment: QR + link --}}
    <x-modal name="kiosk-device-enrol" :title="__('app.kiosk_devices.enrol_title')">
        @if ($this->enrollingDevice && $enrolUrl !== '')
            <div class="space-y-5">
                <div>
                    <p class="text-base font-semibold text-slate-900">{{ $this->enrollingDevice->name }}</p>
                    <p class="text-sm text-slate-500">
                        {{ $this->enrollingDevice->location?->name ?? __('app.kiosk_devices.any_location') }}
                    </p>
                </div>

                <ol class="list-decimal space-y-1 pl-5 text-base text-slate-700">
                    <li>{{ __('app.kiosk_devices.enrol_step_1') }}</li>
                    <li>{{ __('app.kiosk_devices.enrol_step_2') }}</li>
                    <li>{{ __('app.kiosk_devices.enrol_step_3') }}</li>
                </ol>

                <div class="flex justify-center rounded-lg border border-slate-200 bg-white p-4">
                    {!! $this->enrolSvg() !!}
                </div>

                <div>
                    <label for="kiosk-enrol-url" class="mb-1 block text-sm font-semibold text-slate-700">
                        {{ __('app.kiosk_devices.enrol_url_label') }}
                    </label>
                    {{-- Selectable so it can be typed or copied if the camera will not focus. --}}
                    <input
                        id="kiosk-enrol-url"
                        type="text"
                        readonly
                        value="{{ $enrolUrl }}"
                        onfocus="this.select()"
                        class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700"
                    />
                </div>

                <x-alert type="warning">
                    {{ __('app.kiosk_devices.enrol_warning', ['minutes' => \App\Livewire\Admin\KioskDeviceManager::LINK_TTL_MINUTES]) }}
                </x-alert>

                <div class="flex justify-end">
                    <x-button variant="ghost" x-on:click="show = false" wire:click="closeEnrolModal">
                        {{ __('app.actions.close') }}
                    </x-button>
                </div>
            </div>
        @endif
    </x-modal>

    {{-- Revoke confirmation --}}
    <x-modal name="kiosk-device-revoke" :title="__('app.kiosk_devices.revoke_title')">
        @if ($this->revokingDevice)
            <p class="text-base text-slate-700">
                {{ __('app.kiosk_devices.revoke_confirm', ['name' => $this->revokingDevice->name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="revokeEnrolment">{{ __('app.kiosk_devices.revoke') }}</x-button>
            </div>
        @endif
    </x-modal>

    {{-- Delete confirmation --}}
    <x-modal name="kiosk-device-delete" :title="__('app.kiosk_devices.delete_device')">
        @if ($this->deletingDevice)
            <p class="text-base text-slate-700">
                {{ __('app.kiosk_devices.delete_confirm', ['name' => $this->deletingDevice->name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="deleteDevice">{{ __('app.actions.delete') }}</x-button>
            </div>
        @endif
    </x-modal>
</div>
