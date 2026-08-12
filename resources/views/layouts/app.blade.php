<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Branding PM') }}</title>

    {{-- Livewire 3 auto-injects its styles/scripts — @vite is all we need. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    {{--
        Two independent bits of state, because they are two different things:

        `sidebarOpen` — the mobile drawer. Always starts shut; a menu that
        remembered being open would cover the page on every load.

        `collapsed`  — the desktop icon rail. Remembered in localStorage,
        because a sidebar that expanded again on every navigation would be
        worse than not being collapsible at all. Read synchronously in
        x-data (not init) so the rail never flashes wide first.
    --}}
    <div
        x-data="{
            sidebarOpen: false,
            collapsed: window.localStorage.getItem('brandingPm.sidebarCollapsed') === '1',
            toggleCollapsed() {
                this.collapsed = ! this.collapsed;
                window.localStorage.setItem('brandingPm.sidebarCollapsed', this.collapsed ? '1' : '0');
            },
        }"
        class="flex min-h-screen flex-col md:flex-row"
    >

        @php
            /*
             * Where "Kiosk mode" goes, and whether it appears at all.
             *
             * Computed up here rather than in the nav because it is now a
             * chrome control, rendered beside the menu toggle in both bars.
             *
             * Enrolled browser → straight into the kiosk. Otherwise it depends
             * on what this person may do: somebody holding `kiosk.activate`
             * sets the device up themselves, and an operator — who holds no
             * such permission, because enrolling is a trust decision — asks
             * for one instead. Nobody is offered a control that only leads to
             * a 403.
             */
            $kioskEnrolled = \App\Http\Middleware\EnsureKioskDevice::enrolledDevice(request()) !== null;
            $mayActivateKiosk = auth()->user()?->can('kiosk.activate') === true;

            [$kioskTarget, $kioskLabel] = match (true) {
                $kioskEnrolled => [route('kiosk.home'), __('app.nav.kiosk_mode')],
                $mayActivateKiosk => [route('kiosk.activate'), __('app.nav.kiosk_mode_setup')],
                default => [route('kiosk.request'), __('app.nav.kiosk_mode_request')],
            };
        @endphp

        {{-- Mobile top bar — the sidebar collapses into this under md --}}
        <header data-nav-chrome class="flex items-center justify-between gap-3 bg-slate-900 px-4 py-2 text-white md:hidden">
            <a href="{{ route('dashboard') }}" class="rounded-lg px-2 py-1 text-lg font-bold">
                {{ config('app.name', 'Branding PM') }}
            </a>

            <div class="flex items-center gap-1">
                @include('partials.kiosk-mode-icon', ['sizeClasses' => 'h-14 w-14 rounded-xl'])

            <button
                type="button"
                class="flex h-14 w-14 items-center justify-center rounded-xl hover:bg-slate-800 active:bg-slate-700"
                x-on:click="sidebarOpen = ! sidebarOpen"
                x-bind:aria-expanded="sidebarOpen"
                aria-controls="sidebar-nav"
                aria-label="{{ __('app.nav.toggle_menu') }}"
            >
                <svg x-show="! sidebarOpen" class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
                <svg x-show="sidebarOpen" x-cloak class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
            </div>
        </header>

        {{--
            Sidebar. Static `hidden md:!flex` keeps it visible on desktop and
            hidden on mobile before Alpine boots; when the hamburger opens it,
            Alpine adds `!flex`, whose !important beats `hidden`.
        --}}
        <aside
            id="sidebar-nav"
            data-nav-chrome
            {{--
                `md:sticky md:top-0 md:h-screen md:self-start` is the sticky
                part. `self-start` matters: a flex child stretches to the row
                height by default, and an element exactly as tall as its
                container has no room to stick — it would simply scroll away.
            --}}
            class="hidden w-full flex-shrink-0 flex-col bg-slate-900 text-slate-100 md:!flex md:sticky md:top-0 md:h-screen md:self-start"
            x-bind:class="{
                '!flex': sidebarOpen,
                'is-collapsed md:w-20': collapsed,
                'md:w-72': ! collapsed,
            }"
        >
            {{--
                Collapsed, this row stacks.

                The rail is 80px wide and `px-3` leaves 56px of it. Two 44px
                controls plus the gap need 96px, and both are `shrink-0`, so
                laid out in a row the second one overflowed the aside entirely
                and floated over the page content beside it. A column gives
                each its own line, centred by the `items-center` already here.
            --}}
            <div class="hidden shrink-0 items-center gap-2 px-3 py-3 md:flex [.is-collapsed_&]:flex-col [.is-collapsed_&]:gap-0.5 [.is-collapsed_&]:pb-0">
                <a href="{{ route('dashboard') }}"
                   class="min-w-0 flex-1 truncate rounded-lg px-3 text-xl font-bold text-white [.is-collapsed_&]:hidden">
                    {{ config('app.name', 'Branding PM') }}
                </a>

                @include('partials.kiosk-mode-icon', ['sizeClasses' => 'h-11 w-11 rounded-lg'])

                {{-- Desktop collapse. The mobile drawer has its own hamburger. --}}
                <button
                    type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-800 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                    x-on:click="toggleCollapsed()"
                    x-bind:aria-expanded="(! collapsed).toString()"
                    aria-controls="sidebar-nav"
                    x-bind:aria-label="collapsed ? '{{ __('app.nav.expand_menu') }}' : '{{ __('app.nav.collapse_menu') }}'"
                    x-bind:title="collapsed ? '{{ __('app.nav.expand_menu') }}' : '{{ __('app.nav.collapse_menu') }}'"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
                         aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="9" y1="3" x2="9" y2="21"/>
                    </svg>
                </button>
            </div>

            {{-- The nav scrolls inside the sticky column, not the page. --}}
            <nav aria-label="{{ __('app.nav.main') }}" class="nav-scroll flex-1 space-y-0.5 overflow-y-auto px-3 py-3 [.is-collapsed_&]:pt-2">
                @php
                    // Icons are decorative — every entry is named in text beside it.
                    $ico = fn (string $d) => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';

                    /*
                     * Pending requests, for the people who can clear them. One
                     * count query per page for supervisors only — an operator
                     * never runs it.
                     */
                    $kioskRequestCount = $mayActivateKiosk
                        ? \App\Models\KioskEnrolmentRequest::query()->pending()->count()
                        : 0;

                    /*
                     * Where the review queue lives depends on who is looking,
                     * because the two permissions do not overlap the way the
                     * menu does.
                     *
                     * An administrator already has a System group holding the
                     * kiosk fleet, and the queue belongs beside it rather than
                     * as a ninth top-level row. A supervisor holds
                     * `kiosk.activate` but NOT `kiosk.manage`, so that group
                     * does not render for them at all — filing the queue in it
                     * unconditionally would hide it from exactly the people
                     * who clear it.
                     */
                    $kioskRequestsInSystem = auth()->user()?->canAny(['kiosk.manage', 'user.manage']) === true;
                @endphp

                {{-- Supervisors only. Administrators get this inside System. --}}
                @if ($mayActivateKiosk && ! $kioskRequestsInSystem)
                    @include('partials.kiosk-requests-nav-link')
                @endif

                {{--
                    Group 1 — the work. No heading on purpose: for an operator
                    this is the entire menu, and a lone header over two links
                    is noise.
                --}}
                @can('report.view')
                    {{--
                        Gated on `report.view`, NOT `run.view`. The Dashboard
                        component redirects anyone without it to /runs, so an
                        operator was being shown a link that bounced them
                        straight back to where they already were.
                    --}}
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <x-slot:icon>{!! $ico('<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>') !!}</x-slot:icon>
                        {{ __('app.nav.dashboard') }}
                    </x-nav-link>
                @endcan

                {{--
                    Group 1 — the day's work. Dashboard stays above it as the
                    landing page; everything you *do* lives in here.
                --}}
                @canany(['run.view', 'run.approve', 'run.verify', 'issue.view', 'report.view'])
                    <x-nav-group
                        group="work"
                        :label="__('app.nav.group_work')"
                        :active="request()->routeIs('runs.*') || request()->routeIs('issues.*') || request()->routeIs('reports.*')"
                    >
                        <x-slot:icon>{!! $ico('<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>') !!}</x-slot:icon>

                @can('run.view')
                    <x-nav-link :href="route('runs.index')" :active="request()->routeIs('runs.index') || request()->routeIs('runs.show')">
                        <x-slot:icon>{!! $ico('<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>') !!}</x-slot:icon>
                        {{ __('app.nav.runs') }}
                    </x-nav-link>
                @endcan

                @can('run.approve')
                    <x-nav-link :href="route('runs.approvals')" :active="request()->routeIs('runs.approvals') || request()->routeIs('runs.review')">
                        <x-slot:icon>{!! $ico('<path d="M20 6L9 17l-5-5"/>') !!}</x-slot:icon>
                        {{ __('app.nav.approvals') }}
                    </x-nav-link>
                @endcan

                @can('run.verify')
                    <x-nav-link :href="route('runs.verifications')" :active="request()->routeIs('runs.verifications')">
                        <x-slot:icon>{!! $ico('<path d="M9 12l2 2 4-4"/><path d="M12 3l7 4v5c0 4.4-3 8.5-7 9.5C8 20.5 5 16.4 5 12V7l7-4z"/>') !!}</x-slot:icon>
                        {{ __('app.nav.verifications') }}
                    </x-nav-link>
                @endcan

                @can('issue.view')
                    <x-nav-link :href="route('issues.index')" :active="request()->routeIs('issues.*')">
                        <x-slot:icon>{!! $ico('<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>') !!}</x-slot:icon>
                        {{ __('app.nav.issues') }}
                    </x-nav-link>
                @endcan

                @can('report.view')
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        <x-slot:icon>{!! $ico('<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>') !!}</x-slot:icon>
                        {{ __('app.nav.reports') }}
                    </x-nav-link>
                @endcan

                    </x-nav-group>
                @endcanany

                {{--
                    Group 2 — the plant. Folded by default: this is what a
                    maintenance manager sets up once and then rarely touches,
                    so it does not deserve five permanent rows.
                --}}
                @canany(['machine.manage', 'template.manage', 'holiday.manage'])
                    <x-nav-group
                        group="plant"
                        :label="__('app.nav.group_plant')"
                        :active="request()->routeIs('admin.machines*') || request()->routeIs('machines.show') || request()->routeIs('admin.locations*') || request()->routeIs('admin.templates*') || request()->routeIs('admin.holidays*')"
                    >
                        <x-slot:icon>{!! $ico('<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21v-6h4v6"/>') !!}</x-slot:icon>

                @can('machine.manage')
                    {{-- Also active on the machine profile and the sticker sheet:
                         both are reached from here and neither is a section. --}}
                    <x-nav-link :href="route('admin.machines')" :active="request()->routeIs('admin.machines') || request()->routeIs('admin.machines.qr') || request()->routeIs('machines.show')">
                        <x-slot:icon>{!! $ico('<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M7 8V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/>') !!}</x-slot:icon>
                        {{ __('app.nav.machines') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.locations')" :active="request()->routeIs('admin.locations*')">
                        <x-slot:icon>{!! $ico('<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>') !!}</x-slot:icon>
                        {{ __('app.nav.locations') }}
                    </x-nav-link>
                @endcan

                @can('template.manage')
                    <x-nav-link :href="route('admin.templates')" :active="request()->routeIs('admin.templates*')">
                        <x-slot:icon>{!! $ico('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>') !!}</x-slot:icon>
                        {{ __('app.nav.templates') }}
                    </x-nav-link>
                @endcan


                @can('holiday.manage')
                    <x-nav-link :href="route('admin.holidays')" :active="request()->routeIs('admin.holidays*')">
                        <x-slot:icon>{!! $ico('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>') !!}</x-slot:icon>
                        {{ __('app.nav.holidays') }}
                    </x-nav-link>
                @endcan

                    </x-nav-group>
                @endcanany

                {{-- Group 3 — the system itself. Admin territory, and rarer still. --}}
                @canany(['kiosk.manage', 'user.manage', 'setting.manage'])
                    <x-nav-group
                        group="system"
                        :label="__('app.nav.group_system')"
                        {{-- Includes kiosk.requests, or an administrator sitting
                             on that page finds the group folded shut over it. --}}
                        :active="request()->routeIs('admin.kiosk') || request()->routeIs('admin.users') || request()->routeIs('kiosk.requests') || request()->routeIs('admin.mail')"
                    >
                        <x-slot:icon>{!! $ico('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>') !!}</x-slot:icon>

                {{-- Beside the fleet it belongs with. --}}
                @if ($mayActivateKiosk && $kioskRequestsInSystem)
                    @include('partials.kiosk-requests-nav-link')
                @endif

                @can('kiosk.manage')
                    <x-nav-link :href="route('admin.kiosk')" :active="request()->routeIs('admin.kiosk')">
                        <x-slot:icon>{!! $ico('<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="10" y1="18" x2="14" y2="18"/>') !!}</x-slot:icon>
                        {{ __('app.nav.kiosk_devices') }}
                    </x-nav-link>
                @endcan

                    @can('setting.manage')
                    <x-nav-link :href="route('admin.mail')" :active="request()->routeIs('admin.mail')">
                        <x-slot:icon>{!! $ico('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>') !!}</x-slot:icon>
                        {{ __('app.mail.title') }}
                    </x-nav-link>
                @endcan

                @can('user.manage')
                    <x-nav-link :href="route('admin.users')" :active="request()->routeIs('admin.users')">
                        <x-slot:icon>{!! $ico('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>') !!}</x-slot:icon>
                        {{ __('app.nav.users') }}
                    </x-nav-link>
                    @endcan
                    </x-nav-group>
                @endcanany
            </nav>

            {{--
                View-as picker. Gated on the REAL admin role, not `@can`,
                because while previewing an operator the admin permissions are
                gone — and the control to get back out must not vanish with
                them. Hidden on the icon rail, where a select has nowhere to go.
            --}}
            {{--
                Hidden while a preview is running: with the banner's button as
                the only exit there is one obvious way back, and no second
                control quietly contradicting the banner. Switching roles is
                stop-then-pick, which is a click more and a good deal clearer.
            --}}
            @if (auth()->user()?->hasRole('admin') && ! \App\Support\ViewAs::active())
                <div class="shrink-0 border-t border-slate-800 px-4 py-3 [.is-collapsed_&]:hidden">
                    <label for="view-as-role" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {{ __('app.view_as.label') }}
                    </label>

                    <form method="POST" action="{{ route('view-as.start') }}" class="mt-2 flex gap-2">
                        @csrf
                        <select id="view-as-role" name="role"
                                class="min-h-11 min-w-0 flex-1 rounded-lg border-slate-600 bg-slate-800 text-sm text-slate-100 focus:border-sky-500 focus:ring-sky-500">
                            <option value="">{{ __('app.view_as.choose') }}</option>
                            @foreach (\App\Support\ViewAs::selectableRoles() as $previewRole)
                                <option value="{{ $previewRole }}" @selected(\App\Support\ViewAs::role() === $previewRole)>
                                    {{ __('app.roles.'.$previewRole) }}
                                </option>
                            @endforeach
                        </select>

                        <x-button
                            type="submit"
                            variant="ghost"
                            class="!min-h-11 shrink-0 border-slate-600 !px-3 !text-sm text-slate-100 hover:bg-slate-800"
                        >
                            {{ __('app.view_as.apply') }}
                        </x-button>
                    </form>
                </div>
            @endif

            {{-- Pinned to the bottom of the sticky column, above the scroll. --}}
            <div class="shrink-0 border-t border-slate-800 px-4 py-3 [.is-collapsed_&]:px-2">
                <p class="truncate text-sm text-white [.is-collapsed_&]:hidden">
                    <span class="font-semibold">{{ auth()->user()?->full_name }}</span>
                    <span class="text-slate-400">#{{ auth()->user()?->employee_number }}</span>
                </p>

                {{-- Collapsed, the initials stand in for the name. --}}
                <p class="hidden text-center text-base font-bold text-white [.is-collapsed_&]:block"
                   title="{{ auth()->user()?->full_name }}">
                    {{ Str::of(auth()->user()?->full_name ?? '')->explode(' ')->filter()->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode('') }}
                </p>

                {{-- For somebody who skipped it on day one and wants it back. --}}
                <form method="POST" action="{{ route('walkthrough.replay') }}" class="mt-2 [.is-collapsed_&]:hidden">
                    @csrf
                    <button type="submit" class="text-xs text-slate-400 underline-offset-2 hover:text-slate-200 hover:underline">
                        {{ __('app.walkthrough.replay') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="mt-2">
                    @csrf
                    <x-button
                        variant="ghost"
                        type="submit"
                        :title="__('app.nav.logout')"
                        class="!min-h-11 w-full border-slate-600 !text-base text-slate-100 hover:bg-slate-800 active:bg-slate-700 [.is-collapsed_&]:px-0"
                    >
                        <span class="[.is-collapsed_&]:hidden">{{ __('app.nav.logout') }}</span>
                        <svg class="hidden h-5 w-5 [.is-collapsed_&]:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                             stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </x-button>
                </form>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            @isset($header)
                <header class="bg-white shadow-sm">
                    <div class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @include('partials.view-as-banner')

            <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @if (session('status'))
                    <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
                @endif
                @if (session('error'))
                    <x-alert type="error" class="mb-6">{{ session('error') }}</x-alert>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @include('partials.walkthrough')

    @stack('scripts')
</body>
</html>
