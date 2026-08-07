<div>
    <x-page-header :title="__('app.machines.title')">
        <x-slot:actions>
            @can('create', App\Models\Machine::class)
                <x-button wire:click="openCreateModal">
                    {{ __('app.machines.add_machine') }}
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
                placeholder="{{ __('app.machines.search_placeholder') }}"
                aria-label="{{ __('app.actions.search') }}"
                class="w-full md:max-w-xs"
            />

            <x-select wire:model.live="locationFilter" aria-label="{{ __('app.machines.location') }}" class="w-full md:w-64">
                <option value="">{{ __('app.machines.all_locations') }}</option>
                @foreach ($this->locations as $location)
                    <option value="{{ $location->id }}">{{ $location->site?->name }} — {{ $location->name }}</option>
                @endforeach
            </x-select>

            <x-select wire:model.live="activeFilter" aria-label="{{ __('app.common.status') }}" class="w-full md:w-48">
                <option value="">{{ __('app.common.all') }}</option>
                <option value="1">{{ __('app.common.active') }}</option>
                <option value="0">{{ __('app.common.inactive') }}</option>
            </x-select>

            @if ($search !== '' || $locationFilter !== '' || $activeFilter !== '')
                <x-button variant="ghost" wire:click="clearFilters">
                    {{ __('app.actions.clear') }}
                </x-button>
            @endif
        </div>
    </div>

    {{-- List --}}
    @if ($machines->count() === 0)
        @if ($search !== '' || $locationFilter !== '' || $activeFilter !== '')
            <x-empty-state
                class="mt-6"
                :title="__('app.machines.empty_filtered_title')"
                :description="__('app.machines.empty_filtered_description')"
            >
                <x-slot:action>
                    <x-button variant="ghost" wire:click="clearFilters">{{ __('app.actions.clear') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <x-empty-state
                class="mt-6"
                :title="__('app.machines.empty_title')"
                :description="__('app.machines.empty_description')"
            >
                @can('create', App\Models\Machine::class)
                    <x-slot:action>
                        <x-button wire:click="openCreateModal">{{ __('app.machines.add_machine') }}</x-button>
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
                            <th scope="col" class="px-4 py-3">{{ __('app.common.code') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.machines.location') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.machines.asset_tag') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.machines.parts_count') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.common.status') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($machines as $machine)
                            <tr wire:key="machine-{{ $machine->id }}">
                                <td class="px-4 py-3 font-semibold">
                                    {{ $machine->name }}
                                    @if ($machine->manufacturer || $machine->model)
                                        <span class="block text-sm font-normal text-slate-500">
                                            {{ trim(($machine->manufacturer ?? '').' '.($machine->model ?? '')) }}
                                        </span>
                                    @endif
                                    {{-- Open breakdown — flagged wherever a machine is listed --}}
                                    @if ($machine->open_breakdown_count > 0)
                                        @can('issue.view')
                                            <a href="{{ route('issues.index', ['machine' => $machine->id, 'severity' => 'breakdown']) }}" class="mt-1 inline-block">
                                                <x-badge color="red">{{ __('app.issues.open_breakdown_flag') }}</x-badge>
                                            </a>
                                        @else
                                            <x-badge color="red" class="mt-1">{{ __('app.issues.open_breakdown_flag') }}</x-badge>
                                        @endcan
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-sm">{{ $machine->code }}</td>
                                <td class="px-4 py-3">
                                    {{ $machine->location?->site?->name }} — {{ $machine->location?->name }}
                                </td>
                                <td class="px-4 py-3">{{ $machine->asset_tag ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $machine->parts_count }}</td>
                                <td class="px-4 py-3">
                                    @if ($machine->is_active)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">
                                            {{ __('app.common.active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-200 px-3 py-1 text-sm font-semibold text-slate-700">
                                            {{ __('app.common.inactive') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        @can('update', $machine)
                                            <x-button variant="ghost" wire:click="openEditModal({{ $machine->id }})">
                                                {{ __('app.actions.edit') }}
                                            </x-button>
                                            <x-button variant="ghost" wire:click="openPartsModal({{ $machine->id }})">
                                                {{ __('app.machines.manage_parts') }}
                                            </x-button>
                                            <x-button variant="ghost" wire:click="openOperatorsModal({{ $machine->id }})">
                                                {{ __('app.machines.manage_operators') }}
                                            </x-button>
                                        @endcan
                                        @can('delete', $machine)
                                            <x-button variant="danger" wire:click="confirmDelete({{ $machine->id }})">
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
            {{ $machines->links() }}
        </div>
    @endif

    {{-- Create / edit modal --}}
    <x-modal name="machine-form" max-width="2xl" :title="$editingId ? __('app.machines.edit_machine') : __('app.machines.add_machine')">
        <form wire:submit="save" class="space-y-4">
            @if ($editingId !== null && $code !== $originalCode)
                <x-alert type="warning">
                    <p class="font-bold">{{ __('app.machines.code_change_warning_title') }}</p>
                    <p class="mt-1">{{ __('app.machines.code_change_warning', ['old' => $originalCode]) }}</p>
                </x-alert>
            @endif

            <div>
                <label for="machine-location" class="mb-1 block text-base font-semibold">{{ __('app.machines.location') }}</label>
                <x-select id="machine-location" wire:model="locationId" class="w-full">
                    <option value="">{{ __('app.machines.all_locations') }}</option>
                    @foreach ($this->locations as $location)
                        <option value="{{ $location->id }}">{{ $location->site?->name }} — {{ $location->name }}</option>
                    @endforeach
                </x-select>
                @error('locationId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="machine-name" class="mb-1 block text-base font-semibold">{{ __('app.common.name') }}</label>
                <x-input id="machine-name" wire:model.live.debounce.400ms="name" maxlength="160" class="w-full" />
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="machine-code" class="mb-1 block text-base font-semibold">{{ __('app.common.code') }}</label>
                <x-input id="machine-code" wire:model.live.debounce.400ms="code" maxlength="64" class="w-full font-mono" />
                <p class="mt-1 text-sm text-slate-500">{{ __('app.machines.code_hint') }}</p>
                @error('code') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="machine-manufacturer" class="mb-1 block text-base font-semibold">
                        {{ __('app.machines.manufacturer') }}
                        <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                    </label>
                    <x-input id="machine-manufacturer" wire:model="manufacturer" maxlength="120" class="w-full" />
                    @error('manufacturer') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="machine-model" class="mb-1 block text-base font-semibold">
                        {{ __('app.machines.model') }}
                        <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                    </label>
                    <x-input id="machine-model" wire:model="model" maxlength="120" class="w-full" />
                    @error('model') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="machine-asset-tag" class="mb-1 block text-base font-semibold">
                    {{ __('app.machines.asset_tag') }}
                    <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                </label>
                <x-input id="machine-asset-tag" wire:model="assetTag" maxlength="64" class="w-full" />
                @error('assetTag') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="machine-notes" class="mb-1 block text-base font-semibold">
                    {{ __('app.machines.notes') }}
                    <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                </label>
                <textarea id="machine-notes" wire:model="notes" rows="3" class="input w-full"></textarea>
                @error('notes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
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

    {{-- Parts modal --}}
    <x-modal name="machine-parts" max-width="2xl" :title="$this->partsMachine ? __('app.machines.parts_for', ['machine' => $this->partsMachine->name]) : __('app.machines.manage_parts')">
        @if ($this->partsMachine)
            <p class="mb-4 text-base text-slate-600">{{ __('app.machines.parts_help') }}</p>

            @if ($this->partsMachine->parts->isEmpty())
                <p class="rounded-xl bg-slate-50 p-4 text-base text-slate-600">
                    {{ __('app.machines.no_parts_attached') }}
                </p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($this->partsMachine->parts as $part)
                        <li wire:key="machine-part-{{ $part->id }}" class="flex min-h-14 items-center gap-3 py-2">
                            <span class="w-8 shrink-0 text-center text-sm font-semibold text-slate-400 tabular-nums">
                                {{ $loop->iteration }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold">{{ $part->name }}</span>
                                <span class="block font-mono text-sm text-slate-500">{{ $part->part_code }}</span>
                            </span>
                            <x-button
                                variant="ghost"
                                class="h-14 w-14 !px-0"
                                wire:click="movePartUp({{ $part->id }})"
                                :disabled="$loop->first"
                                aria-label="{{ __('app.actions.move_up') }}"
                            >
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                </svg>
                            </x-button>
                            <x-button
                                variant="ghost"
                                class="h-14 w-14 !px-0"
                                wire:click="movePartDown({{ $part->id }})"
                                :disabled="$loop->last"
                                aria-label="{{ __('app.actions.move_down') }}"
                            >
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </x-button>
                            <x-button
                                variant="danger"
                                class="h-14 w-14 !px-0"
                                wire:click="detachPart({{ $part->id }})"
                                wire:confirm="{{ __('app.machines.detach_confirm') }}"
                                aria-label="{{ __('app.actions.delete') }}"
                            >
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </x-button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4 border-t border-slate-200 pt-4">
                <label for="attach-part" class="mb-1 block text-base font-semibold">{{ __('app.parts.attach_part') }}</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-select id="attach-part" wire:model="attachPartId" class="w-full">
                        <option value="">{{ __('app.parts.part') }}…</option>
                        @foreach ($this->availableParts as $part)
                            <option value="{{ $part->id }}">{{ $part->name }} ({{ $part->part_code }})</option>
                        @endforeach
                    </x-select>
                    <x-button wire:click="attachPart" class="shrink-0">{{ __('app.actions.add') }}</x-button>
                </div>
                @error('attachPartId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.close') }}</x-button>
            </div>
        @endif
    </x-modal>

    {{-- Operators --}}
    <x-modal name="machine-operators" max-width="2xl" :title="$this->operatorsMachine ? __('app.machines.operators_for', ['machine' => $this->operatorsMachine->name]) : __('app.machines.manage_operators')">
        @if ($this->operatorsMachine)
            {{--
                Said plainly, because a list of names next to a machine reads
                like a permission list and is not one.
            --}}
            <x-alert type="info">{{ __('app.machines.operators_not_a_gate') }}</x-alert>

            <div class="mt-4">
                @if ($this->operatorsMachine->operators->isEmpty())
                    <p class="text-base text-slate-500">{{ __('app.machines.no_operators') }}</p>
                @else
                    <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
                        @foreach ($this->operatorsMachine->operators as $operator)
                            <li wire:key="machine-operator-{{ $operator->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-900">{{ $operator->full_name }}</p>
                                    <p class="text-sm text-slate-500">#{{ $operator->employee_number }}</p>
                                </div>
                                <x-button variant="ghost" wire:click="detachOperator({{ $operator->id }})">
                                    {{ __('app.actions.delete') }}
                                </x-button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="mt-5">
                <label for="attach-operator" class="mb-1 block text-base font-semibold">{{ __('app.machines.assign_operator') }}</label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-select id="attach-operator" wire:model="attachOperatorId" class="w-full">
                        <option value="">{{ __('app.runs.operator') }}…</option>
                        @foreach ($this->availableOperators as $candidate)
                            <option value="{{ $candidate->id }}">{{ $candidate->full_name }} (#{{ $candidate->employee_number }})</option>
                        @endforeach
                    </x-select>
                    <x-button wire:click="attachOperator" class="shrink-0">{{ __('app.actions.add') }}</x-button>
                </div>
                @error('attachOperatorId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.close') }}</x-button>
            </div>
        @endif
    </x-modal>

    {{-- Delete confirmation --}}
    <x-modal name="confirm-delete-machine" :title="__('app.machines.delete_machine')">
        @if ($this->deletingMachine)
            <p class="text-base text-slate-700">
                {{ __('app.machines.delete_confirm', ['name' => $this->deletingMachine->name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="deleteMachine">{{ __('app.actions.delete') }}</x-button>
            </div>
        @endif
    </x-modal>
</div>
