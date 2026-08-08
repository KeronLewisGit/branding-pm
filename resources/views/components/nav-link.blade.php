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
    {{--
        44px rows, not the 56px used elsewhere. This component is only ever
        rendered in the office sidebar (the kiosk has its own chrome and does
        not use it), so the target is a mouse pointer rather than a gloved
        thumb — and at 56px an administrator's thirteen entries did not fit on
        a 1080p screen, which put a scrollbar in the navigation.

        44px is the WCAG 2.2 minimum target size, so this is the floor, not a
        convenience.
    --}}
    {{ $attributes->merge(['class' => 'flex min-h-11 items-center gap-3 rounded-xl px-4 text-base font-medium transition-colors [.is-collapsed_&]:justify-center [.is-collapsed_&]:px-0 '
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
