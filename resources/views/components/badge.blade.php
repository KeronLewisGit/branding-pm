@props(['color' => 'slate'])

@php
    $classes = match ($color) {
        'amber' => 'bg-amber-100 text-amber-900',
        'sky' => 'bg-sky-100 text-sky-900',
        'emerald' => 'bg-emerald-100 text-emerald-900',
        'rose' => 'bg-rose-100 text-rose-900',
        'red' => 'bg-red-100 text-red-900',
        default => 'bg-slate-200 text-slate-800',
    };
@endphp

<span {{ $attributes->merge(['class' => "badge {$classes}"]) }}>{{ $slot }}</span>
