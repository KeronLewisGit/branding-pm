{{--
    403 for a tablet without a valid device cookie, rendered by the `kiosk`
    middleware (EnsureKioskDevice). Deliberately does not say whether the
    cookie was missing, unknown, or belongs to a deactivated device.
--}}
@component('layouts.kiosk')
    <div class="mx-auto flex w-full max-w-lg flex-col items-center py-12 text-center">
        <svg class="mb-6 h-16 w-16 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="5" y="11" width="14" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
            <line x1="12" y1="15" x2="12" y2="17" />
        </svg>

        <h1 class="mb-3 text-3xl font-bold text-white">{{ __('app.kiosk.not_enrolled_title') }}</h1>

        <p class="mb-2 text-xl text-slate-300">{{ __('app.kiosk.device_not_registered') }}</p>

        <p class="max-w-md text-lg text-slate-400">{{ __('app.kiosk.not_enrolled_help') }}</p>

        <div class="mt-8 w-full max-w-xs">
            <x-button href="{{ route('login') }}" variant="ghost" size="kiosk" class="w-full">
                {{ __('app.auth.login') }}
            </x-button>
        </div>
    </div>
@endcomponent
