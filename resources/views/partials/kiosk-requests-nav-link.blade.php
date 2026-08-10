{{--
    The "Kiosk requests" row, rendered in one of two places.

    An administrator gets it inside the System group, beside the kiosk fleet
    it belongs with. A supervisor holds `kiosk.activate` without
    `kiosk.manage`, so that group never renders for them and the row sits at
    the top level instead. Same row either way, so it lives here rather than
    being written twice and drifting.

    Expects `$ico` and `$kioskRequestCount` from the layout.
--}}
<x-nav-link :href="route('kiosk.requests')" :active="request()->routeIs('kiosk.requests')">
    <x-slot:icon>{!! $ico('<path d="M9 12l2 2 4-4"/><rect x="3" y="4" width="18" height="16" rx="2"/>') !!}</x-slot:icon>
    {{ __('app.nav.kiosk_requests') }}
    @if ($kioskRequestCount > 0)
        {{-- The count is the reason to look. Without it the queue is a screen
             nobody opens. --}}
        <span class="ml-auto rounded-full bg-sky-600 px-2 py-0.5 text-xs font-bold text-white [.is-collapsed_&]:hidden">
            {{ $kioskRequestCount }}
        </span>
    @endif
</x-nav-link>
