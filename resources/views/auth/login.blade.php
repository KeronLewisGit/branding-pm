{{--
    Login — email OR employee number in one field (BUILD-CONTRACT §6).
    layouts.guest is a plain slot-based Blade view, so it is used via
    @component, not <x-guest-layout>. All controls are >= 56px (.input/.btn).
--}}
@component('layouts.guest')
    <h2 class="mb-6 text-xl font-semibold text-slate-900">{{ __('app.auth.login_title') }}</h2>

    <form method="POST" action="{{ route('login') }}" class="space-y-5" novalidate>
        @csrf

        <div class="field">
            <x-label for="identifier">{{ __('app.auth.email_or_employee_number') }}</x-label>
            <x-input
                id="identifier"
                name="identifier"
                type="text"
                value="{{ old('identifier') }}"
                required
                autofocus
                autocomplete="username"
                autocapitalize="none"
                autocorrect="off"
                spellcheck="false"
            />
            <p class="text-sm text-slate-500">{{ __('app.auth.identifier_hint') }}</p>
            @error('identifier')
                <p class="text-base font-medium text-rose-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <x-label for="password">{{ __('app.auth.password') }}</x-label>
            <x-input
                id="password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
            />
            @error('password')
                <p class="text-base font-medium text-rose-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <x-checkbox id="remember" name="remember">{{ __('app.auth.remember_me') }}</x-checkbox>

        <x-button type="submit" class="w-full">{{ __('app.auth.login') }}</x-button>
    </form>

    {{-- Floor operators sign in on the shared tablet with a PIN instead. --}}
    <div class="mt-6 border-t border-slate-200 pt-6">
        <p class="mb-3 text-base text-slate-600">{{ __('app.auth.kiosk_prompt') }}</p>
        <x-button href="{{ route('kiosk.home') }}" variant="ghost" class="w-full">
            {{ __('app.auth.kiosk_link') }}
        </x-button>
    </div>
@endcomponent
