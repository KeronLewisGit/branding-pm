@props(['title'])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-4']) }}>
    <h1 class="text-2xl font-bold text-slate-900 [.kiosk_&]:text-white">{{ $title }}</h1>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
    @endisset

    {{ $slot }}
</div>
