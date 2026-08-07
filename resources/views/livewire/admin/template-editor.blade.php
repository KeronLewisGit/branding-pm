<div class="space-y-6">
    <x-page-header :title="$template->name">
        <p class="w-full text-base text-slate-600">
            {{ $template->machine->name }}
            ·
            <span class="font-semibold">{{ __('app.templates.version_current', ['version' => $template->version]) }}</span>
            ·
            @if ($template->is_active)
                <x-badge color="emerald">{{ __('app.common.active') }}</x-badge>
            @else
                <x-badge>{{ __('app.common.inactive') }}</x-badge>
            @endif
        </p>

        <x-slot:actions>
            <x-button variant="ghost" href="{{ route('admin.templates') }}">
                {{ __('app.templates.back_to_templates') }}
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Non-redirect actions flash here; Livewire re-renders only this
         component, so the layout's alert block never sees them. --}}
    @if (session('flash.success'))
        <x-alert type="success">{{ session('flash.success') }}</x-alert>
    @endif
    @if (session('flash.error'))
        <x-alert type="error">{{ session('flash.error') }}</x-alert>
    @endif

    {{-- Versioning: edits shape FUTURE runs only — snapshots are never rewritten --}}
    <x-alert type="info">
        <p class="font-semibold">{{ __('app.templates.version_current', ['version' => $template->version]) }}</p>
        <p>{{ __('app.templates.version_explainer') }}</p>
    </x-alert>

    @if ($this->openRunCount > 0)
        <x-alert type="warning">
            <p class="font-semibold">
                {{ trans_choice('app.templates.open_runs_warning', $this->openRunCount, ['count' => $this->openRunCount, 'version' => $template->version]) }}
            </p>
            <p>{{ __('app.templates.open_runs_warning_detail') }}</p>
        </x-alert>
    @endif

    {{-- ─── Section a: template settings ──────────────────────────── --}}
    <section class="card card-body" aria-labelledby="settings-heading">
        <h2 id="settings-heading" class="text-xl font-bold">{{ __('app.templates.settings') }}</h2>
        <p class="mt-1 text-base text-slate-600">{{ __('app.templates.settings_hint') }}</p>

        <form wire:submit="saveSettings" class="mt-4 space-y-4">
            <div>
                <label for="settings-name" class="mb-1 block text-base font-medium">{{ __('app.common.name') }}</label>
                <x-input id="settings-name" wire:model="name" maxlength="190" />
                @error('name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="settings-category" class="mb-1 block text-base font-medium">{{ __('app.templates.work_category') }}</label>
                    <x-select id="settings-category" wire:model="workCategory">
                        @foreach (App\Enums\WorkCategory::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                    @error('workCategory') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="settings-frequency" class="mb-1 block text-base font-medium">{{ __('app.templates.frequency') }}</label>
                    <x-select id="settings-frequency" wire:model.live="frequency">
                        @foreach (App\Enums\Frequency::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select>
                    @error('frequency') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Each frequency's question only appears when it applies --}}
                @if ($frequency === App\Enums\Frequency::Weekly->value)
                    <div>
                        <label for="settings-weekday" class="mb-1 block text-base font-medium">{{ __('app.templates.weekly_weekday') }}</label>
                        <x-select id="settings-weekday" wire:model="weeklyWeekday">
                            @foreach ($this->weekdayOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-select>
                        @error('weeklyWeekday') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @elseif ($frequency === App\Enums\Frequency::Monthly->value)
                    <div>
                        <label for="settings-monthly-day" class="mb-1 block text-base font-medium">{{ __('app.templates.monthly_day') }}</label>
                        <x-input id="settings-monthly-day" type="number" min="1" max="28" wire:model="monthlyDay" />
                        <p class="mt-1 text-sm text-slate-500">{{ __('app.templates.monthly_day_hint') }}</p>
                        @error('monthlyDay') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label for="settings-grace" class="mb-1 block text-base font-medium">{{ __('app.templates.grace_period_hours') }}</label>
                    <x-input id="settings-grace" type="number" min="0" max="720" wire:model="gracePeriodHours" />
                    @error('gracePeriodHours') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="settings-description" class="mb-1 block text-base font-medium">{{ __('app.templates.work_description') }}</label>
                <x-textarea id="settings-description" wire:model="workDescription" rows="3" />
                @error('workDescription') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-checkbox id="settings-per-shift" wire:model="perShift">{{ __('app.templates.per_shift') }}</x-checkbox>
                <p class="text-sm text-slate-500">{{ __('app.templates.per_shift_hint') }}</p>
            </div>

            <x-checkbox id="settings-signoff" wire:model="requiresSupervisorSignoff">{{ __('app.templates.requires_supervisor_signoff') }}</x-checkbox>

            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled">
                    {{ __('app.actions.save') }}
                </x-button>
            </div>
        </form>
    </section>

    {{-- ─── Section b: item builder ────────────────────────────────── --}}
    <section class="card card-body" aria-labelledby="items-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="items-heading" class="text-xl font-bold">{{ __('app.templates.items') }}</h2>

            @unless ($showItemForm)
                <x-button wire:click="startAddItem">
                    {{ __('app.templates.add_item') }}
                </x-button>
            @endunless
        </div>

        <p class="mt-1 text-base text-slate-600">{{ __('app.templates.items_keep_note') }}</p>

        @if ($items->isEmpty() && ! $showItemForm)
            <x-empty-state class="mt-4" :title="__('app.templates.no_items')">
                <x-slot:action>
                    <x-button wire:click="startAddItem">{{ __('app.templates.add_item') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <ol class="mt-4 divide-y divide-slate-200">
                @foreach ($items as $item)
                    <li wire:key="item-{{ $item->id }}" class="py-3">
                        @if ($showItemForm && $editingItemId === $item->id)
                            @include('livewire.admin.partials.template-item-form')
                        @else
                            <div @class(['flex flex-wrap items-center gap-3', 'opacity-50' => ! $item->is_active])>
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 font-bold tabular-nums" aria-hidden="true">
                                    {{ $item->sort_order }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-lg font-medium">{{ $item->description }}</p>

                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <x-badge color="sky">{{ $item->response_type->label() }}</x-badge>

                                        @if ($item->is_required)
                                            <x-badge color="amber">{{ __('app.common.required') }}</x-badge>
                                        @else
                                            <x-badge>{{ __('app.common.optional') }}</x-badge>
                                        @endif

                                        @if ($item->requires_photo_on_fail)
                                            <x-badge color="rose">{{ __('app.templates.requires_photo_on_fail') }}</x-badge>
                                        @endif

                                        @unless ($item->is_active)
                                            <x-badge>{{ __('app.templates.inactive_item_label') }}</x-badge>
                                        @endunless
                                    </div>

                                    @if ($item->guidance)
                                        <p class="mt-1 text-sm italic text-slate-600">{{ $item->guidance }}</p>
                                    @endif
                                </div>

                                {{-- Same icon vocabulary as every other action column. --}}
                                <div class="flex items-center gap-1">
                                    <x-icon-button
                                        icon="move_up"
                                        :label="__('app.actions.move_up')"
                                        class="disabled:opacity-30"
                                        wire:click="moveItemUp({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        :disabled="$loop->first"
                                    />

                                    <x-icon-button
                                        icon="move_down"
                                        :label="__('app.actions.move_down')"
                                        class="disabled:opacity-30"
                                        wire:click="moveItemDown({{ $item->id }})"
                                        wire:loading.attr="disabled"
                                        :disabled="$loop->last"
                                    />

                                    <x-icon-button icon="edit" :label="__('app.actions.edit')"
                                        wire:click="startEditItem({{ $item->id }})" />

                                    @if ($item->is_active)
                                        <x-icon-button
                                            icon="deactivate"
                                            :label="__('app.actions.deactivate')"
                                            wire:click="toggleItemActive({{ $item->id }})"
                                            wire:confirm="{{ __('app.templates.confirm_deactivate_item') }}"
                                        />
                                    @else
                                        <x-icon-button icon="activate" :label="__('app.actions.activate')"
                                            wire:click="toggleItemActive({{ $item->id }})" />
                                    @endif
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($showItemForm && $editingItemId === null)
            <div class="mt-4">
                @include('livewire.admin.partials.template-item-form')
            </div>
        @endif
    </section>

    {{-- ─── Section c: Used Parts list ─────────────────────────────── --}}
    <section class="card card-body" aria-labelledby="parts-heading">
        <h2 id="parts-heading" class="text-xl font-bold">{{ __('app.templates.used_parts') }}</h2>
        <p class="mt-1 text-base text-slate-600">{{ __('app.templates.parts_order_note') }}</p>

        {{-- Picker: part_code is a STRING (one code is literally "XXX") --}}
        <div class="mt-4">
            <label for="part-search" class="mb-1 block text-base font-medium">{{ __('app.parts.attach_part') }}</label>
            <x-input
                id="part-search"
                type="search"
                wire:model.live.debounce.300ms="partSearch"
                :placeholder="__('app.templates.part_search_placeholder')"
            />

            @if (trim($partSearch) !== '')
                @if ($this->availableParts->isEmpty())
                    <p class="mt-2 text-base text-slate-600">{{ __('app.templates.no_matching_parts') }}</p>
                @else
                    <ul class="mt-2 divide-y divide-slate-200 rounded-xl border border-slate-200">
                        @foreach ($this->availableParts as $part)
                            <li wire:key="part-option-{{ $part->id }}" class="flex min-h-14 items-center gap-3 px-4 py-2">
                                <span class="font-mono font-semibold">{{ $part->part_code }}</span>
                                <span class="min-w-0 flex-1 truncate">{{ $part->name }}</span>
                                @if ($part->unit)
                                    <span class="text-sm text-slate-500">{{ $part->unit }}</span>
                                @endif
                                <x-button variant="ghost" wire:click="attachPart({{ $part->id }})" wire:loading.attr="disabled">
                                    {{ __('app.actions.add') }}
                                </x-button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>

        @if ($templateParts->isEmpty())
            <p class="mt-4 text-base text-slate-600">{{ __('app.parts.no_parts') }}</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr class="text-left text-sm font-semibold uppercase tracking-wider text-slate-500">
                            <th scope="col">{{ __('app.templates.print_order') }}</th>
                            <th scope="col">{{ __('app.parts.part_code') }}</th>
                            <th scope="col">{{ __('app.common.name') }}</th>
                            <th scope="col">{{ __('app.parts.unit') }}</th>
                            <th scope="col" class="text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($templateParts as $templatePart)
                            <tr wire:key="template-part-{{ $templatePart->id }}">
                                <td class="tabular-nums">{{ $templatePart->sort_order }}</td>
                                <td class="font-mono font-semibold">{{ $templatePart->part->part_code }}</td>
                                <td>{{ $templatePart->part->name }}</td>
                                <td>{{ $templatePart->part->unit ?? '—' }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <x-icon-button
                                            icon="move_up"
                                            :label="__('app.actions.move_up')"
                                            class="disabled:opacity-30"
                                            wire:click="movePartUp({{ $templatePart->id }})"
                                            wire:loading.attr="disabled"
                                            :disabled="$loop->first"
                                        />

                                        <x-icon-button
                                            icon="move_down"
                                            :label="__('app.actions.move_down')"
                                            class="disabled:opacity-30"
                                            wire:click="movePartDown({{ $templatePart->id }})"
                                            wire:loading.attr="disabled"
                                            :disabled="$loop->last"
                                        />

                                        <x-icon-button
                                            icon="delete"
                                            variant="danger"
                                            :label="__('app.actions.delete')"
                                            wire:click="detachPart({{ $templatePart->id }})"
                                            wire:confirm="{{ __('app.templates.confirm_detach_part') }}"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
