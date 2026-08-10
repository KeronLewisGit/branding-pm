{{--
    A collapsible group of nav links.

        <x-nav-group group="plant" :label="__('app.nav.group_plant')"
                     :active="request()->routeIs('admin.machines')">
            <x-slot:icon>…</x-slot:icon>
            <x-nav-link …>…</x-nav-link>
        </x-nav-group>

    Why this exists: an administrator has thirteen destinations, and thirteen
    rows do not fit above the fold on a 1366×768 laptop however tightly they
    are packed. Folding the two setup groups turns thirteen rows into eight
    and leaves every destination one click away.

    Three behaviours worth knowing:

    - The group holding the current page opens itself, so you can always see
      where you are. That beats a remembered state, so it wins over storage.
    - Otherwise the open/shut state is remembered per group, read
      synchronously so nothing flashes open then shut on load.
    - When the sidebar is collapsed to an icon rail the children are FORCED
      open (`[.is-collapsed_&]:!block`) and the toggle is hidden. A rail whose
      destinations were folded away behind a heading nobody can read would
      hide half the application.
--}}
@props([
    'group',
    'label',
    'active' => false,
])

<div
    x-data="{
        active: {{ $active ? 'true' : 'false' }},
        {{-- Shut unless remembered open, or unless the current page is inside
             it. Defaulting open would leave thirteen rows and no gain. --}}
        open: {{ $active ? 'true' : "window.localStorage.getItem('brandingPm.navGroup.{$group}') === '1'" }},
        toggle() {
            this.open = ! this.open;
            window.localStorage.setItem('brandingPm.navGroup.{{ $group }}', this.open ? '1' : '0');

            // One at a time. With every group open an administrator is back
            // to thirteen rows and a scrollbar, which is the thing this
            // component exists to remove.
            if (this.open) {
                window.dispatchEvent(new CustomEvent('nav-group-opened', { detail: '{{ $group }}' }));
            }
        },
        closeIfOther(opened) {
            // Never fold away the group holding the page you are on.
            if (opened !== '{{ $group }}' && ! this.active) {
                this.open = false;
                window.localStorage.setItem('brandingPm.navGroup.{{ $group }}', '0');
            }
        },
    }"
    x-on:nav-group-opened.window="closeIfOther($event.detail)"
    class="pt-2 [.is-collapsed_&]:pt-0"
>
    {{-- The toggle. Hidden on the icon rail, where the divider below stands in. --}}
    <button
        type="button"
        class="flex min-h-11 w-full items-center gap-3 rounded-xl px-4 text-xs font-semibold uppercase tracking-wider text-slate-400 transition-colors hover:bg-slate-800 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 [.is-collapsed_&]:hidden"
        x-on:click="toggle()"
        x-bind:aria-expanded="open.toString()"
        aria-controls="nav-group-{{ $group }}"
    >
        @isset($icon)
            <span class="flex w-6 shrink-0 justify-center" aria-hidden="true">{{ $icon }}</span>
        @endisset

        <span class="min-w-0 flex-1 truncate text-left">{{ $label }}</span>

        <svg class="h-4 w-4 shrink-0 transition-transform" x-bind:class="open && 'rotate-90'"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
    </button>

    {{-- Stands in for the heading when the sidebar is an icon rail. --}}
    <div class="mx-2 my-2 hidden h-px bg-slate-700 [.is-collapsed_&]:block" aria-hidden="true"></div>

    {{--
        A class toggle rather than x-show: x-show writes inline
        `display:none`, which no class could override, and the icon rail has
        to force these open.
    --}}
    <div
        id="nav-group-{{ $group }}"
        class="space-y-0.5 [.is-collapsed_&]:!block [.is-collapsed_&]:!mt-0"
        x-bind:class="open ? 'mt-0.5' : 'hidden'"
    >
        {{ $slot }}
    </div>
</div>
