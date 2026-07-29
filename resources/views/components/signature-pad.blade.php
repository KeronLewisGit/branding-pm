{{--
    Signature capture pad (milestone 5). Exposes its PNG data URL to the
    surrounding Alpine scope through x-modelable:

        <div x-data="{ signature: '' }">
            <x-signature-pad x-model="signature" :label="__('app.runs.sign_here')" />
            <button x-bind:disabled="! signature" x-on:click="$wire.submit(signature)">…</button>
        </div>

    The value never becomes a Livewire property — see the `signaturePad`
    comment in resources/js/app.js. `wire:ignore` keeps Livewire's DOM diffing
    away from the canvas: a re-render between the first stroke and the last
    would otherwise blank it.

    The pad is white in both layouts on purpose (the kiosk is dark elsewhere):
    the ink is stored dark on transparent and printed onto a white sheet.
--}}
@props([
    'label' => null,
    'hint' => null,
    'height' => 'h-44',
])

<div wire:ignore x-data="signaturePad" x-modelable="dataUrl" {{ $attributes }}>
    @if ($label)
        <p class="text-lg font-semibold [.kiosk_&]:text-slate-100">{{ $label }}</p>
    @endif

    @if ($hint)
        <p class="mt-1 text-base text-slate-500 [.kiosk_&]:text-slate-400">{{ $hint }}</p>
    @endif

    <div class="relative mt-2 overflow-hidden rounded-xl border-2 border-dashed border-slate-400 bg-white {{ $height }}">
        {{-- The signing line, so the pad reads as a signature block, not a drawing area. --}}
        <div class="pointer-events-none absolute inset-x-6 bottom-8 border-b border-slate-300" aria-hidden="true"></div>

        <p x-show="! hasInk" x-cloak
           class="pointer-events-none absolute inset-0 flex items-center justify-center text-lg text-slate-400">
            {{ __('app.runs.sign_here') }}
        </p>

        {{-- touch-action:none — otherwise the tablet pans the page instead of drawing. --}}
        <canvas
            x-ref="canvas"
            class="h-full w-full touch-none"
            role="img"
            aria-label="{{ $label ?? __('app.runs.signature') }}"
            x-on:pointerdown.prevent="start($event)"
            x-on:pointermove.prevent="move($event)"
            x-on:pointerup="end($event)"
            x-on:pointercancel="end($event)"
            x-on:pointerleave="end($event)"
        ></canvas>
    </div>

    <div class="mt-2 flex items-center justify-between gap-4">
        <p class="text-base" aria-live="polite">
            <span x-show="hasInk" x-cloak class="font-semibold text-emerald-600 [.kiosk_&]:text-emerald-400">
                {{ __('app.runs.signature_captured') }}
            </span>
        </p>

        <button type="button"
            class="flex min-h-14 items-center justify-center rounded-xl border-2 border-slate-400 px-5 text-lg font-semibold text-slate-600 active:bg-slate-100 [.kiosk_&]:border-slate-600 [.kiosk_&]:text-slate-300 [.kiosk_&]:active:bg-slate-800"
            x-on:click="clear()">
            {{ __('app.runs.signature_clear') }}
        </button>
    </div>
</div>
