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

{{--
    `title` is always set, not only when collapsed: it costs nothing when the
    label is visible, and when the sidebar is an icon rail it is the only
    thing naming the destination to a mouse user. The label stays in the DOM
    and is merely hidden, so a screen reader still reads the link normally.
--}}
<a
    href="{{ $href }}"
    title="{{ trim(strip_tags($slot)) }}"
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => 'flex min-h-14 items-center gap-3 rounded-xl px-4 text-lg font-medium transition-colors [.is-collapsed_&]:justify-center [.is-collapsed_&]:px-0 '
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

    <span class="min-w-0 truncate [.is-collapsed_&]:hidden">{{ $slot }}</span>
</a>
