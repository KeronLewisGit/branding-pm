{{--
    "Kiosk mode", as a chrome icon beside the menu toggle.

    The glyph is an arrow going through a doorway, not a tablet: the tablet
    already means "Kiosk Devices" in the System group, and a control that
    switches the whole surface should not wear the same badge as a page that
    lists hardware.

    It sits in the chrome rather than the menu because it switches the whole
    surface rather than navigating within it — the same reason the collapse
    control is not a menu row either. It also survives the collapsed icon
    rail, where a nav row would be reduced to an unlabelled glyph anyway.

    Icon-only, so the accessible name is not optional: `aria-label` names it
    for a screen reader and `title` gives a sighted user the same words on
    hover. The glyph itself is aria-hidden so nothing is announced twice —
    the same rule <x-icon-button> enforces for list actions.

    Expects `$kioskTarget` and `$kioskLabel` from the layout, and
    `$sizeClasses` from the caller, since the two bars size their controls
    differently (56px on the mobile bar, 44px on the desktop rail).
--}}
<a
    href="{{ $kioskTarget }}"
    class="{{ $sizeClasses }} flex shrink-0 items-center justify-center text-slate-400 transition-colors hover:bg-slate-800 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 {{ request()->routeIs('kiosk.request') ? 'bg-slate-800 text-white' : '' }}"
    aria-label="{{ $kioskLabel }}"
    title="{{ $kioskLabel }}"
>
    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true">
        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
        <polyline points="10 17 15 12 10 7"/>
        <line x1="15" y1="12" x2="3" y2="12"/>
    </svg>
</a>
