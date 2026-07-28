<div>
    @if ($state === 'ok' && $machine !== null)
        <x-slot:header>
            <p class="truncate text-2xl font-bold text-white">{{ $machine->name }}</p>
            <p class="truncate text-base text-slate-300">
                {{ $machine->location->name }}
                @if ($machine->location->floor) · {{ $machine->location->floor }} @endif
            </p>
        </x-slot:header>

        {{-- Header block, as on the paper form: equipment, location, building/floor --}}
        <div class="mb-6 grid grid-cols-1 gap-x-8 gap-y-3 rounded-2xl border border-slate-700 bg-slate-900 p-5 sm:grid-cols-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ __('app.kiosk.equipment') }}</p>
                <p class="text-xl font-bold text-white">{{ $machine->name }}</p>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ __('app.locations.location') }}</p>
                <p class="text-xl text-slate-100">{{ $machine->location->name }}</p>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-400">{{ __('app.locations.building') }} / {{ __('app.locations.floor') }}</p>
                <p class="text-xl text-slate-100">
                    {{ $machine->location->site?->name ?? '—' }}
                    @if ($machine->location->floor) · {{ $machine->location->floor }} @endif
                </p>
            </div>
        </div>

        @if ($runsByShift->isEmpty())
            {{-- Nothing due today — say so plainly, never a blank screen --}}
            <div class="rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center">
                <p class="text-2xl font-bold text-white">{{ __('app.kiosk.nothing_due') }}</p>
                @if ($lastCompleted !== null)
                    <p class="mt-3 text-lg text-slate-300">
                        {{ __('app.kiosk.last_completed', ['date' => $lastCompleted->scheduled_for->format('D j M Y')]) }}
                    </p>
                @endif
                <div class="mt-8">
                    <x-button size="kiosk" variant="ghost" href="{{ route('kiosk.home') }}">
                        {{ __('app.kiosk.back_to_kiosk') }}
                    </x-button>
                </div>
            </div>
        @else
            @foreach (['day', 'night', 'all'] as $shiftValue)
                @continue(! $runsByShift->has($shiftValue))
                @php($shiftRuns = $runsByShift->get($shiftValue))

                {{--
                    Both shifts due → each shift gets an unmissable full-width
                    banner AND a distinct card colour, so a night operator
                    cannot open the day sheet by accident. Colour is never the
                    only cue: the banner text and the per-card badge repeat it.
                --}}
                @if ($hasBothShifts && $shiftValue !== 'all')
                    <div class="mb-3 mt-8 first:mt-0 flex min-h-[72px] items-center gap-4 rounded-2xl px-6
                        {{ $shiftValue === 'day' ? 'bg-amber-400 text-slate-950' : 'bg-indigo-950 text-white ring-2 ring-indigo-400' }}">
                        @if ($shiftValue === 'day')
                            <svg class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2.25a.75.75 0 0 1 .75.75v2a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75ZM7.5 12a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM18.894 6.166a.75.75 0 0 0-1.06-1.06l-1.415 1.414a.75.75 0 1 0 1.06 1.06l1.415-1.414ZM21.75 12a.75.75 0 0 1-.75.75h-2a.75.75 0 0 1 0-1.5h2a.75.75 0 0 1 .75.75ZM17.834 18.894a.75.75 0 0 0 1.06-1.06l-1.414-1.415a.75.75 0 1 0-1.06 1.06l1.414 1.415ZM12 18.25a.75.75 0 0 1 .75.75v2a.75.75 0 0 1-1.5 0v-2a.75.75 0 0 1 .75-.75ZM7.641 17.48a.75.75 0 1 0-1.06-1.061l-1.415 1.414a.75.75 0 0 0 1.061 1.06l1.414-1.414ZM5.75 12a.75.75 0 0 1-.75.75H3a.75.75 0 0 1 0-1.5h2a.75.75 0 0 1 .75.75ZM6.581 7.58a.75.75 0 0 0 1.06-1.06L6.227 5.104a.75.75 0 0 0-1.06 1.06L6.58 7.58Z" />
                            </svg>
                        @else
                            <svg class="h-9 w-9" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.7-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162Z" clip-rule="evenodd" />
                            </svg>
                        @endif
                        <p class="text-3xl font-black uppercase tracking-widest">
                            {{ $shiftValue === 'day' ? __('app.kiosk.day_shift') : __('app.kiosk.night_shift') }}
                        </p>
                    </div>
                @endif

                <div class="space-y-4">
                    @foreach ($shiftRuns as $run)
                        <a
                            href="{{ route('runs.show', $run) }}"
                            wire:key="run-{{ $run->id }}"
                            class="block rounded-2xl p-6 active:bg-slate-800
                                {{ $hasBothShifts && $shiftValue === 'day' ? 'border-2 border-amber-400 bg-slate-900' : '' }}
                                {{ $hasBothShifts && $shiftValue === 'night' ? 'border-2 border-indigo-400 bg-indigo-950' : '' }}
                                {{ ! ($hasBothShifts && $shiftValue !== 'all') ? 'border-2 border-slate-700 bg-slate-900' : '' }}"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-2xl font-bold text-white">{{ $run->template->name }}</p>
                                    <p class="mt-1 text-base font-semibold uppercase tracking-wide text-slate-400">
                                        {{ __('app.templates.work_category') }}: {{ $run->template->work_category->label() }}
                                    </p>
                                </div>

                                @if ($run->shift->value !== 'all')
                                    <span class="shrink-0 rounded-xl px-4 py-2 text-xl font-black uppercase tracking-wider
                                        {{ $run->shift->value === 'day' ? 'bg-amber-400 text-slate-950' : 'bg-indigo-500 text-white' }}">
                                        {{ $run->shift->label() }}
                                    </span>
                                @endif
                            </div>

                            <p class="mt-3 text-lg text-slate-300">{{ $run->template->work_description }}</p>

                            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 text-lg">
                                <x-status-dot :status="$run->status" class="text-slate-100" />
                                <span class="font-semibold tabular-nums text-slate-100">
                                    {{ __('app.runs.progress', ['done' => $run->items_done_count, 'total' => $run->items_total_count]) }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endforeach

            <div class="mt-8">
                <x-button size="kiosk" variant="ghost" href="{{ route('kiosk.home') }}">
                    {{ __('app.kiosk.back_to_kiosk') }}
                </x-button>
            </div>
        @endif
    @else
        {{-- Unknown, inactive or out-of-scope machine: clear kiosk copy, never a 404 --}}
        <x-slot:header>
            <p class="truncate text-2xl font-bold text-white">{{ __('app.kiosk.title') }}</p>
        </x-slot:header>

        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center">
            @if ($state === 'unknown')
                <p class="text-2xl font-bold text-white">{{ __('app.kiosk.machine_unknown') }}</p>
                <p class="mt-3 text-lg text-slate-300">{{ __('app.kiosk.machine_unknown_hint', ['code' => $code]) }}</p>
            @elseif ($state === 'inactive')
                <p class="text-2xl font-bold text-white">{{ __('app.kiosk.machine_inactive', ['name' => $machine?->name ?? $code]) }}</p>
                <p class="mt-3 text-lg text-slate-300">{{ __('app.kiosk.machine_inactive_hint') }}</p>
            @else
                <p class="text-2xl font-bold text-white">{{ __('app.kiosk.machine_forbidden') }}</p>
            @endif

            <div class="mt-8">
                <x-button size="kiosk" href="{{ route('kiosk.home') }}">
                    {{ __('app.kiosk.back_to_kiosk') }}
                </x-button>
            </div>
        </div>
    @endif
</div>
