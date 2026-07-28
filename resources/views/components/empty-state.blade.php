{{-- Text colours inherit from .card so this reads correctly on kiosk (dark) too. --}}
@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'card flex flex-col items-center justify-center gap-2 p-10 text-center']) }}>
    @isset($icon)
        <div class="mb-2 text-slate-400" aria-hidden="true">{{ $icon }}</div>
    @endisset

    <p class="text-xl font-semibold">{{ $title }}</p>

    @if ($description)
        <p class="max-w-md text-base opacity-75">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset

    {{ $slot }}
</div>
