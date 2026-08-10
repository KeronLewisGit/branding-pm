{{--
    "Ask for this browser to become a kiosk" — the operator's side.

    Rendered in the OFFICE layout, not the kiosk one: the person is signed in
    with a password on a browser that is not a kiosk yet, which is exactly the
    office context. The kiosk layout would be a promise the browser cannot
    keep.

    @component rather than <x-app-layout> because layouts/app.blade.php is a
    plain slot-based view, the same as dashboard.blade.php.
--}}
@component('layouts.app')
    @slot('title', __('app.kiosk_requests.title'))

    @slot('header')
        <x-page-header :title="__('app.kiosk_requests.title')" />
    @endslot

    <div class="mx-auto w-full max-w-2xl">
        {{-- What happens after they press the button. x-page-header renders a
             title and nothing else, so this belongs in the page body. --}}
        <p class="mb-6 text-lg text-slate-600">{{ __('app.kiosk_requests.intro') }}</p>

        @if (session('status'))
            <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="error" class="mb-6">{{ session('error') }}</x-alert>
        @endif

        @php($status = $pending?->status)

        @if ($alreadyEnrolled)
            {{-- Reached by a bookmark on a browser that is already a kiosk. --}}
            <x-card>
                <p class="text-lg font-semibold">{{ __('app.kiosk_requests.already_enrolled') }}</p>
                <div class="mt-4">
                    <x-button href="{{ route('kiosk.home') }}">{{ __('app.nav.kiosk_mode') }}</x-button>
                </div>
            </x-card>

        @elseif ($status === \App\Enums\KioskRequestStatus::Approved)
            {{--
                Approved and waiting to be redeemed. This screen is the only
                thing that can finish the job: enrolment sets a cookie, and
                only this browser can be given one.
            --}}
            <x-card>
                <p class="text-lg font-semibold">{{ __('app.kiosk_requests.approved_title') }}</p>
                <p class="mt-2 text-slate-600">{{ __('app.kiosk_requests.approved_body') }}</p>

                <form method="POST" action="{{ route('kiosk.request.claim') }}" class="mt-5">
                    @csrf
                    <x-button type="submit" size="lg">{{ __('app.kiosk_requests.claim') }}</x-button>
                </form>
            </x-card>

        @elseif ($status === \App\Enums\KioskRequestStatus::Pending)
            <x-card>
                <p class="text-lg font-semibold">{{ __('app.kiosk_requests.waiting_title') }}</p>
                <p class="mt-2 text-slate-600">
                    {{ __('app.kiosk_requests.waiting_body', ['when' => $pending->created_at->diffForHumans()]) }}
                </p>
            </x-card>

        @else
            @if ($status === \App\Enums\KioskRequestStatus::Declined)
                <x-alert type="error" class="mb-6">
                    <p class="font-semibold">{{ __('app.kiosk_requests.declined_title') }}</p>
                    @if ($pending->decline_reason)
                        <p class="mt-1">{{ $pending->decline_reason }}</p>
                    @endif
                    <p class="mt-2">{{ __('app.kiosk_requests.declined_retry') }}</p>
                </x-alert>
            @endif

            <x-card>
                <form method="POST" action="{{ route('kiosk.request.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-label for="suggested_name" required>{{ __('app.kiosk_requests.device_name') }}</x-label>
                        <x-input id="suggested_name" name="suggested_name" class="mt-1 w-full"
                                 value="{{ old('suggested_name', $suggestedName) }}" maxlength="120" required />
                        <p class="mt-1 text-sm text-slate-500">{{ __('app.kiosk_requests.device_name_hint') }}</p>
                        @error('suggested_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <x-label for="note">{{ __('app.kiosk_requests.note') }}</x-label>
                        <x-textarea id="note" name="note" :rows="3" class="mt-1 w-full" maxlength="500">{{ old('note') }}</x-textarea>
                        <p class="mt-1 text-sm text-slate-500">{{ __('app.kiosk_requests.note_hint') }}</p>
                        @error('note') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <x-button type="submit" size="lg">{{ __('app.kiosk_requests.submit') }}</x-button>
                </form>
            </x-card>
        @endif
    </div>
@endcomponent
