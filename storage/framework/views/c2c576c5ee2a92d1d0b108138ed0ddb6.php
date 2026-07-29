
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label' => null,
    'hint' => null,
    'height' => 'h-44',
]));

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

foreach (array_filter(([
    'label' => null,
    'hint' => null,
    'height' => 'h-44',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div wire:ignore x-data="signaturePad" x-modelable="dataUrl" <?php echo e($attributes); ?>>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($label): ?>
        <p class="text-lg font-semibold [.kiosk_&]:text-slate-100"><?php echo e($label); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hint): ?>
        <p class="mt-1 text-base text-slate-500 [.kiosk_&]:text-slate-400"><?php echo e($hint); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="relative mt-2 overflow-hidden rounded-xl border-2 border-dashed border-slate-400 bg-white <?php echo e($height); ?>">
        
        <div class="pointer-events-none absolute inset-x-6 bottom-8 border-b border-slate-300" aria-hidden="true"></div>

        <p x-show="! hasInk" x-cloak
           class="pointer-events-none absolute inset-0 flex items-center justify-center text-lg text-slate-400">
            <?php echo e(__('app.runs.sign_here')); ?>

        </p>

        
        <canvas
            x-ref="canvas"
            class="h-full w-full touch-none"
            role="img"
            aria-label="<?php echo e($label ?? __('app.runs.signature')); ?>"
            x-on:pointerdown.prevent="start($event)"
            x-on:pointermove.prevent="move($event)"
            x-on:pointerup="end($event)"
            x-on:pointercancel="end($event)"
            x-on:pointerleave="end($event)"
        ></canvas>
    </div>

    <div class="mt-2 flex items-center justify-between gap-4">
        <p class="text-base" aria-live="polite">
            <span x-show="hasInk" x-cloak class="font-semibold text-emerald-600 [.kiosk_&]:text-emerald-400">
                <?php echo e(__('app.runs.signature_captured')); ?>

            </span>
        </p>

        <button type="button"
            class="flex min-h-14 items-center justify-center rounded-xl border-2 border-slate-400 px-5 text-lg font-semibold text-slate-600 active:bg-slate-100 [.kiosk_&]:border-slate-600 [.kiosk_&]:text-slate-300 [.kiosk_&]:active:bg-slate-800"
            x-on:click="clear()">
            <?php echo e(__('app.runs.signature_clear')); ?>

        </button>
    </div>
</div>
<?php /**PATH /var/www/html/resources/views/components/signature-pad.blade.php ENDPATH**/ ?>