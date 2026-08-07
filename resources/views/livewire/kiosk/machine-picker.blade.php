<div>
    <x-slot:header>
        <p class="truncate text-2xl font-bold text-white">{{ __('app.kiosk.pick_machine') }}</p>
        <p class="truncate text-base text-slate-300">{{ __('app.kiosk.scan_hint') }}</p>
    </x-slot:header>

    {{-- Location filter — big tab buttons, never a <select> on the kiosk --}}
    <div class="mb-6 flex flex-wrap gap-3" role="tablist" aria-label="{{ __('app.kiosk.filter_by_location') }}">
        <button
            type="button"
            wire:click="$set('locationId', '')"
            role="tab"
            aria-selected="{{ $locationId === '' ? 'true' : 'false' }}"
            class="min-h-[72px] rounded-2xl border-2 px-8 text-xl font-bold
                {{ $locationId === '' ? 'border-white bg-white text-slate-900' : 'border-slate-600 bg-slate-900 text-slate-100' }}"
        >
            {{ __('app.kiosk.all_locations') }}
        </button>
        @foreach ($locations as $location)
            <button
                type="button"
                wire:key="loc-{{ $location->id }}"
                wire:click="$set('locationId', '{{ $location->id }}')"
                role="tab"
                aria-selected="{{ (int) $locationId === $location->id ? 'true' : 'false' }}"
                class="min-h-[72px] rounded-2xl border-2 px-8 text-xl font-bold
                    {{ (int) $locationId === $location->id ? 'border-white bg-white text-slate-900' : 'border-slate-600 bg-slate-900 text-slate-100' }}"
            >
                {{ $location->name }}
            </button>
        @endforeach
    </div>

    @if ($machines->isEmpty())
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center text-xl text-slate-300">
            {{ __('app.machines.no_machines') }}
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($machines as $machine)
                @php
                    $machineStatus = $statuses[$machine->id] ?? 'none';
                    $hasBreakdown = in_array($machine->id, $breakdownIds, true);

                    // Literal classes so Tailwind JIT compiles them. Colour is
                    // ALWAYS paired with the text label below — never alone.
                    [$dotClass, $statusLabel] = match ($machineStatus) {
                        'due' => ['bg-slate-400', __('app.kiosk.status_due')],
                        'in_progress' => ['bg-amber-500', __('app.kiosk.status_in_progress')],
                        'done' => ['bg-emerald-600', __('app.kiosk.status_done')],
                        'overdue' => ['bg-red-700', __('app.kiosk.status_overdue')],
                        default => [null, __('app.kiosk.status_none')],
                    };
                @endphp

                <a
                    href="{{ route('kiosk.machine', ['code' => $machine->code]) }}"
                    wire:key="machine-{{ $machine->id }}"
                    class="flex min-h-[140px] flex-col justify-between rounded-2xl p-5 active:bg-slate-800
                        {{ $hasBreakdown
                            ? 'border-4 border-red-600 bg-red-950'
                            : 'border-2 border-slate-700 bg-slate-900' }}"
                >
                    <div>
                        @if ($hasBreakdown)
                            <p class="mb-2 inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-1 text-base font-bold uppercase tracking-wide text-white">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                </svg>
                                {{ __('app.issues.open_breakdown_flag') }}
                            </p>
                        @endif
                        <p class="text-2xl font-bold text-white">{{ $machine->name }}</p>
                        <p class="mt-1 text-base text-slate-300">{{ $machine->location->name }}</p>
                    </div>

                    <p class="mt-4 inline-flex items-center gap-2 text-lg font-semibold {{ $machineStatus === 'none' ? 'text-slate-400' : 'text-slate-100' }}">
                        @if ($dotClass !== null)
                            <span class="status-dot {{ $dotClass }}" aria-hidden="true"></span>
                        @endif
                        {{ $statusLabel }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif
</div>
