<div>
    <x-page-header :title="__('app.parts.title')">
        <x-slot:actions>
            @can('part.manage')
                <x-button wire:click="openCreateModal">
                    {{ __('app.parts.add_part') }}
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
    <div class="filter-bar">
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <x-input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('app.parts.search_placeholder') }}"
                aria-label="{{ __('app.actions.search') }}"
                class="w-full md:max-w-xs"
            />

            <x-select wire:model.live="activeFilter" aria-label="{{ __('app.common.status') }}" class="w-full md:w-48">
                <option value="">{{ __('app.common.all') }}</option>
                <option value="1">{{ __('app.common.active') }}</option>
                <option value="0">{{ __('app.common.inactive') }}</option>
            </x-select>

            <x-select wire:model.live="sortBy" aria-label="{{ __('app.parts.sort_by') }}" class="w-full md:w-56">
                <option value="name">{{ __('app.parts.sort_by') }}: {{ __('app.parts.sort_name') }}</option>
                <option value="code">{{ __('app.parts.sort_by') }}: {{ __('app.parts.sort_code') }}</option>
            </x-select>

            @if ($search !== '' || $activeFilter !== '')
                <x-button variant="ghost" wire:click="clearFilters">
                    {{ __('app.actions.clear') }}
                </x-button>
            @endif
        </div>
    </div>

    {{-- List --}}
    @if ($parts->count() === 0)
        @if ($search !== '' || $activeFilter !== '')
            <x-empty-state
                class="mt-6"
                :title="__('app.parts.empty_filtered_title')"
                :description="__('app.parts.empty_filtered_description')"
            >
                <x-slot:action>
                    <x-button variant="ghost" wire:click="clearFilters">{{ __('app.actions.clear') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state
                class="mt-6"
                :title="__('app.parts.empty_title')"
                :description="__('app.parts.empty_description')"
            >
                @can('part.manage')
                    <x-slot:action>
                        <x-button wire:click="openCreateModal">{{ __('app.parts.add_part') }}</x-button>
                    </x-slot:action>
                @endcan
            </x-empty-state>
        @endif
    @else
        <div class="table-wrap mt-6">
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('app.parts.part_code') }}</th>
                            <th scope="col">{{ __('app.common.name') }}</th>
                            <th scope="col">{{ __('app.parts.unit') }}</th>
                            <th scope="col">{{ __('app.parts.used_on') }}</th>
                            <th scope="col">{{ __('app.common.status') }}</th>
                            <th scope="col" class="text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parts as $part)
                            <tr wire:key="part-{{ $part->id }}">
                                <td class="font-mono text-sm">{{ $part->part_code }}</td>
                                <td class="font-semibold">{{ $part->name }}</td>
                                <td>{{ $part->unit ?? '—' }}</td>
                                <td class="max-w-xs">
                                    @if ($part->machines->isEmpty())
                                        <span class="text-slate-500">{{ __('app.parts.not_used') }}</span>
                                    @else
                                        {{ $part->machines->pluck('name')->implode(', ') }}
                                    @endif
                                </td>
                                <td>
                                    @if ($part->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">
                                            {{ __('app.common.active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-sm font-semibold text-slate-700">
                                            {{ __('app.common.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @can('part.manage')
                                            <x-icon-button icon="edit" :label="__('app.actions.edit')"
                                                wire:click="openEditModal({{ $part->id }})" />
                                            <x-icon-button icon="delete" variant="danger" :label="__('app.actions.delete')"
                                                wire:click="confirmDelete({{ $part->id }})" />
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
            {{ $parts->links() }}
        </div>
    @endif

    {{-- Create / edit modal --}}
    <x-modal name="part-form" :title="$editingId ? __('app.parts.edit_part') : __('app.parts.add_part')">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label for="part-code" class="mb-1 block text-base font-semibold">{{ __('app.parts.part_code') }}</label>
                {{-- Deliberately type="text": part codes are strings ("XXX" is real). --}}
                <x-input id="part-code" type="text" wire:model="partCode" maxlength="32" class="w-full font-mono" />
                <p class="mt-1 text-sm text-slate-500">{{ __('app.parts.code_hint') }}</p>
                @error('partCode') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="part-name" class="mb-1 block text-base font-semibold">{{ __('app.common.name') }}</label>
                <x-input id="part-name" wire:model="name" maxlength="190" class="w-full" />
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="part-unit" class="mb-1 block text-base font-semibold">
                    {{ __('app.parts.unit') }}
                    <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                </label>
                <x-input id="part-unit" wire:model="unit" maxlength="32" class="w-full" />
                @error('unit') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex min-h-14 cursor-pointer items-center gap-3">
                <input type="checkbox" wire:model="isActive" class="h-6 w-6 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                <span class="text-base font-semibold">{{ __('app.common.active') }}</span>
            </label>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit">{{ $editingId ? __('app.actions.update') : __('app.actions.create') }}</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Delete confirmation --}}
    <x-modal name="confirm-delete-part" :title="__('app.parts.delete_part')">
        @if ($this->deletingPart)
            <p class="text-base text-slate-700">
                {{ __('app.parts.delete_confirm', ['name' => $this->deletingPart->name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="deletePart">{{ __('app.actions.delete') }}</x-button>
            </div>
        @endif
    </x-modal>
</div>
