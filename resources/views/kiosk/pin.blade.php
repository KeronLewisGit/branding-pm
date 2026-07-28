{{--
    Kiosk PIN pad. Rendered by Kiosk\KioskSessionController::create() with:
      $operator — App\Models\User (active, has a PIN)
      $run      — ?App\Models\ChecklistRun (what they are signing in for)

    72px+ keypad targets for gloved hands, masked PIN display, no hover-only
    affordances. The PIN travels only as a hidden POST field — never in a
    URL, never flashed back, never logged.
--}}
@component('layouts.kiosk')
    @php
        $run = $run ?? null;
        $minLength = (int) config('checklists.pin_min_length');
        $maxLength = (int) config('checklists.pin_max_length');
        $maxAttempts = (int) config('checklists.pin_max_attempts');

        // Same key the controller throttles on — single source of truth.
        $device = \App\Http\Middleware\EnsureKioskDevice::device(request());
        $throttleKey = $device !== null
            ? \App\Http\Controllers\Kiosk\KioskSessionController::throttleKey((string) $operator->id, $device)
            : null;
        $isLocked = $throttleKey !== null
            && \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, $maxAttempts);
        $lockedMinutes = $isLocked
            ? max(1, (int) ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey) / 60))
            : 0;
        $attemptsRemaining = session('pin_attempts_remaining');
    @endphp

    <div class="mx-auto w-full max-w-md">

        {{-- Who is signing, and for what --}}
        <div class="mb-6 text-center">
            <p class="text-3xl font-bold text-white">{{ $operator->full_name }}</p>
            <p class="mt-1 text-lg text-slate-300">
                {{ __('app.kiosk.employee_number') }}: {{ $operator->employee_number }}
            </p>
            @if ($run !== null)
                <p class="mt-3 rounded-xl bg-slate-900 px-4 py-3 text-lg text-slate-200">
                    {{ __('app.kiosk.signing_for', [
                        'checklist' => $run->template?->name,
                        'machine' => $run->machine?->name,
                    ]) }}
                </p>
            @endif
        </div>

        @if ($isLocked)
            {{-- Lockout state, stated plainly. Survives reloads (RateLimiter). --}}
            <x-alert type="error" class="mb-6">
                <p class="text-xl font-bold">{{ __('app.kiosk.locked_title') }}</p>
                <p class="mt-1">{{ __('app.kiosk.pin_locked', ['minutes' => $lockedMinutes]) }}</p>
            </x-alert>

            <x-button href="{{ route('kiosk.home') }}" variant="ghost" size="kiosk" class="w-full">
                {{ __('app.kiosk.not_you') }}
            </x-button>
        @else
            <form
                method="POST"
                action="{{ route('kiosk.pin') }}"
                x-data="{
                    pin: '',
                    max: {{ $maxLength }},
                    min: {{ $minLength }},
                    add(digit) {
                        if (this.pin.length < this.max) { this.pin += digit; }
                    },
                    backspace() { this.pin = this.pin.slice(0, -1); },
                    clearAll() { this.pin = ''; },
                }"
            >
                @csrf
                <input type="hidden" name="user_id" value="{{ $operator->id }}">
                @if ($run !== null)
                    <input type="hidden" name="run_id" value="{{ $run->id }}">
                @endif
                <input type="hidden" name="pin" :value="pin">

                <p class="text-center text-2xl font-semibold text-white">{{ __('app.kiosk.enter_pin') }}</p>
                <p class="mt-1 text-center text-base text-slate-400">
                    {{ __('app.kiosk.pin_length', ['min' => $minLength, 'max' => $maxLength]) }}
                </p>

                {{-- Masked PIN display: filled/empty positions, never digits --}}
                <div class="my-6 flex items-center justify-center gap-3" aria-hidden="true">
                    @for ($i = 0; $i < $maxLength; $i++)
                        <span
                            class="h-5 w-5 rounded-full border-2 transition-colors"
                            :class="pin.length > {{ $i }} ? 'border-white bg-white' : 'border-slate-500'"
                        ></span>
                    @endfor
                </div>

                @error('pin')
                    <x-alert type="error" class="mb-4">{{ $message }}</x-alert>
                @enderror

                @if ($attemptsRemaining !== null && ! $isLocked)
                    <p class="mb-4 text-center text-lg font-semibold text-amber-400" role="status">
                        {{ __('app.kiosk.attempts_remaining', ['count' => $attemptsRemaining]) }}
                    </p>
                @endif

                {{-- Keypad — every target >= 72px, active states, no hover --}}
                <div class="grid grid-cols-3 gap-3">
                    @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
                        <button
                            type="button"
                            x-on:click="add('{{ $digit }}')"
                            class="min-h-[72px] select-none rounded-2xl bg-slate-800 text-3xl font-bold text-white active:bg-slate-600"
                        >{{ $digit }}</button>
                    @endforeach

                    <button
                        type="button"
                        x-on:click="clearAll()"
                        class="min-h-[72px] select-none rounded-2xl bg-slate-800 text-xl font-semibold text-slate-300 active:bg-slate-600"
                    >{{ __('app.actions.clear') }}</button>

                    <button
                        type="button"
                        x-on:click="add('0')"
                        class="min-h-[72px] select-none rounded-2xl bg-slate-800 text-3xl font-bold text-white active:bg-slate-600"
                    >0</button>

                    <button
                        type="button"
                        x-on:click="backspace()"
                        aria-label="{{ __('app.kiosk.backspace') }}"
                        class="flex min-h-[72px] select-none items-center justify-center rounded-2xl bg-slate-800 text-slate-300 active:bg-slate-600"
                    >
                        <svg class="h-9 w-9" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z" />
                            <line x1="18" y1="9" x2="12" y2="15" />
                            <line x1="12" y1="9" x2="18" y2="15" />
                        </svg>
                    </button>
                </div>

                <x-button type="submit" size="kiosk" class="mt-6 w-full" x-bind:disabled="pin.length < min">
                    {{ __('app.kiosk.sign_in') }}
                </x-button>
            </form>

            <div class="mt-4">
                <x-button href="{{ route('kiosk.home') }}" variant="ghost" size="kiosk" class="w-full">
                    {{ __('app.kiosk.not_you') }}
                </x-button>
            </div>
        @endif
    </div>
@endcomponent
