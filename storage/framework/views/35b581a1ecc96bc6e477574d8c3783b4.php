<div>
     <?php $__env->slot('header', null, []); ?> 
        <p class="truncate text-2xl font-bold text-white"><?php echo e(__('app.kiosk.pick_machine')); ?></p>
        <p class="truncate text-base text-slate-300"><?php echo e(__('app.kiosk.scan_hint')); ?></p>
     <?php $__env->endSlot(); ?>

    
    <div class="mb-6 flex flex-wrap gap-3" role="tablist" aria-label="<?php echo e(__('app.kiosk.filter_by_location')); ?>">
        <button
            type="button"
            wire:click="$set('locationId', '')"
            role="tab"
            aria-selected="<?php echo e($locationId === '' ? 'true' : 'false'); ?>"
            class="min-h-[72px] rounded-2xl border-2 px-8 text-xl font-bold
                <?php echo e($locationId === '' ? 'border-white bg-white text-slate-900' : 'border-slate-600 bg-slate-900 text-slate-100'); ?>"
        >
            <?php echo e(__('app.kiosk.all_locations')); ?>

        </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $location): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <button
                type="button"
                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'loc-'.e($location->id).''; ?>wire:key="loc-<?php echo e($location->id); ?>"
                wire:click="$set('locationId', '<?php echo e($location->id); ?>')"
                role="tab"
                aria-selected="<?php echo e((int) $locationId === $location->id ? 'true' : 'false'); ?>"
                class="min-h-[72px] rounded-2xl border-2 px-8 text-xl font-bold
                    <?php echo e((int) $locationId === $location->id ? 'border-white bg-white text-slate-900' : 'border-slate-600 bg-slate-900 text-slate-100'); ?>"
            >
                <?php echo e($location->name); ?>

            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($machines->isEmpty()): ?>
        <div class="rounded-2xl border border-slate-700 bg-slate-900 p-10 text-center text-xl text-slate-300">
            <?php echo e(__('app.machines.no_machines')); ?>

        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $machines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $machine): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $machineStatus = $statuses[$machine->id] ?? 'none';
                    $hasBreakdown = in_array($machine->id, $breakdownIds, true);

                    // Literal classes so Tailwind JIT compiles them. Colour is
                    // ALWAYS paired with the text label below — never alone.
                    [$dotClass, $statusLabel] = match ($machineStatus) {
                        'due' => ['bg-slate-400', __('app.kiosk.status_due')],
                        'in_progress' => ['bg-amber-500', __('app.kiosk.status_in_progress')],
                        'done' => ['bg-emerald-600', __('app.kiosk.status_done')],
                        'overdue' => ['bg-red-700', __('app.kiosk.status_overdue')],
                        default => [null, __('app.kiosk.status_none')],
                    };
                ?>

                <a
                    href="<?php echo e(route('kiosk.machine', $machine)); ?>"
                    <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'machine-'.e($machine->id).''; ?>wire:key="machine-<?php echo e($machine->id); ?>"
                    class="flex min-h-[140px] flex-col justify-between rounded-2xl p-5 active:bg-slate-800
                        <?php echo e($hasBreakdown
                            ? 'border-4 border-red-600 bg-red-950'
                            : 'border-2 border-slate-700 bg-slate-900'); ?>"
                >
                    <div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasBreakdown): ?>
                            <p class="mb-2 inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-1 text-base font-bold uppercase tracking-wide text-white">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                </svg>
                                <?php echo e(__('app.issues.open_breakdown_flag')); ?>

                            </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <p class="text-2xl font-bold text-white"><?php echo e($machine->name); ?></p>
                        <p class="mt-1 text-base text-slate-300"><?php echo e($machine->location->name); ?></p>
                    </div>

                    <p class="mt-4 inline-flex items-center gap-2 text-lg font-semibold <?php echo e($machineStatus === 'none' ? 'text-slate-400' : 'text-slate-100'); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dotClass !== null): ?>
                            <span class="status-dot <?php echo e($dotClass); ?>" aria-hidden="true"></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php echo e($statusLabel); ?>

                    </p>
                </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/kiosk/machine-picker.blade.php ENDPATH**/ ?>