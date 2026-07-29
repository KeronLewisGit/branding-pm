
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status']));

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

foreach (array_filter((['status']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;

    // Literal class names so Tailwind's JIT compiles them.
    $dotClass = match ($value) {
        'pending' => 'bg-status-pending',
        'in_progress' => 'bg-status-in_progress',
        'submitted' => 'bg-status-submitted',
        'approved' => 'bg-status-approved',
        'rejected' => 'bg-status-rejected',
        'missed' => 'bg-status-missed',
        default => 'bg-slate-400',
    };

    $label = $status instanceof \BackedEnum && method_exists($status, 'label')
        ? $status->label()
        : __('app.status.' . $value);
?>

<span <?php echo e($attributes->merge(['class' => 'inline-flex items-center gap-2'])); ?>>
    <span class="status-dot <?php echo e($dotClass); ?>" aria-hidden="true"></span>
    <span class="font-medium"><?php echo e($label); ?></span>
</span>
<?php /**PATH /var/www/html/resources/views/components/status-dot.blade.php ENDPATH**/ ?>