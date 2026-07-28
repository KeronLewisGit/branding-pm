{{--
    Button. Renders an <a> when an href attribute is present.
    variant: primary | ghost | danger
    size:    default (56px) | lg (64px) | kiosk (72px)
--}}
@props(['variant' => 'primary', 'size' => 'default', 'type' => 'button'])

@php
    $variantClass = match ($variant) {
        'ghost' => 'btn-ghost',
        'danger' => 'btn-danger',
        default => 'btn-primary',
    };

    $sizeClass = match ($size) {
        'lg' => 'min-h-16 px-8 text-xl',
        'kiosk' => 'min-h-[72px] px-8 text-xl',
        default => '',
    };

    $classes = trim("btn {$variantClass} {$sizeClass}");
@endphp

@if ($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
