{{-- Card with optional header/footer slots. padding=false for flush lists (e.g. tap-rows). --}}
@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @isset($header)
        <div class="border-b border-slate-200 px-6 py-4 [.kiosk_&]:border-slate-700">
            {{ $header }}
        </div>
    @endisset

    <div @class(['p-6' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-slate-200 px-6 py-4 [.kiosk_&]:border-slate-700">
            {{ $footer }}
        </div>
    @endisset
</div>
