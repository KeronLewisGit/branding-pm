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

    {{-- PWA: the kiosk is the installable surface (manifest start_url = /kiosk) --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#020617">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    {{-- Livewire 3 auto-injects its styles/scripts — @vite is all we need. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="kiosk flex min-h-full flex-col bg-slate-950 font-sans text-lg text-slate-100 antialiased"
    {{--
        Must be the SAME value the server enforces (EnforceKioskIdleTimeout
        reads the same key). Hardcoding it here meant raising
        CHECKLISTS_KIOSK_IDLE_SECONDS moved the server's deadline while the
        browser went on dropping the operator at 120s — a setting that looked
        broken rather than ignored.
    --}}
    x-data="idleRelease({{ (int) config('checklists.kiosk_idle_seconds', 120) }}, '{{ route('kiosk.release') }}')"
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

    {{--
        Connection badge. Silent while online with nothing queued; loud the
        moment the tablet is holding answers, because the one thing an
        operator must never do is walk away believing a sheet was saved.
    --}}
    <div x-data="connectionStatus" x-cloak>
        <div x-show="! online || queued > 0"
             class="sticky top-20 z-40 flex items-center gap-3 border-b border-amber-500 bg-amber-950/90 px-4 py-3 text-amber-100 backdrop-blur sm:px-6"
             role="status" aria-live="polite">
            <svg class="h-7 w-7 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75h.008v.008H12v-.008zM3.98 8.223a13.5 13.5 0 0116.04 0M6.62 11.1a9.5 9.5 0 0110.76 0M9.26 13.98a5.5 5.5 0 015.48 0" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
            </svg>
            <p class="flex-1 text-lg font-semibold">
                <span x-show="! online">{{ __('app.offline.working_offline') }}</span>
                <span x-show="online && queued > 0">{{ __('app.offline.syncing') }}</span>
                <span x-show="queued > 0" class="block text-base font-normal"
                      x-text="`{{ __('app.offline.queued_count') }}`.replace(':count', queued)"></span>
            </p>
        </div>

        {{-- Answers stranded by a reload: never replayed, always announced. --}}
        <div x-show="stranded > 0"
             class="sticky top-20 z-40 border-b-2 border-rose-500 bg-rose-950/95 px-4 py-3 text-rose-100 sm:px-6"
             role="alert">
            <p class="text-lg font-bold">{{ __('app.offline.stranded_title') }}</p>
            <p class="mt-1 text-base" x-text="`{{ __('app.offline.stranded_body') }}`.replace(':count', stranded)"></p>
            <button type="button"
                    class="mt-2 min-h-14 rounded-xl border-2 border-rose-400 px-5 text-base font-semibold"
                    x-on:click="discardStranded()">
                {{ __('app.offline.stranded_dismiss') }}
            </button>
        </div>
    </div>

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
