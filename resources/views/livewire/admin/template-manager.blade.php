<div class="space-y-6">
    <x-page-header :title="__('app.templates.title')">
        <x-slot:actions>
            @can('create', App\Models\ChecklistTemplate::class)
                <x-button wire:click="openCreateModal">
                    {{ __('app.templates.add_template') }}
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Non-redirect actions (duplicate / toggle / delete) flash here; Livewire
         re-renders only this component, so the layout's alert block never sees them. --}}
    @if (session('flash.success'))
        <x-alert type="success">{{ session('flash.success') }}</x-alert>
    @endif
    @if (session('flash.error'))
        <x-alert type="error">{{ session('flash.error') }}</x-alert>
    @endif

    {{-- Filters — mirrored into the URL by the component, so the view is shareable --}}
    <div class="filter-bar">
        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <div class="xl:col-span-2">
                <label for="template-search" class="sr-only">{{ __('app.actions.search') }}</label>
                <x-input
                    id="template-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('app.templates.search_placeholder')"
                />
            </div>

            <div>
                <label for="filter-machine" class="sr-only">{{ __('app.machines.machine') }}</label>
                <x-select id="filter-machine" wire:model.live="machineFilter">
                    <option value="">{{ __('app.templates.all_machines') }}</option>
                    @foreach ($this->machines as $machine)
                        <option value="{{ $machine->id }}">{{ $machine->name }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <label for="filter-category" class="sr-only">{{ __('app.templates.work_category') }}</label>
                <x-select id="filter-category" wire:model.live="categoryFilter">
                    <option value="">{{ __('app.templates.all_categories') }}</option>
                    @foreach (App\Enums\WorkCategory::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <label for="filter-frequency" class="sr-only">{{ __('app.templates.frequency') }}</label>
                <x-select id="filter-frequency" wire:model.live="frequencyFilter">
                    <option value="">{{ __('app.templates.all_frequencies') }}</option>
                    @foreach (App\Enums\Frequency::options() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-select>
            </div>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label for="filter-active" class="sr-only">{{ __('app.common.status') }}</label>
                    <x-select id="filter-active" wire:model.live="activeFilter">
                        <option value="">{{ __('app.templates.all_states') }}</option>
                        <option value="1">{{ __('app.common.active') }}</option>
                        <option value="0">{{ __('app.common.inactive') }}</option>
                    </x-select>
                </div>

                <x-button variant="ghost" wire:click="clearFilters">
                    {{ __('app.actions.clear') }}
                </x-button>
            </div>
        </div>
    </div>

    @if ($templates->isEmpty())
        <x-empty-state
            :title="__('app.templates.no_templates')"
            :description="__('app.common.no_results')"
        >
            <x-slot:action>
                <x-button variant="ghost" wire:click="clearFilters">
                    {{ __('app.actions.clear') }}
                </x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="card overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr class="text-left text-sm font-semibold uppercase tracking-wider text-slate-500">
                        <th scope="col">{{ __('app.common.name') }}</th>
                        <th scope="col">{{ __('app.templates.work_category') }}</th>
                        <th scope="col">{{ __('app.templates.frequency') }}</th>
                        <th scope="col">{{ __('app.templates.per_shift_short') }}</th>
                        <th scope="col" class="text-right">{{ __('app.templates.item_count') }}</th>
                        <th scope="col" class="text-right">{{ __('app.templates.version') }}</th>
                        <th scope="col">{{ __('app.common.status') }}</th>
                        <th scope="col">{{ __('app.templates.last_run') }}</th>
                        <th scope="col" class="text-right">{{ __('app.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @php($currentMachineId = null)
                    @foreach ($templates as $template)
                        {{-- Rows arrive ordered by machine name, so a header row per machine groups them --}}
                        @if ($template->machine_id !== $currentMachineId)
                            @php($currentMachineId = $template->machine_id)
                            <tr wire:key="machine-group-{{ $template->machine_id }}" class="bg-slate-100">
                                <th scope="colgroup" colspan="9" class="text-left text-lg font-bold text-slate-800">
                                    {{ $template->machine->name }}
                                </th>
                            </tr>
                        @endif

                        <tr wire:key="template-{{ $template->id }}" @class(['opacity-60' => ! $template->is_active])>
                            <td>
                                <a
                                    href="{{ route('admin.templates.edit', $template) }}"
                                    class="inline-flex min-h-14 items-center font-semibold text-sky-700 underline-offset-2 hover:underline"
                                >
                                    {{ $template->name }}
                                </a>
                            </td>
                            <td>
                                <x-badge>{{ $template->work_category->label() }}</x-badge>
                            </td>
                            <td>{{ $template->frequency->label() }}</td>
                            <td>
                                {{ $template->per_shift ? __('app.actions.yes') : '—' }}
                            </td>
                            <td class="text-right tabular-nums">{{ $template->active_items_count }}</td>
                            <td class="text-right tabular-nums">v{{ $template->version }}</td>
                            <td>
                                @if ($template->is_active)
                                    <x-badge color="emerald">{{ __('app.common.active') }}</x-badge>
                                @else
                                    <x-badge>{{ __('app.common.inactive') }}</x-badge>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $template->runs_max_scheduled_for
                                    ? \Illuminate\Support\Carbon::parse($template->runs_max_scheduled_for)->format('d M Y')
                                    : '—' }}
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $template)
                                        <x-icon-button icon="edit" :label="__('app.actions.edit')"
                                            :href="route('admin.templates.edit', $template)" />
                                    @endcan

                                    @can('create', App\Models\ChecklistTemplate::class)
                                        <x-icon-button
                                            icon="duplicate"
                                            :label="__('app.templates.duplicate')"
                                            wire:click="duplicateTemplate({{ $template->id }})"
                                            wire:loading.attr="disabled"
                                        />
                                    @endcan

                                    @can('update', $template)
                                        @if ($template->is_active)
                                            <x-icon-button
                                                icon="deactivate"
                                                :label="__('app.actions.deactivate')"
                                                wire:click="toggleActive({{ $template->id }})"
                                                wire:confirm="{{ __('app.templates.confirm_deactivate', ['name' => $template->name]) }}"
                                            />
                                        @else
                                            <x-icon-button icon="activate" :label="__('app.actions.activate')"
                                                wire:click="toggleActive({{ $template->id }})" />
                                        @endif
                                    @endcan

                                    @can('delete', $template)
                                        {{-- Always rendered: when runs exist the component refuses and
                                             explains that the run history blocks deletion. --}}
                                        <x-icon-button
                                            icon="delete"
                                            variant="danger"
                                            :label="__('app.actions.delete')"
                                            wire:click="deleteTemplate({{ $template->id }})"
                                            wire:confirm="{{ __('app.templates.confirm_delete') }}"
                                        />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            {{ $templates->links() }}
        </div>
    @endif

    {{-- Create-template modal --}}
    <x-modal name="create-template" :title="__('app.templates.add_template')" maxWidth="2xl">
        <form wire:submit="createTemplate" class="space-y-4">
            <div>
                <label for="create-machine" class="mb-1 block text-base font-medium">{{ __('app.machines.machine') }}</label>
                <x-select id="create-machine" wire:model="machineId">
                    <option value="">{{ __('app.common.none') }}</option>
                    @foreach ($this->machines as $machine)
                        <option value="{{ $machine->id }}">
                            {{ $machine->name }}@unless ($machine->is_active) — {{ __('app.common.inactive') }}@endunless
                        </option>
                    @endforeach
                </x-select>
                @error('machineId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="create-name" class="mb-1 block text-base font-medium">{{ __('app.common.name') }}</label>
                <x-input id="create-name" wire:model="name" maxlength="190" />
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="create-category" class="mb-1 block text-base font-medium">{{ __('app.templates.work_category') }}</label>
                    <x-select id="create-category" wire:model="workCategory">
                        @foreach (App\Enums\WorkCategory::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                    @error('workCategory') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="create-frequency" class="mb-1 block text-base font-medium">{{ __('app.templates.frequency') }}</label>
                    <x-select id="create-frequency" wire:model.live="frequency">
                        @foreach (App\Enums\Frequency::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                    @error('frequency') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Only ask the question the chosen frequency actually needs --}}
            @if ($frequency === App\Enums\Frequency::Weekly->value)
                <div>
                    <label for="create-weekday" class="mb-1 block text-base font-medium">{{ __('app.templates.weekly_weekday') }}</label>
                    <x-select id="create-weekday" wire:model="weeklyWeekday">
                        @foreach ($this->weekdayOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                    @error('weeklyWeekday') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            @elseif ($frequency === App\Enums\Frequency::Monthly->value)
                <div>
                    <label for="create-monthly-day" class="mb-1 block text-base font-medium">{{ __('app.templates.monthly_day') }}</label>
                    <x-input id="create-monthly-day" type="number" min="1" max="28" wire:model="monthlyDay" />
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.templates.monthly_day_hint') }}</p>
                    @error('monthlyDay') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="create-description" class="mb-1 block text-base font-medium">{{ __('app.templates.work_description') }}</label>
                <x-textarea id="create-description" wire:model="workDescription" rows="3" />
                @error('workDescription') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-checkbox id="create-per-shift" wire:model="perShift">{{ __('app.templates.per_shift') }}</x-checkbox>
                <p class="text-sm text-slate-500">{{ __('app.templates.per_shift_hint') }}</p>
            </div>

            <x-checkbox id="create-signoff" wire:model="requiresSupervisorSignoff">{{ __('app.templates.requires_supervisor_signoff') }}</x-checkbox>

            <div>
                <label for="create-grace" class="mb-1 block text-base font-medium">{{ __('app.templates.grace_period_hours') }}</label>
                <x-input id="create-grace" type="number" min="0" max="720" wire:model="gracePeriodHours" />
                @error('gracePeriodHours') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <x-button variant="ghost" x-on:click="show = false">
                    {{ __('app.actions.cancel') }}
                </x-button>
                <x-button type="submit" wire:loading.attr="disabled">
                    {{ __('app.actions.create') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</div>
