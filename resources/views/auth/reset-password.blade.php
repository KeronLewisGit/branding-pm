{{--
    "Set a new password" — reached from the emailed link, which carries the
    token and the address. See NewPasswordController.
--}}
@component('layouts.guest')
    <h2 class="mb-2 text-xl font-semibold text-slate-900">{{ __('app.auth.reset_title') }}</h2>
    <p class="mb-6 text-base text-slate-600">{{ __('app.auth.reset_intro') }}</p>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <x-label for="email">{{ __('app.auth.email') }}</x-label>
            <x-input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $email) }}"
                required
                autocomplete="username"
                autocapitalize="none"
                autocorrect="off"
                spellcheck="false"
            />
            @error('email')
                <p class="text-base font-medium text-rose-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <x-label for="password">{{ __('app.auth.new_password') }}</x-label>
            <x-input
                id="password"
                name="password"
                type="password"
                required
                autofocus
                autocomplete="new-password"
            />
            <p class="text-sm text-slate-500">{{ __('app.auth.new_password_hint') }}</p>
            @error('password')
                <p class="text-base font-medium text-rose-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <x-label for="password_confirmation">{{ __('app.auth.confirm_password') }}</x-label>
            <x-input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                required
                autocomplete="new-password"
            />
            @error('password_confirmation')
                <p class="text-base font-medium text-rose-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <x-button type="submit" class="w-full">{{ __('app.auth.reset_submit') }}</x-button>
    </form>
@endcomponent
