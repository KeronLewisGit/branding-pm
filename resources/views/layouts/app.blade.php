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
                @can('run.view')
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('app.nav.dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('runs.index')" :active="request()->routeIs('runs.index') || request()->routeIs('runs.show')">
                        {{ __('app.nav.runs') }}
                    </x-nav-link>
                @endcan

                @can('run.approve')
                    <x-nav-link :href="route('runs.approvals')" :active="request()->routeIs('runs.approvals') || request()->routeIs('runs.review')">
                        {{ __('app.nav.approvals') }}
                    </x-nav-link>
                @endcan

                @can('issue.view')
                    <x-nav-link :href="route('issues.index')" :active="request()->routeIs('issues.*')">
                        {{ __('app.nav.issues') }}
                    </x-nav-link>
                @endcan

                @can('report.view')
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        {{ __('app.nav.reports') }}
                    </x-nav-link>
                @endcan

                @canany(['machine.manage', 'part.manage', 'template.manage', 'holiday.manage'])
                    <p class="px-4 pb-1 pt-5 text-sm font-semibold uppercase tracking-wider text-slate-400">
                        {{ __('app.nav.admin') }}
                    </p>
                @endcanany

                @can('machine.manage')
                    <x-nav-link :href="route('admin.machines')" :active="request()->routeIs('admin.machines')">
                        {{ __('app.nav.machines') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.machines.qr')" :active="request()->routeIs('admin.machines.qr')">
                        {{ __('app.qr.title') }}
                    </x-nav-link>
                    <x-nav-link :href="route('admin.locations')" :active="request()->routeIs('admin.locations*')">
                        {{ __('app.nav.locations') }}
                    </x-nav-link>
                @endcan

                @can('kiosk.manage')
                    <x-nav-link :href="route('admin.kiosk')" :active="request()->routeIs('admin.kiosk')">
                        {{ __('app.nav.kiosk_devices') }}
                    </x-nav-link>
                @endcan

                @can('part.manage')
                    <x-nav-link :href="route('admin.parts')" :active="request()->routeIs('admin.parts*')">
                        {{ __('app.nav.parts') }}
                    </x-nav-link>
                @endcan

                @can('template.manage')
                    <x-nav-link :href="route('admin.templates')" :active="request()->routeIs('admin.templates*')">
                        {{ __('app.nav.templates') }}
                    </x-nav-link>
                @endcan

                @can('user.manage')
                    <x-nav-link :href="route('admin.users')" :active="request()->routeIs('admin.users')">
                        {{ __('app.nav.users') }}
                    </x-nav-link>
                @endcan

                @can('holiday.manage')
                    <x-nav-link :href="route('admin.holidays')" :active="request()->routeIs('admin.holidays*')">
                        {{ __('app.nav.holidays') }}
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
