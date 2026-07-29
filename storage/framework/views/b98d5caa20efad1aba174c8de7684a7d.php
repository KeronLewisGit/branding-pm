
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'label',
    'user' => null,
    'path' => null,
    'signedAt' => null,
    'note' => null,
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
    'label',
    'user' => null,
    'path' => null,
    'signedAt' => null,
    'note' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $displayTz = (string) config('app.display_timezone', 'UTC');
?>

<div <?php echo e($attributes->merge(['class' => 'rounded-xl border border-slate-200 p-4 [.kiosk_&]:border-slate-700'])); ?>>
    <p class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?php echo e($label); ?></p>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($path): ?>
        
        <div class="mt-2 flex h-28 items-center justify-center rounded-lg border border-slate-200 bg-white p-2">
            <img src="<?php echo e(\App\Support\SignatureImage::url($path)); ?>"
                 alt="<?php echo e(__('app.runs.signature_of', ['name' => $user?->full_name ?? __('app.runs.unknown_user')])); ?>"
                 class="max-h-full max-w-full object-contain">
        </div>
    <?php else: ?>
        <div class="mt-2 flex h-28 items-center justify-center rounded-lg border border-dashed border-slate-300 [.kiosk_&]:border-slate-700">
            <p class="text-base text-slate-500"><?php echo e(__('app.runs.not_signed')); ?></p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <p class="mt-3 text-lg font-semibold [.kiosk_&]:text-slate-100">
        <?php echo e($user?->full_name ?? __('app.runs.unknown_user')); ?>

    </p>
    <p class="text-base text-slate-500">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user?->employee_number): ?>
            #<?php echo e($user->employee_number); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signedAt): ?>
            <span class="tabular-nums">· <?php echo e($signedAt->timezone($displayTz)->format('D j M Y, g:i A')); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </p>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($note): ?>
        <p class="mt-2 text-base text-slate-600 [.kiosk_&]:text-slate-300"><?php echo e($note); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/components/signature-block.blade.php ENDPATH**/ ?>