{{--
    Icon-only action button, for the Actions column of a list.

    Five word-buttons in a row wrap onto three lines and make a table look
    unfinished; five icons fit on one. The trade is discoverability, so this
    component makes the accessible name non-optional:

      - `aria-label` names it for a screen reader
      - `title` gives a sighted user the same word on hover
      - the icon itself is aria-hidden, so nothing is announced twice

        <x-icon-button icon="edit" :label="__('app.actions.edit')"
                       wire:click="openEditModal(1)" />
        <x-icon-button icon="delete" variant="danger" :label="__('app.actions.delete')"
                       wire:click="confirmDelete(1)" />

    Renders an <a> when `href` is present, a <button> otherwise — same rule as
    <x-button>.

    44px square: below the WCAG 2.2 target-size minimum a mouse user starts
    missing, and these sit next to each other.
--}}
@props([
    'icon',
    'label',
    'variant' => 'ghost',
    'href' => null,
])

@php
    // A disabled control keeps its place in the row. Dropping the button
    // instead shifts every icon beside it, so the same action sits under a
    // different pixel from one row to the next — and the reader is left to
    // work out whether it is missing or forbidden. Greyed out with the reason
    // in its tooltip answers that.
    // `has('disabled')` is NOT the test: the attribute bag still holds the key
    // for `:disabled="false"`, so using it greyed out every button on the
    // page. Read the value, and accept the several shapes Blade produces —
    // `true` from `:disabled="$expr"`, and the string "disabled" from
    // `@disabled($expr)`.
    $disabledValue = $attributes->get('disabled', false);
    $isDisabled = $disabledValue === true
        || $disabledValue === 'disabled'
        || $disabledValue === 'true'
        || $disabledValue === '1';

    // Literal class strings — Tailwind's JIT cannot see an interpolated one.
    $variantClasses = $isDisabled
        ? 'text-slate-300 cursor-not-allowed'
        : match ($variant) {
            'danger' => 'text-rose-600 hover:bg-rose-50 hover:text-rose-700 active:bg-rose-100',
            'primary' => 'text-sky-700 hover:bg-sky-50 hover:text-sky-800 active:bg-sky-100',
            default => 'text-slate-500 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200',
        };

    // One place for the whole vocabulary, so "edit" looks the same on every
    // screen and a new list cannot invent its own pencil.
    $paths = [
        'view' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'profile' => '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M7 8V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'delete' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>',
        'operators' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><line x1="14" y1="14" x2="14" y2="21"/><line x1="18" y1="14" x2="21" y2="14"/><line x1="21" y1="18" x2="21" y2="21"/>',
        'activate' => '<path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><line x1="12" y1="2" x2="12" y2="12"/>',
        'deactivate' => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
        'setup' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="7.5 12 10.5 15 16.5 9"/>',
        'revoke' => '<path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/><line x1="3" y1="3" x2="21" y2="21"/>',
        'pin' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'verify' => '<path d="M9 12l2 2 4-4"/><path d="M12 3l7 4v5c0 4.4-3 8.5-7 9.5C8 20.5 5 16.4 5 12V7l7-4z"/>',
        'move_up' => '<line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>',
        'move_down' => '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
        'restore' => '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
        'duplicate' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
    ];

    $path = $paths[$icon] ?? $paths['edit'];
@endphp

<{{ $href ? 'a' : 'button' }}
    @if ($href) href="{{ $href }}" @else type="button" @endif
    aria-label="{{ $label }}"
    title="{{ $label }}"
    {{-- A disabled <button> is already out of the tab order and unclickable;
         `aria-disabled` is what tells a screen reader why it is still here. --}}
    @if ($isDisabled) aria-disabled="true" @endif
    {{ $attributes->merge(['class' => 'inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-1 '.$variantClasses]) }}
>
    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true" focusable="false">
        {!! $path !!}
    </svg>
</{{ $href ? 'a' : 'button' }}>
