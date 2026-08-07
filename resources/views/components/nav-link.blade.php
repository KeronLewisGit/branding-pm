{{--
    Sidebar nav link (dark chrome). 56px tap target.

    Optional `icon` slot, rendered at a fixed width so labels line up into a
    column whether or not every entry has one:

        <x-nav-link :href="..." :active="...">
            <x-slot:icon><svg …></svg></x-slot:icon>
            Dashboard
        </x-nav-link>
--}}
@props(['href' => '#', 'active' => false])

<a
    href="{{ $href }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => 'flex min-h-14 items-center gap-3 rounded-xl px-4 text-lg font-medium transition-colors '
        . ($active
            ? 'bg-slate-800 text-white'
            : 'text-slate-300 hover:bg-slate-800 hover:text-white active:bg-slate-700')]) }}
>
    @isset($icon)
        {{-- aria-hidden: the label beside it already names the destination. --}}
        <span class="flex w-6 shrink-0 justify-center text-slate-400 [a[aria-current]_&]:text-white" aria-hidden="true">
            {{ $icon }}
        </span>
    @endisset

    <span class="min-w-0 truncate">{{ $slot }}</span>
</a>
