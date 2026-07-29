<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['color' => 'slate']));

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

foreach (array_filter((['color' => 'slate']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $classes = match ($color) {
        'amber' => 'bg-amber-100 text-amber-900',
        'sky' => 'bg-sky-100 text-sky-900',
        'emerald' => 'bg-emerald-100 text-emerald-900',
        'rose' => 'bg-rose-100 text-rose-900',
        'red' => 'bg-red-100 text-red-900',
        default => 'bg-slate-200 text-slate-800',
    };
?>

<span <?php echo e($attributes->merge(['class' => "badge {$classes}"])); ?>><?php echo e($slot); ?></span>
<?php /**PATH /var/www/html/resources/views/components/badge.blade.php ENDPATH**/ ?>