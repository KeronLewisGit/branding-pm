{{-- Sidebar nav link (dark chrome). 56px tap target. --}}
@props(['href' => '#', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => 'flex min-h-14 items-center gap-3 rounded-xl px-4 text-lg font-medium transition-colors '
        . ($active
            ? 'bg-slate-800 text-white'
            : 'text-slate-300 hover:bg-slate-800 hover:text-white active:bg-slate-700')]) }}
>
    {{ $slot }}
</a>
