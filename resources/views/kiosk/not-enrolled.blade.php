{{--
    403 for a browser without a valid device cookie, rendered by the `kiosk`
    middleware (EnsureKioskDevice). Deliberately does not say whether the
    cookie was missing, unknown, or belongs to a deactivated device.

    The wording follows App\Support\DeviceType so somebody at a laptop is not
    told that "this tablet" needs enrolling. That is presentation only — the
    verdict comes from a User-Agent, which the client controls.
--}}
@php
    $deviceType = ($deviceType ?? \App\Support\DeviceType::Unknown);
    $key = $deviceType->value;
    $mayBeTablet = (bool) ($mayBeTablet ?? false);
@endphp

@component('layouts.kiosk')
    <div
        class="mx-auto flex w-full max-w-lg flex-col items-center py-12 text-center"
        @if ($mayBeTablet)
            {{-- Tablet wording, for the script below to swap in. --}}
            data-tablet-title="{{ __('app.kiosk.not_enrolled.title.tablet') }}"
            data-tablet-body="{{ __('app.kiosk.not_enrolled.body.tablet') }}"
            data-tablet-help="{{ __('app.kiosk.not_enrolled.help.tablet') }}"
        @endif
    >
        <svg class="mb-6 h-16 w-16 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="5" y="11" width="14" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
            <line x1="12" y1="15" x2="12" y2="17" />
        </svg>

        <h1 data-not-enrolled-title class="mb-3 text-3xl font-bold text-white">
            {{ __('app.kiosk.not_enrolled.title.'.$key) }}
        </h1>

        <p data-not-enrolled-body class="mb-2 text-xl text-slate-300">
            {{ __('app.kiosk.not_enrolled.body.'.$key) }}
        </p>

        <p data-not-enrolled-help class="max-w-md text-lg text-slate-400">
            {{ __('app.kiosk.not_enrolled.help.'.$key) }}
        </p>

        <div class="mt-8 w-full max-w-xs">
            <x-button href="{{ route('login') }}" variant="ghost" size="kiosk" class="w-full">
                {{ __('app.auth.login') }}
            </x-button>
        </div>
    </div>

    @if ($mayBeTablet)
        {{--
            Since iPadOS 13, Safari on iPad requests desktop sites by default
            and sends a Macintosh User-Agent — identical to a MacBook's. The
            server cannot tell them apart, so it renders the computer wording
            and lets the browser correct itself: only a touch device reports
            more than one touch point.

            Checked for Macintosh only (see DeviceType::mayBeATabletInDisguise),
            so a Windows touchscreen laptop is not relabelled a tablet.

            Progressive enhancement — with JavaScript off the page still reads
            correctly, just as "computer".
        --}}
        <script>
            (function () {
                if (!(navigator.maxTouchPoints > 1)) {
                    return;
                }

                var root = document.querySelector('[data-tablet-title]');

                if (!root) {
                    return;
                }

                var swap = function (selector, value) {
                    var el = root.querySelector(selector);

                    if (el && value) {
                        el.textContent = value;
                    }
                };

                swap('[data-not-enrolled-title]', root.dataset.tabletTitle);
                swap('[data-not-enrolled-body]', root.dataset.tabletBody);
                swap('[data-not-enrolled-help]', root.dataset.tabletHelp);
            })();
        </script>
    @endif
@endcomponent
