{{--
    Alpine-driven modal. Focus-trapped (x-trap ships with Livewire 3's bundled
    Alpine Focus plugin), Escape closes, overlay tap closes.

    Open / close:
      Alpine:   $dispatch('open-modal', 'confirm-delete')
      Livewire: $this->dispatch('open-modal', name: 'confirm-delete');
                $this->dispatch('close-modal', name: 'confirm-delete');
--}}
@props(['name', 'show' => false, 'title' => null, 'maxWidth' => 'lg'])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-lg',
    };
@endphp

<div
    x-data="{ show: @js($show) }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}' || $event.detail?.name === '{{ $name }}') show = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}' || $event.detail?.name === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
>
    <div
        x-show="show"
        x-transition.opacity
        class="fixed inset-0 bg-slate-950/70"
        x-on:click="show = false"
        aria-hidden="true"
    ></div>

    <div
        x-show="show"
        x-trap.inert.noscroll="show"
        x-transition
        role="dialog"
        aria-modal="true"
        @if ($title) aria-label="{{ $title }}" @endif
        {{ $attributes->merge(['class' => "card relative z-10 w-full {$maxWidthClass}"]) }}
    >
        <div class="flex items-start justify-between gap-4 p-6 pb-0">
            @if ($title)
                <h2 class="pt-3 text-xl font-bold">{{ $title }}</h2>
            @endif

            <button
                type="button"
                class="ml-auto flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-700 active:bg-slate-200 [.kiosk_&]:text-slate-300 [.kiosk_&]:hover:bg-slate-800 [.kiosk_&]:hover:text-white"
                x-on:click="show = false"
                aria-label="{{ __('app.common.close') }}"
            >
                <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-6 pt-2">
            {{ $slot }}
        </div>
    </div>
</div>
