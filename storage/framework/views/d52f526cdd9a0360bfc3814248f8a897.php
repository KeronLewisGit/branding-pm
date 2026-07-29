
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['name', 'show' => false, 'title' => null, 'maxWidth' => 'lg']));

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

foreach (array_filter((['name', 'show' => false, 'title' => null, 'maxWidth' => 'lg']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        default => 'sm:max-w-lg',
    };
?>

<div
    x-data="{ show: <?php echo \Illuminate\Support\Js::from($show)->toHtml() ?> }"
    x-on:open-modal.window="if ($event.detail === '<?php echo e($name); ?>' || $event.detail?.name === '<?php echo e($name); ?>') show = true"
    x-on:close-modal.window="if ($event.detail === '<?php echo e($name); ?>' || $event.detail?.name === '<?php echo e($name); ?>') show = false"
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
        <?php if($title): ?> aria-label="<?php echo e($title); ?>" <?php endif; ?>
        <?php echo e($attributes->merge(['class' => "card relative z-10 w-full {$maxWidthClass}"])); ?>

    >
        <div class="flex items-start justify-between gap-4 p-6 pb-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($title): ?>
                <h2 class="pt-3 text-xl font-bold"><?php echo e($title); ?></h2>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <button
                type="button"
                class="ml-auto flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-700 active:bg-slate-200 [.kiosk_&]:text-slate-300 [.kiosk_&]:hover:bg-slate-800 [.kiosk_&]:hover:text-white"
                x-on:click="show = false"
                aria-label="<?php echo e(__('app.common.close')); ?>"
            >
                <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="p-6 pt-2">
            <?php echo e($slot); ?>

        </div>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/components/modal.blade.php ENDPATH**/ ?>