{{--
    "I have forgotten my password" — asks for an email address and says
    nothing about whether it is one we know. See PasswordResetLinkController.

    layouts.guest is a plain slot-based Blade view, so it is used via
    @component, not <x-guest-layout>. All controls are >= 56px (.input/.btn).
--}}
@component('layouts.guest')
    <h2 class="mb-2 text-xl font-semibold text-slate-900">{{ __('app.auth.forgot_title') }}</h2>
    <p class="mb-6 text-base text-slate-600">{{ __('app.auth.forgot_intro') }}</p>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4" role="status">
            <p class="text-base font-medium text-emerald-900">{{ session('status') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5" novalidate>
        @csrf

        <div class="field">
            <x-label for="email">{{ __('app.auth.email') }}</x-label>
            <x-input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                autocapitalize="none"
                autocorrect="off"
                spellcheck="false"
            />
            @error('email')
                <p class="text-base font-medium text-rose-600" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <x-button type="submit" class="w-full">{{ __('app.auth.forgot_submit') }}</x-button>
    </form>

    {{--
        Said here rather than as an error after the fact: an operator has no
        email address to type, and nothing this form does will help them.
    --}}
    <div class="mt-6 border-t border-slate-200 pt-6">
        <p class="mb-3 text-base text-slate-600">{{ __('app.auth.forgot_no_email') }}</p>
        <x-button href="{{ route('login') }}" variant="ghost" class="w-full">
            {{ __('app.auth.back_to_login') }}
        </x-button>
    </div>
@endcomponent
