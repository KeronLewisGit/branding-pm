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
<body class="flex min-h-screen flex-col items-center justify-center bg-slate-100 px-4 py-8 font-sans text-slate-900 antialiased">
    <main class="w-full max-w-md">
        <h1 class="mb-6 text-center text-2xl font-bold text-slate-900">
            {{ config('app.name', 'Branding PM') }}
        </h1>

        @if (session('status'))
            <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="error" class="mb-6">{{ session('error') }}</x-alert>
        @endif

        <div class="card p-6 sm:p-8">
            {{ $slot }}
        </div>
    </main>

    @stack('scripts')
</body>
</html>
