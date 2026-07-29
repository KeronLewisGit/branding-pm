{{--
    A signed signature block, laid out like the one on the paper work order:
    the image, then the printed name, employee number and timestamp beneath it
    (SPEC §"PDF Export" expects exactly this shape).

        <x-signature-block
            :label="__('app.runs.operator_signature')"
            :user="$run->operator"
            :path="$run->operator_signature_path"
            :signed-at="$run->operator_signed_at" />

    Renders an explicit "not signed" state rather than nothing — a missing
    signature is information on an audit record.
--}}
@props([
    'label',
    'user' => null,
    'path' => null,
    'signedAt' => null,
    'note' => null,
])

@php
    $displayTz = (string) config('app.display_timezone', 'UTC');
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 p-4 [.kiosk_&]:border-slate-700']) }}>
    <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>

    @if ($path)
        {{-- White plate: the ink is dark on transparent, so it needs one in
             the dark kiosk layout as much as on a printed sheet. --}}
        <div class="mt-2 flex h-28 items-center justify-center rounded-lg border border-slate-200 bg-white p-2">
            <img src="{{ \App\Support\SignatureImage::url($path) }}"
                 alt="{{ __('app.runs.signature_of', ['name' => $user?->full_name ?? __('app.runs.unknown_user')]) }}"
                 class="max-h-full max-w-full object-contain">
        </div>
    @else
        <div class="mt-2 flex h-28 items-center justify-center rounded-lg border border-dashed border-slate-300 [.kiosk_&]:border-slate-700">
            <p class="text-base text-slate-500">{{ __('app.runs.not_signed') }}</p>
        </div>
    @endif

    <p class="mt-3 text-lg font-semibold [.kiosk_&]:text-slate-100">
        {{ $user?->full_name ?? __('app.runs.unknown_user') }}
    </p>
    <p class="text-base text-slate-500">
        @if ($user?->employee_number)
            #{{ $user->employee_number }}
        @endif
        @if ($signedAt)
            <span class="tabular-nums">· {{ $signedAt->timezone($displayTz)->format('D j M Y, g:i A') }}</span>
        @endif
    </p>

    @if ($note)
        <p class="mt-2 text-base text-slate-600 [.kiosk_&]:text-slate-300">{{ $note }}</p>
    @endif
</div>
