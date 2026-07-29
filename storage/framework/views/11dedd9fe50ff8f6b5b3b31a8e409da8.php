
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id' => null]));

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

foreach (array_filter((['id' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php ($id = $id ?? $attributes->get('id') ?? 'checkbox-' . uniqid()); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($slot->isEmpty()): ?>
    <input type="checkbox" id="<?php echo e($id); ?>" <?php echo e($attributes->except('id')->merge(['class' => 'h-7 w-7 shrink-0 rounded-md border-slate-400 text-sky-600 focus:ring-sky-500'])); ?>>
<?php else: ?>
    <label for="<?php echo e($id); ?>" class="flex min-h-14 cursor-pointer select-none items-center gap-3">
        <input type="checkbox" id="<?php echo e($id); ?>" <?php echo e($attributes->except('id')->merge(['class' => 'h-7 w-7 shrink-0 rounded-md border-slate-400 text-sky-600 focus:ring-sky-500'])); ?>>
        <span class="text-lg"><?php echo e($slot); ?></span>
    </label>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /var/www/html/resources/views/components/checkbox.blade.php ENDPATH**/ ?>