{{--
    "Make this device a kiosk" — reached by scanning a machine's QR sticker on
    a device that is not enrolled, then signing in. See
    KioskActivationController for the whole journey.

    Rendered in the kiosk layout because that is what the person is looking
    at: they are standing at a machine holding a tablet, not sitting at a desk.
--}}
@component('layouts.kiosk')
    <div class="mx-auto w-full max-w-xl py-8">
        <h1 class="mb-2 text-3xl font-bold text-white">{{ __('app.kiosk.activate.title') }}</h1>

        @if ($machine)
            <p class="mb-6 text-xl text-slate-300">
                {{ __('app.kiosk.activate.intro_machine', ['machine' => $machine->name]) }}
            </p>
        @else
            {{-- Scanned nothing recognisable, or reached this screen directly. --}}
            <p class="mb-6 text-xl text-slate-300">{{ __('app.kiosk.activate.intro') }}</p>
        @endif

        @if (session('error'))
            <x-alert type="error" class="mb-6">{{ session('error') }}</x-alert>
        @endif

        <form method="POST" action="{{ route('kiosk.activate.store') }}" class="space-y-6" data-activate-form>
            @csrf
            <input type="hidden" name="machine" value="{{ $machine?->code }}">

            {{--
                Measurements the server cannot take for itself. Everything
                here is client-supplied and forgeable, and nothing is ever
                authorised on the strength of it — it is so a human reading
                the fleet list can tell one black tablet from another.
                See App\Support\DeviceReport.

                Plain JavaScript, NOT Alpine. This is a controller-rendered
                Blade page with no Livewire component on it, and Alpine only
                arrives bundled inside Livewire's script — which is never
                injected here. `x-init` on these fields looked right, rendered
                fine, and posted nothing at all.
            --}}
            <input type="hidden" name="device[screen]" data-device="screen">
            <input type="hidden" name="device[viewport]" data-device="viewport">
            <input type="hidden" name="device[pixel_ratio]" data-device="pixel_ratio">
            <input type="hidden" name="device[touch_points]" data-device="touch_points">
            <input type="hidden" name="device[timezone]" data-device="timezone">
            <input type="hidden" name="device[language]" data-device="language">
            <input type="hidden" name="device[platform]" data-device="platform">

            <div class="field">
                <x-label for="name">{{ __('app.kiosk_devices.name') }}</x-label>
                <x-input
                    id="name"
                    name="name"
                    value="{{ old('name', $suggestedName) }}"
                    maxlength="120"
                    required
                    autofocus
                />
                <p class="text-base text-slate-400">{{ __('app.kiosk.activate.name_hint') }}</p>
                @error('name')
                    <p class="text-base font-medium text-rose-400" role="alert">{{ $message }}</p>
                @enderror
            </div>

            @if ($existingDevices->isNotEmpty())
                {{--
                    Replacing a broken tablet: taking over the old device's
                    identity keeps its name, location and history instead of
                    leaving a dead row in the fleet list beside a new one.
                --}}
                <div class="field">
                    <x-label for="device_id">{{ __('app.kiosk.activate.existing') }}</x-label>
                    <x-select id="device_id" name="device_id">
                        <option value="">{{ __('app.kiosk.activate.existing_none') }}</option>
                        @foreach ($existingDevices as $existing)
                            <option value="{{ $existing->id }}" @selected(old('device_id') == $existing->id)>
                                {{ $existing->name }} ({{ $existing->kind->label() }})
                            </option>
                        @endforeach
                    </x-select>
                    <p class="text-base text-slate-400">{{ __('app.kiosk.activate.existing_hint') }}</p>
                </div>
            @endif

            <div class="rounded-xl border border-slate-700 bg-slate-900 p-4">
                <p class="mb-2 text-base font-semibold text-slate-200">
                    {{ __('app.kiosk.activate.detected') }}
                </p>
                <p class="text-base text-slate-400">
                    {{ $detectedType->label() }}<span data-device-readout></span>
                </p>
                <p class="mt-2 text-sm text-slate-500">{{ __('app.kiosk.activate.detected_hint') }}</p>
            </div>

            <x-button type="submit" size="kiosk" class="w-full">
                {{ __('app.kiosk.activate.submit') }}
            </x-button>
        </form>
    </div>

    {{--
        Fills the hidden fields above, and shows the person what is about to
        be recorded rather than collecting it behind their back.

        Wrapped in try/catch per field: an older tablet missing one of these
        APIs must still be able to enrol. A device that cannot report its
        pixel ratio is not a device we refuse to set up.
    --}}
    <script>
        (function () {
            var form = document.querySelector('[data-activate-form]');

            if (!form) {
                return;
            }

            var readings = {
                screen: function () { return screen.width + ' x ' + screen.height; },
                viewport: function () { return window.innerWidth + ' x ' + window.innerHeight; },
                pixel_ratio: function () { return String(window.devicePixelRatio || 1); },
                touch_points: function () { return String(navigator.maxTouchPoints || 0); },
                timezone: function () { return Intl.DateTimeFormat().resolvedOptions().timeZone || ''; },
                language: function () { return navigator.language || ''; },
                platform: function () {
                    return (navigator.userAgentData && navigator.userAgentData.platform)
                        || navigator.platform || '';
                }
            };

            Object.keys(readings).forEach(function (key) {
                var field = form.querySelector('[data-device="' + key + '"]');

                if (!field) {
                    return;
                }

                try {
                    field.value = readings[key]();
                } catch (e) {
                    field.value = '';
                }
            });

            var readout = document.querySelector('[data-device-readout]');

            if (readout) {
                try {
                    readout.textContent = ' · ' + screen.width + ' × ' + screen.height
                        + (navigator.maxTouchPoints > 0 ? ' · {{ __('app.kiosk_devices.touchscreen') }}' : '');
                } catch (e) {
                    // Leave the server-detected label standing on its own.
                }
            }
        })();
    </script>
@endcomponent
