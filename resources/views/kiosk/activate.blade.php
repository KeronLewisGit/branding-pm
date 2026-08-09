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

        <form method="POST" action="{{ route('kiosk.activate.store') }}" class="space-y-6" x-data>
            @csrf
            <input type="hidden" name="machine" value="{{ $machine?->code }}">

            {{--
                Measurements the server cannot take for itself. Everything
                here is client-supplied and forgeable, and nothing is ever
                authorised on the strength of it — it is so a human reading
                the fleet list can tell one black tablet from another.
                See App\Support\DeviceReport.
            --}}
            <input type="hidden" name="device[screen]" x-init="$el.value = `${screen.width} x ${screen.height}`">
            <input type="hidden" name="device[viewport]" x-init="$el.value = `${innerWidth} x ${innerHeight}`">
            <input type="hidden" name="device[pixel_ratio]" x-init="$el.value = String(devicePixelRatio || 1)">
            <input type="hidden" name="device[touch_points]" x-init="$el.value = String(navigator.maxTouchPoints || 0)">
            <input type="hidden" name="device[timezone]" x-init="$el.value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''">
            <input type="hidden" name="device[language]" x-init="$el.value = navigator.language || ''">
            <input type="hidden" name="device[platform]" x-init="$el.value = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || ''">

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
                    {{ $detectedType->label() }}<span x-text="` · ${screen.width} × ${screen.height}`"></span>
                </p>
                <p class="mt-2 text-sm text-slate-500">{{ __('app.kiosk.activate.detected_hint') }}</p>
            </div>

            <x-button type="submit" size="kiosk" class="w-full">
                {{ __('app.kiosk.activate.submit') }}
            </x-button>
        </form>
    </div>
@endcomponent
