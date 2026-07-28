{{--
    Kiosk layout — 10" shared tablet on the shop floor. Full-bleed, dark,
    high-contrast, no browser-nav affordances, no hover-only affordances.
    <body class="kiosk"> activates the dark overrides in app.css.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Branding PM') }}</title>

    {{-- Livewire 3 auto-injects its styles/scripts — @vite is all we need. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="kiosk flex min-h-full flex-col bg-slate-950 font-sans text-lg text-slate-100 antialiased"
    x-data="idleRelease(120, '{{ route('kiosk.release') }}')"
>
    {{-- Persistent header: machine/location context (header slot) + clock --}}
    <header data-nav-chrome class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900">
        <div class="flex min-h-20 items-center justify-between gap-4 px-4 py-2 sm:px-6">
            <div class="min-w-0 flex-1">
                @isset($header)
                    {{ $header }}
                @else
                    <p class="truncate text-2xl font-bold text-white">{{ config('app.name', 'Branding PM') }}</p>
                @endisset
            </div>

            {{-- Clock in the plant's display timezone, server-rendered first paint --}}
            @php($displayTz = config('app.display_timezone', 'UTC'))
            @php($jsLocale = str_replace('_', '-', app()->getLocale()))
            <div
                class="shrink-0 text-right tabular-nums"
                x-data="{
                    time: '',
                    date: '',
                    tick() {
                        const now = new Date();
                        this.time = new Intl.DateTimeFormat('{{ $jsLocale }}', {
                            hour: 'numeric', minute: '2-digit', hour12: true, timeZone: '{{ $displayTz }}',
                        }).format(now);
                        this.date = new Intl.DateTimeFormat('{{ $jsLocale }}', {
                            weekday: 'short', day: 'numeric', month: 'short', timeZone: '{{ $displayTz }}',
                        }).format(now);
                    },
                }"
                x-init="tick(); setInterval(() => tick(), 1000)"
            >
                <p class="text-3xl font-bold leading-tight text-white" x-text="time">{{ now($displayTz)->format('g:i A') }}</p>
                <p class="text-base text-slate-300" x-text="date">{{ now($displayTz)->format('D j M') }}</p>
            </div>
        </div>
    </header>

    <main class="flex-1 px-4 py-6 sm:px-6">
        @if (session('status'))
            <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="error" class="mb-6">{{ session('error') }}</x-alert>
        @endif

        {{ $slot }}
    </main>

    @stack('scripts')
</body>
</html>
