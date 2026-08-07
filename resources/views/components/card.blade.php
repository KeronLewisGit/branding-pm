{{--
    Card with optional header/footer slots. padding=false for flush lists
    (e.g. tap-rows).

    Body padding matches `.card-body` (p-5) rather than the p-6 it used to
    use: two card systems with different insets sat side by side on the same
    screens, and the difference read as a mistake because it was one. Header
    and footer follow at px-5 so the three line up vertically.
--}}
@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @isset($header)
        <div class="border-b border-slate-200 px-5 py-4 [.kiosk_&]:border-slate-700">
            {{ $header }}
        </div>
    @endisset

    <div @class(['card-body' => $padding])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-slate-200 px-5 py-4 [.kiosk_&]:border-slate-700">
            {{ $footer }}
        </div>
    @endisset
</div>
