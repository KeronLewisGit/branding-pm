{{--
    Choose your own password.

    Rendered in the guest layout even though the person IS signed in. When the
    change is forced there is nowhere else they may go, so the office chrome
    would be a menu of links that all bounce straight back here.
--}}
@component('layouts.guest')
    @slot('title', __('app.auth.change_title'))

    <h1 class="text-2xl font-bold text-slate-900">{{ __('app.auth.change_title') }}</h1>

    @if ($forced)
        {{-- Said before the form, because somebody who was not expecting this
             screen needs to know why it is in the way. --}}
        <p class="mt-2 text-lg text-slate-600">{{ __('app.auth.change_forced_intro') }}</p>
    @else
        <p class="mt-2 text-lg text-slate-600">{{ __('app.auth.change_intro') }}</p>
    @endif

    @if (session('status'))
        <x-alert type="success" class="mt-6">{{ session('status') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('password.change.store') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-label for="current_password" required>{{ __('app.auth.change_current') }}</x-label>
            <x-input id="current_password" type="password" name="current_password"
                     class="mt-1 w-full" autocomplete="current-password" required autofocus />
            <p class="mt-1 text-sm text-slate-500">{{ __('app.auth.change_current_hint') }}</p>
            @error('current_password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="password" required>{{ __('app.auth.change_new') }}</x-label>
            <x-input id="password" type="password" name="password"
                     class="mt-1 w-full" autocomplete="new-password" required />
            <p class="mt-1 text-sm text-slate-500">{{ __('app.auth.change_new_hint') }}</p>
            @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-label for="password_confirmation" required>{{ __('app.auth.change_confirm') }}</x-label>
            <x-input id="password_confirmation" type="password" name="password_confirmation"
                     class="mt-1 w-full" autocomplete="new-password" required />
        </div>

        <div class="pt-2">
            <x-button type="submit" size="lg">{{ __('app.auth.change_button') }}</x-button>
        </div>
    </form>

    {{-- Outside the form above: a form inside a form is invalid HTML, and
         browsers resolve it by dropping one of them — usually not the one you
         expected.

         A way out even when the change is forced. Somebody who cannot get past
         this screen must still be able to leave, rather than being held in the
         application by the thing meant to protect them. --}}
    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-base font-semibold text-slate-500 hover:underline">
            {{ __('app.nav.logout') }}
        </button>
    </form>
@endcomponent
