{{--
    Printable QR sticker sheet.

    The controls are screen-only (`print:hidden`); what reaches the paper is
    the sticker grid alone, three to a row, each sized to be readable from
    arm's length on a machine housing.

    Every sticker carries the machine code in plain text under the QR. A
    sticker in a print shop will be scuffed, over-sprayed and inked within a
    year — when the QR stops scanning, the code is what still works.
--}}
<div>
    {{-- ── Controls (screen only) ──────────────────────────────── --}}
    <div class="print:hidden">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">{{ __('app.qr.title') }}</h1>
                <p class="mt-1 text-base text-slate-600">{{ __('app.qr.subtitle') }}</p>
            </div>

            <x-button onclick="window.print()">{{ __('app.qr.print_sheet') }}</x-button>
        </div>

        <x-card class="mb-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.locations.location') }}</span>
                    <select wire:model.live="location" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                        <option value="">{{ __('app.machines.all_locations') }}</option>
                        @foreach ($locations as $locationOption)
                            <option value="{{ $locationOption->id }}">{{ $locationOption->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="flex items-end gap-3 pb-2">
                    <input type="checkbox" wire:model.live="includeInactive" class="h-6 w-6 rounded border-slate-300">
                    <span class="text-base text-slate-700">{{ __('app.qr.include_inactive') }}</span>
                </label>

                <div class="flex items-end gap-3">
                    <x-button variant="ghost" wire:click="selectAll">{{ __('app.qr.select_all') }}</x-button>
                    <x-button variant="ghost" wire:click="selectNone">{{ __('app.qr.select_none') }}</x-button>
                </div>
            </div>

            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-base text-amber-900">
                {{ __('app.qr.code_warning') }}
            </p>
        </x-card>

        {{-- Pick which machines get a sticker --}}
        <x-card class="mb-6">
            <h2 class="text-lg font-bold text-slate-900">{{ __('app.qr.choose_machines') }}</h2>
            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($machines as $machine)
                    <label wire:key="pick-{{ $machine->id }}" class="flex min-h-14 items-center gap-3 rounded-lg border border-slate-200 px-3">
                        <input type="checkbox" wire:model.live="selected.{{ $machine->id }}" class="h-6 w-6 rounded border-slate-300">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-base font-medium text-slate-800">{{ $machine->name }}</span>
                            <span class="block truncate font-mono text-sm text-slate-500">{{ $machine->code }}</span>
                        </span>
                        @unless ($machine->is_active)
                            <x-badge>{{ __('app.common.inactive') }}</x-badge>
                        @endunless
                    </label>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- ── The sheet itself ────────────────────────────────────── --}}
    @if ($chosen->isEmpty())
        <div class="print:hidden">
            <x-empty-state :title="__('app.qr.none_selected')" :description="__('app.qr.none_selected_hint')" />
        </div>
    @else
        <div class="rounded-xl border border-slate-200 bg-white p-6 print:rounded-none print:border-0 print:p-0 print:shadow-none">
            @foreach ($rows as $rowIndex => $row)
                <div class="grid grid-cols-3 gap-4 print:gap-2" wire:key="row-{{ $rowIndex }}">
                    @foreach ($row as $machine)
                        {{-- break-inside-avoid keeps a sticker from being cut
                             in half by a page break. --}}
                        <div wire:key="sticker-{{ $machine->id }}"
                             class="mb-4 break-inside-avoid rounded-xl border-2 border-dashed border-slate-400 p-4 text-center print:mb-2">
                            <p class="truncate text-base font-bold text-slate-900" title="{{ $machine->name }}">
                                {{ $machine->name }}
                            </p>
                            <p class="truncate text-xs uppercase tracking-wide text-slate-500">
                                {{ $machine->location?->name }}
                            </p>

                            <div class="mx-auto mt-2 flex h-40 w-40 items-center justify-center">
                                {!! $this->svg($machine) !!}
                            </div>

                            <p class="mt-2 font-mono text-lg font-bold tracking-wider text-slate-900">{{ $machine->code }}</p>
                            <p class="text-xs text-slate-500">{{ __('app.qr.scan_hint') }}</p>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>
