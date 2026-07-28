{{--
    "Tap your name" grid — precedes the PIN pad. Large tiles (>= 88px) with
    the employee number beneath the name; stylus-friendly search box.
--}}
<div class="mx-auto w-full max-w-4xl">
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-bold text-white">{{ __('app.kiosk.who_are_you') }}</h1>
        @if ($machineName !== null)
            <p class="mt-1 text-lg text-slate-300">
                {{ __('app.kiosk.picker_context', ['machine' => $machineName]) }}
            </p>
        @endif
    </div>

    <div class="field mb-6">
        <x-label for="operator-search" class="sr-only">{{ __('app.kiosk.search_operators') }}</x-label>
        <x-input
            id="operator-search"
            type="search"
            wire:model.live.debounce.250ms="search"
            placeholder="{{ __('app.kiosk.search_operators') }}"
            autocomplete="off"
            autocapitalize="none"
            spellcheck="false"
        />
    </div>

    @if ($operators->isEmpty())
        <p class="py-12 text-center text-xl text-slate-400">{{ __('app.kiosk.no_operators') }}</p>
    @else
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
            @foreach ($operators as $operator)
                <a
                    wire:key="operator-{{ $operator->id }}"
                    href="{{ route('kiosk.pin.show', array_filter([
                        'user' => $operator->id,
                        'run' => $runId,
                    ])) }}"
                    class="flex min-h-[88px] select-none flex-col items-center justify-center rounded-2xl bg-slate-800 px-4 py-3 text-center active:bg-slate-600"
                >
                    <span class="text-xl font-bold text-white">{{ $operator->full_name }}</span>
                    <span class="mt-1 text-base text-slate-300">{{ $operator->employee_number }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
