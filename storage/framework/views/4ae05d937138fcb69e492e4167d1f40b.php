<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900"><?php echo e(__('app.runs.title')); ?></h1>

        
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex min-h-14 items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 font-semibold text-red-800">
                <span class="status-dot bg-status-missed" aria-hidden="true"></span>
                <?php echo e(__('app.runs.summary_missed', ['count' => $missedCount])); ?>

            </span>
            
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('run.approve')): ?>
                <a href="<?php echo e(route('runs.approvals')); ?>"
                   class="inline-flex min-h-14 items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 font-semibold text-sky-800 hover:bg-sky-100">
                    <span class="status-dot bg-status-submitted" aria-hidden="true"></span>
                    <?php echo e(__('app.runs.summary_awaiting_approval', ['count' => $awaitingCount])); ?>

                </a>
            <?php else: ?>
                <span class="inline-flex min-h-14 items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-4 font-semibold text-sky-800">
                    <span class="status-dot bg-status-submitted" aria-hidden="true"></span>
                    <?php echo e(__('app.runs.summary_awaiting_approval', ['count' => $awaitingCount])); ?>

                </span>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('app.runs.date_from')); ?></span>
            <input type="date" wire:model.live="dateFrom"
                   class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('app.runs.date_to')); ?></span>
            <input type="date" wire:model.live="dateTo"
                   class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('app.runs.machine')); ?></span>
            <select wire:model.live="machine" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value=""><?php echo e(__('app.runs.all_machines')); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $machines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $machineOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($machineOption->id); ?>"><?php echo e($machineOption->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('app.locations.location')); ?></span>
            <select wire:model.live="location" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value=""><?php echo e(__('app.runs.all_locations')); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locationOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($locationOption->id); ?>"><?php echo e($locationOption->name); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('app.common.status')); ?></span>
            <select wire:model.live="status" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value=""><?php echo e(__('app.runs.all_statuses')); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Enums\RunStatus::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $statusOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($statusOption->value); ?>"><?php echo e($statusOption->label()); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('app.runs.shift')); ?></span>
            <select wire:model.live="shift" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value=""><?php echo e(__('app.runs.all_shifts')); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Enums\Shift::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shiftOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($shiftOption->value); ?>"><?php echo e($shiftOption->label()); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </label>
    </div>

    <div class="mb-6">
        <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'ghost','wire:click' => 'clearFilters']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'ghost','wire:click' => 'clearFilters']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e(__('app.actions.clear')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($runs->isEmpty()): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-lg text-slate-500">
            <?php echo e(__('app.runs.no_runs')); ?>

        </div>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-base">
                <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3"><?php echo e(__('app.runs.machine')); ?></th>
                        <th class="px-4 py-3"><?php echo e(__('app.runs.template')); ?></th>
                        <th class="px-4 py-3"><?php echo e(__('app.runs.scheduled_for')); ?></th>
                        <th class="px-4 py-3"><?php echo e(__('app.runs.shift')); ?></th>
                        <th class="px-4 py-3"><?php echo e(__('app.common.status')); ?></th>
                        <th class="px-4 py-3"><?php echo e(__('app.runs.progress_label')); ?></th>
                        <th class="px-4 py-3"><?php echo e(__('app.runs.operator')); ?></th>
                        <th class="px-4 py-3"><?php echo e(__('app.runs.submitted_at')); ?></th>
                        <th class="px-4 py-3"><span class="sr-only"><?php echo e(__('app.common.actions')); ?></span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $runs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $run): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'run-'.e($run->id).''; ?>wire:key="run-<?php echo e($run->id); ?>" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900"><?php echo e($run->machine->name); ?></p>
                                <p class="text-sm text-slate-500"><?php echo e($run->machine->location->name); ?></p>
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?php echo e($run->template->name); ?></td>
                            <td class="px-4 py-3 tabular-nums text-slate-700">
                                <?php echo e($run->scheduled_for->format('D j M Y')); ?>

                            </td>
                            <td class="px-4 py-3 text-slate-700"><?php echo e($run->display_shift); ?></td>
                            <td class="px-4 py-3"><?php if (isset($component)) { $__componentOriginale122a964aaade1f8044b1545740ce9f7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale122a964aaade1f8044b1545740ce9f7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-dot','data' => ['status' => $run->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-dot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->status)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale122a964aaade1f8044b1545740ce9f7)): ?>
<?php $attributes = $__attributesOriginale122a964aaade1f8044b1545740ce9f7; ?>
<?php unset($__attributesOriginale122a964aaade1f8044b1545740ce9f7); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale122a964aaade1f8044b1545740ce9f7)): ?>
<?php $component = $__componentOriginale122a964aaade1f8044b1545740ce9f7; ?>
<?php unset($__componentOriginale122a964aaade1f8044b1545740ce9f7); ?>
<?php endif; ?></td>
                            <td class="px-4 py-3 tabular-nums text-slate-700">
                                <?php echo e(__('app.runs.progress', ['done' => $run->items_done_count, 'total' => $run->items_total_count])); ?>

                            </td>
                            <td class="px-4 py-3 text-slate-700"><?php echo e($run->operator?->full_name ?? '—'); ?></td>
                            <td class="px-4 py-3 tabular-nums text-slate-700">
                                <?php echo e($run->submitted_at?->timezone($displayTz)->format('D j M, g:i A') ?? '—'); ?>

                            </td>
                            <td class="px-4 py-3 text-right">
                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->status === \App\Enums\RunStatus::Submitted && auth()->user()->can('run.approve')): ?>
                                    <a href="<?php echo e(route('runs.review', $run)); ?>"
                                       class="inline-flex min-h-14 items-center rounded-lg px-4 font-semibold text-sky-700 hover:bg-sky-50">
                                        <?php echo e(__('app.approvals.review')); ?>

                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo e(route('runs.show', $run)); ?>"
                                       class="inline-flex min-h-14 items-center rounded-lg px-4 font-semibold text-sky-700 hover:bg-sky-50">
                                        <?php echo e(__('app.actions.view')); ?>

                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                
                                <a href="<?php echo e(route('runs.pdf', $run)); ?>"
                                   class="inline-flex min-h-14 items-center rounded-lg px-4 font-semibold text-slate-600 hover:bg-slate-100">
                                    <?php echo e(__('app.actions.print')); ?>

                                </a>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            <?php echo e($runs->links()); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/runs/run-index.blade.php ENDPATH**/ ?>