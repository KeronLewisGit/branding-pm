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
    <div x-data="{ sidebarOpen: false }" class="flex min-h-screen flex-col md:flex-row">

        {{-- Mobile top bar — the sidebar collapses into this under md --}}
        <header data-nav-chrome class="flex items-center justify-between gap-3 bg-slate-900 px-4 py-2 text-white md:hidden">
            <a href="{{ route('dashboard') }}" class="rounded-lg px-2 py-1 text-lg font-bold">
                {{ config('app.name', 'Branding PM') }}
            </a>

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
        </header>

        {{--
            Sidebar. Static `hidden md:!flex` keeps it visible on desktop and
            hidden on mobile before Alpine boots; when the hamburger opens it,
            Alpine adds `!flex`, whose !important beats `hidden`.
        --}}
        <aside
            id="sidebar-nav"
            data-nav-chrome
            class="hidden w-full flex-shrink-0 flex-col bg-slate-900 text-slate-100 md:!flex md:min-h-screen md:w-72"
            x-bind:class="sidebarOpen && '!flex'"
        >
            <div class="hidden items-center px-6 py-5 md:flex">
                <a href="{{ route('dashboard') }}" class="rounded-lg text-xl font-bold text-white">
                    {{ config('app.name', 'Branding PM') }}
                </a>
            </div>

            <nav aria-label="{{ __('app.nav.main') }}" class="flex-1 space-y-1 px-3 py-4">
                @php
                    // Icons are decorative — every entry is named in text beside it.
                    $ico = fn (string $d) => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">'.$d.'</svg>';
                @endphp

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

                {{--
                    Group 2 — the plant. What a maintenance manager sets up
                    once and then rarely touches.
                --}}
                @canany(['machine.manage', 'part.manage', 'template.manage', 'holiday.manage'])
                    <p class="px-4 pb-1 pt-6 text-sm font-semibold uppercase tracking-wider text-slate-400">
                        {{ __('app.nav.group_plant') }}
                    </p>
                @endcanany

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

                @can('part.manage')
                    <x-nav-link :href="route('admin.parts')" :active="request()->routeIs('admin.parts*')">
                        <x-slot:icon>{!! $ico('<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>') !!}</x-slot:icon>
                        {{ __('app.nav.parts') }}
                    </x-nav-link>
                @endcan

                @can('holiday.manage')
                    <x-nav-link :href="route('admin.holidays')" :active="request()->routeIs('admin.holidays*')">
                        <x-slot:icon>{!! $ico('<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>') !!}</x-slot:icon>
                        {{ __('app.nav.holidays') }}
                    </x-nav-link>
                @endcan

                {{-- Group 3 — the system itself. Admin territory. --}}
                @canany(['kiosk.manage', 'user.manage'])
                    <p class="px-4 pb-1 pt-6 text-sm font-semibold uppercase tracking-wider text-slate-400">
                        {{ __('app.nav.group_system') }}
                    </p>
                @endcanany

                @can('kiosk.manage')
                    <x-nav-link :href="route('admin.kiosk')" :active="request()->routeIs('admin.kiosk')">
                        <x-slot:icon>{!! $ico('<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="10" y1="18" x2="14" y2="18"/>') !!}</x-slot:icon>
                        {{ __('app.nav.kiosk_devices') }}
                    </x-nav-link>
                @endcan

                @can('user.manage')
                    <x-nav-link :href="route('admin.users')" :active="request()->routeIs('admin.users')">
                        <x-slot:icon>{!! $ico('<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>') !!}</x-slot:icon>
                        {{ __('app.nav.users') }}
                    </x-nav-link>
                @endcan
            </nav>

            <div class="border-t border-slate-800 px-4 py-4">
                <p class="truncate text-base font-semibold text-white">{{ auth()->user()?->full_name }}</p>
                <p class="text-sm text-slate-400">#{{ auth()->user()?->employee_number }}</p>

                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <x-button variant="ghost" type="submit" class="w-full border-slate-600 text-slate-100 hover:bg-slate-800 active:bg-slate-700">
                        {{ __('app.nav.logout') }}
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

    @stack('scripts')
</body>
</html>
