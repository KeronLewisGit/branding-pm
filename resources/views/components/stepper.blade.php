{{--
    Parts quantity stepper: big − / + buttons either side of a number, sized
    for one gloved thumb. wire:model goes straight onto the component and is
    forwarded to the inner input; the buttons set the value and dispatch
    input/change events so Livewire (wire:model.live) picks the change up.

        <x-stepper wire:model.live="parts.{{ $i }}.qty_used" :label="$part->name" />
--}}
@props(['min' => 0, 'max' => 9999, 'step' => 1, 'label' => null])

<div
    {{ $attributes->only('class')->merge(['class' => 'flex items-center gap-2']) }}
    x-data="{
        set(value) {
            const el = $refs.qty;
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },
        current() {
            return parseFloat($refs.qty.value) || 0;
        },
        decrement() {
            this.set(Math.max({{ $min }}, this.current() - {{ $step }}));
        },
        increment() {
            this.set(Math.min({{ $max }}, this.current() + {{ $step }}));
        },
    }"
>
    <button
        type="button"
        class="btn btn-ghost h-14 w-14 min-h-14 shrink-0 px-0 text-2xl"
        x-on:click="decrement"
        aria-label="{{ __('app.stepper.decrease') }}@if ($label) — {{ $label }}@endif"
    >&minus;</button>

    <input
        x-ref="qty"
        type="number"
        inputmode="decimal"
        min="{{ $min }}"
        max="{{ $max }}"
        step="{{ $step }}"
        @if ($label) aria-label="{{ $label }}" @endif
        {{ $attributes->except('class')->merge(['class' => 'input w-24 shrink-0 text-center text-xl font-semibold tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none']) }}
    >

    <button
        type="button"
        class="btn btn-ghost h-14 w-14 min-h-14 shrink-0 px-0 text-2xl"
        x-on:click="increment"
        aria-label="{{ __('app.stepper.increase') }}@if ($label) — {{ $label }}@endif"
    >+</button>
</div>
