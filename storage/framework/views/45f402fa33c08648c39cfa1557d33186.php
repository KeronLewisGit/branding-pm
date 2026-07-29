
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['min' => 0, 'max' => 9999, 'step' => 1, 'label' => null]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['min' => 0, 'max' => 9999, 'step' => 1, 'label' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    <?php echo e($attributes->only('class')->merge(['class' => 'flex items-center gap-2'])); ?>

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
            this.set(Math.max(<?php echo e($min); ?>, this.current() - <?php echo e($step); ?>));
        },
        increment() {
            this.set(Math.min(<?php echo e($max); ?>, this.current() + <?php echo e($step); ?>));
        },
    }"
>
    <button
        type="button"
        class="btn btn-ghost h-14 w-14 min-h-14 shrink-0 px-0 text-2xl"
        x-on:click="decrement"
        aria-label="<?php echo e(__('app.stepper.decrease')); ?><?php if($label): ?> — <?php echo e($label); ?><?php endif; ?>"
    >&minus;</button>

    <input
        x-ref="qty"
        type="number"
        inputmode="decimal"
        min="<?php echo e($min); ?>"
        max="<?php echo e($max); ?>"
        step="<?php echo e($step); ?>"
        <?php if($label): ?> aria-label="<?php echo e($label); ?>" <?php endif; ?>
        <?php echo e($attributes->except('class')->merge(['class' => 'input w-24 shrink-0 text-center text-xl font-semibold tabular-nums [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none'])); ?>

    >

    <button
        type="button"
        class="btn btn-ghost h-14 w-14 min-h-14 shrink-0 px-0 text-2xl"
        x-on:click="increment"
        aria-label="<?php echo e(__('app.stepper.increase')); ?><?php if($label): ?> — <?php echo e($label); ?><?php endif; ?>"
    >+</button>
</div>
<?php /**PATH /var/www/html/resources/views/components/stepper.blade.php ENDPATH**/ ?>