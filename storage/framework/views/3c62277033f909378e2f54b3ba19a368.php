
<?php use \App\Enums\ResponseType; ?>
<?php use \App\Enums\RunItemStatus; ?>
<?php use \App\Enums\RunStatus; ?>
<?php use \App\Enums\Shift; ?>
<?php use \App\Enums\IssueSeverity; ?>

<?php
    $isEditable = $this->isEditable;
?>

<div class="mx-auto w-full max-w-3xl pb-24">

    

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->status === RunStatus::Rejected): ?>
        <div class="mb-6 rounded-2xl border-2 border-rose-500 bg-rose-950/50 p-5" role="alert">
            <p class="text-xl font-bold text-rose-200"><?php echo e(__('app.runs.rejected_banner')); ?></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->supervisor_comment): ?>
                <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-rose-400"><?php echo e(__('app.runs.supervisor_comment')); ?></p>
                <blockquote class="mt-1 border-l-4 border-rose-500 pl-3 text-lg text-rose-100"><?php echo e($run->supervisor_comment); ?></blockquote>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isEditable)): ?>
        <div class="mb-6 rounded-2xl border-2 border-slate-500 bg-slate-900 p-5">
            <?php if (isset($component)) { $__componentOriginale122a964aaade1f8044b1545740ce9f7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale122a964aaade1f8044b1545740ce9f7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-dot','data' => ['status' => $run->status,'class' => 'text-xl']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-dot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->status),'class' => 'text-xl']); ?>
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
<?php endif; ?>
            <p class="mt-2 text-lg text-slate-200"><?php echo e(__('app.runs.read_only', ['status' => $run->status->label()])); ?></p>
            <p class="mt-1 text-base text-slate-400"><?php echo e(__('app.runs.read_only_hint')); ?></p>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($conflictNotice): ?>
        <div class="mb-6 flex items-center gap-4 rounded-2xl border-2 border-amber-500 bg-amber-950/50 p-4" role="alert" aria-live="assertive">
            <p class="flex-1 text-lg text-amber-100"><?php echo e($conflictNotice); ?></p>
            <button type="button" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-2xl text-amber-300"
                wire:click="$set('conflictNotice', null)" aria-label="<?php echo e(__('app.common.close')); ?>">&times;</button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($notice): ?>
        <div class="mb-6 flex items-center gap-4 rounded-2xl border-2 border-emerald-500 bg-emerald-950/50 p-4" role="status" aria-live="polite">
            <p class="flex-1 text-lg text-emerald-100"><?php echo e($notice); ?></p>
            <button type="button" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-2xl text-emerald-300"
                wire:click="$set('notice', null)" aria-label="<?php echo e(__('app.common.close')); ?>">&times;</button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    

    <section class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold leading-tight"><?php echo e($run->template->name); ?></h1>
                <p class="mt-1 text-lg text-slate-300">
                    <?php echo e($run->machine->name); ?>

                    <span class="text-slate-500">· <?php echo e($run->machine->code); ?></span>
                </p>
            </div>
            <?php if (isset($component)) { $__componentOriginale122a964aaade1f8044b1545740ce9f7 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale122a964aaade1f8044b1545740ce9f7 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-dot','data' => ['status' => $run->status,'class' => 'text-lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-dot'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->status),'class' => 'text-lg']); ?>
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
<?php endif; ?>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->shift->isSplit()): ?>
            <?php
                $isNight = $run->shift === Shift::Night;
            ?>
            <div class="mt-4 flex items-center gap-3 rounded-2xl border-2 px-5 py-4 <?php echo e($isNight ? 'border-indigo-400 bg-indigo-950/70 text-indigo-100' : 'border-amber-400 bg-amber-400/10 text-amber-100'); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isNight): ?>
                    <svg class="h-9 w-9 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                <?php else: ?>
                    <svg class="h-9 w-9 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <p class="text-2xl font-extrabold uppercase tracking-wider">
                    <?php echo e(__('app.runs.shift_sheet', ['shift' => $run->shift->label()])); ?>

                </p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-lg sm:grid-cols-2">
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?php echo e(__('app.templates.work_category')); ?></dt>
                <dd class="font-semibold"><?php echo e($run->template->work_category->label()); ?></dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?php echo e(__('app.runs.scheduled_for')); ?></dt>
                <dd class="font-semibold"><?php echo e($run->scheduled_for->translatedFormat('D, j M Y')); ?></dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?php echo e(__('app.locations.location')); ?></dt>
                <dd><?php echo e($run->machine->location->name); ?></dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?php echo e(__('app.locations.building')); ?> / <?php echo e(__('app.locations.floor')); ?></dt>
                <dd>
                    <?php echo e($run->machine->location->site?->name); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->machine->location->floor): ?> · <?php echo e($run->machine->location->floor); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500"><?php echo e(__('app.templates.work_description')); ?></dt>
                <dd><?php echo e($run->template->work_description); ?></dd>
            </div>
        </dl>
    </section>

    

    <div class="sticky top-20 z-30 -mx-4 mt-6 border-b border-slate-800 bg-slate-950/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-xl font-bold tabular-nums" aria-live="polite">
                <?php echo e(__('app.runs.progress', ['done' => $progress['done'], 'total' => $progress['total']])); ?>

            </p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->shift->isSplit()): ?>
                <span class="rounded-full border-2 px-3 py-1 text-base font-bold uppercase tracking-wide <?php echo e($run->shift === Shift::Night ? 'border-indigo-400 text-indigo-200' : 'border-amber-400 text-amber-200'); ?>">
                    <?php echo e($run->shift->label()); ?>

                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php
            $pct = $progress['total'] > 0 ? (int) round($progress['done'] / $progress['total'] * 100) : 0;
        ?>
        <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-800" role="progressbar"
            aria-valuemin="0" aria-valuemax="<?php echo e($progress['total']); ?>" aria-valuenow="<?php echo e($progress['done']); ?>">
            <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" style="width: <?php echo e($pct); ?>%"></div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>
        <p class="mt-4 text-base text-slate-400"><?php echo e(__('app.runs.tap_hint')); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    

    <ol class="mt-4 space-y-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $run->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $status = $item->status;
                $guidance = $item->templateItem?->guidance;
                $rowTone = match ($status) {
                    RunItemStatus::Done => 'border-emerald-500/60 bg-emerald-950/30',
                    RunItemStatus::NotApplicable => 'border-slate-500/70 bg-slate-800/50',
                    RunItemStatus::Failed => 'border-rose-500/70 bg-rose-950/30',
                    default => 'border-slate-700 bg-slate-900',
                };
                // Must be character-identical in wire:click and wire:target
                // so the spinner lights up on THIS row only.
                $rowTarget = "toggleDone({$item->id}, '{$status->value}')";
            ?>

            <li id="run-item-<?php echo e($item->id); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-'.e($item->id).''; ?>wire:key="item-<?php echo e($item->id); ?>" class="scroll-mt-44">
                <div class="flex items-stretch gap-2">

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->response_type === ResponseType::Check): ?>
                        
                        <button
                            type="button"
                            class="flex min-h-20 flex-1 items-center gap-4 rounded-2xl border-2 p-4 text-left transition-colors <?php echo e($rowTone); ?> disabled:opacity-60"
                            wire:click="<?php echo e($rowTarget); ?>"
                            wire:target="<?php echo e($rowTarget); ?>"
                            wire:loading.attr="disabled"
                            x-on:contextmenu.prevent="$wire.openActions(<?php echo e($item->id); ?>, '<?php echo e($status->value); ?>')"
                            aria-pressed="<?php echo e($status === RunItemStatus::Done ? 'true' : 'false'); ?>"
                            <?php if(! $isEditable): echo 'disabled'; endif; ?>
                        >
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-slate-600 text-xl font-bold tabular-nums"><?php echo e($item->sort_order); ?></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-semibold leading-snug"><?php echo e($item->description); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($item->is_required)): ?>
                                    <span class="text-sm text-slate-500"><?php echo e(__('app.common.optional')); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($guidance): ?>
                                    <span class="mt-1 block text-base leading-snug text-slate-400"><?php echo e($guidance); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === RunItemStatus::Failed && $item->fail_reason): ?>
                                    <span class="mt-1 block text-base font-medium text-rose-300"><?php echo e($item->fail_reason); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->completedBy !== null): ?>
                                    <span class="mt-1 block text-sm text-slate-500"><?php echo e(__('app.runs.answered_by', ['name' => $item->completedBy->full_name])); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <span class="ml-2 flex shrink-0 flex-col items-center gap-1">
                                <svg wire:loading wire:target="<?php echo e($rowTarget); ?>" class="h-9 w-9 animate-spin text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="<?php echo e($rowTarget); ?>" class="flex flex-col items-center gap-1">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === RunItemStatus::Done): ?>
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white">
                                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </span>
                                        <span class="text-sm font-semibold text-emerald-300"><?php echo e($status->label()); ?></span>
                                    <?php elseif($status === RunItemStatus::NotApplicable): ?>
                                        <span class="rounded-full border-2 border-slate-400 px-3 py-1 text-base font-bold text-slate-200"><?php echo e($status->label()); ?></span>
                                    <?php elseif($status === RunItemStatus::Failed): ?>
                                        <span class="rounded-full bg-rose-600 px-3 py-1 text-base font-bold text-white"><?php echo e($status->label()); ?></span>
                                    <?php else: ?>
                                        <span class="h-10 w-10 rounded-full border-2 border-slate-500" aria-hidden="true"></span>
                                        <span class="sr-only"><?php echo e($status->label()); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                            </span>
                        </button>
                    <?php else: ?>
                        
                        <div
                            class="min-h-20 flex-1 rounded-2xl border-2 p-4 <?php echo e($rowTone); ?>"
                            x-data="{ saving: false }"
                            x-on:contextmenu.prevent="$wire.openActions(<?php echo e($item->id); ?>, '<?php echo e($status->value); ?>')"
                        >
                            <div class="flex items-start gap-4">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-slate-600 text-xl font-bold tabular-nums"><?php echo e($item->sort_order); ?></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-lg font-semibold leading-snug"><?php echo e($item->description); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($item->is_required)): ?>
                                        <span class="text-sm text-slate-500"><?php echo e(__('app.common.optional')); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($guidance): ?>
                                        <span class="mt-1 block text-base leading-snug text-slate-400"><?php echo e($guidance); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === RunItemStatus::Failed && $item->fail_reason): ?>
                                        <span class="mt-1 block text-base font-medium text-rose-300"><?php echo e($item->fail_reason); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->completedBy !== null): ?>
                                        <span class="mt-1 block text-sm text-slate-500"><?php echo e(__('app.runs.answered_by', ['name' => $item->completedBy->full_name])); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                                <span class="ml-2 shrink-0">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status === RunItemStatus::Done): ?>
                                        <span class="rounded-full bg-emerald-600 px-3 py-1 text-base font-bold text-white"><?php echo e($status->label()); ?></span>
                                    <?php elseif($status === RunItemStatus::NotApplicable): ?>
                                        <span class="rounded-full border-2 border-slate-400 px-3 py-1 text-base font-bold text-slate-200"><?php echo e($status->label()); ?></span>
                                    <?php elseif($status === RunItemStatus::Failed): ?>
                                        <span class="rounded-full bg-rose-600 px-3 py-1 text-base font-bold text-white"><?php echo e($status->label()); ?></span>
                                    <?php else: ?>
                                        <span class="rounded-full border-2 border-slate-600 px-3 py-1 text-base font-semibold text-slate-400"><?php echo e($status->label()); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->response_type === ResponseType::PassFail): ?>
                                <?php
                                    $passTarget = "markPass({$item->id}, '{$status->value}')";
                                ?>
                                <div class="mt-3 flex gap-3 sm:pl-16">
                                    <button type="button"
                                        class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 text-lg font-bold transition-colors disabled:opacity-60 <?php echo e($status === RunItemStatus::Done ? 'border-emerald-500 bg-emerald-600 text-white' : 'border-slate-600 bg-slate-800 text-slate-200'); ?>"
                                        wire:click="<?php echo e($passTarget); ?>"
                                        wire:target="<?php echo e($passTarget); ?>"
                                        wire:loading.attr="disabled"
                                        <?php if(! $isEditable): echo 'disabled'; endif; ?>
                                    ><?php echo e(__('app.runs.pass')); ?></button>
                                    <button type="button"
                                        class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 text-lg font-bold transition-colors disabled:opacity-60 <?php echo e($status === RunItemStatus::Failed ? 'border-rose-500 bg-rose-600 text-white' : 'border-slate-600 bg-slate-800 text-slate-200'); ?>"
                                        wire:click="openFailFormFor(<?php echo e($item->id); ?>, '<?php echo e($status->value); ?>')"
                                        wire:loading.attr="disabled"
                                        <?php if(! $isEditable): echo 'disabled'; endif; ?>
                                    ><?php echo e(__('app.runs.fail')); ?></button>
                                </div>
                            <?php elseif($item->response_type === ResponseType::Numeric): ?>
                                <div class="mt-3 flex items-center gap-3 sm:pl-16">
                                    <input
                                        type="number"
                                        inputmode="decimal"
                                        step="any"
                                        value="<?php echo e($item->value_numeric); ?>"
                                        class="input min-h-14 w-44 text-center text-xl font-semibold"
                                        aria-label="<?php echo e(__('app.runs.value_numeric')); ?> — <?php echo e($item->description); ?>"
                                        x-on:change="saving = true; $wire.setNumeric(<?php echo e($item->id); ?>, $event.target.value, '<?php echo e($status->value); ?>').finally(() => saving = false)"
                                        <?php if(! $isEditable): echo 'disabled'; endif; ?>
                                    >
                                    <span x-show="saving" x-cloak class="text-base text-slate-400"><?php echo e(__('app.runs.saving')); ?></span>
                                </div>
                            <?php elseif($item->response_type === ResponseType::Text): ?>
                                <div class="mt-3 sm:pl-16">
                                    <textarea
                                        rows="2"
                                        class="input w-full text-lg"
                                        aria-label="<?php echo e(__('app.runs.value_text')); ?> — <?php echo e($item->description); ?>"
                                        x-on:change="saving = true; $wire.setText(<?php echo e($item->id); ?>, $event.target.value, '<?php echo e($status->value); ?>').finally(() => saving = false)"
                                        <?php if(! $isEditable): echo 'disabled'; endif; ?>
                                    ><?php echo e($item->value_text); ?></textarea>
                                    <span x-show="saving" x-cloak class="text-base text-slate-400"><?php echo e(__('app.runs.saving')); ?></span>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['value.'.$item->id];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <p class="mt-1 text-base text-rose-400 sm:pl-16"><?php echo e($message); ?></p>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>
                        <button
                            type="button"
                            class="flex min-h-20 w-24 shrink-0 flex-col items-center justify-center gap-0.5 rounded-2xl border-2 border-slate-600 bg-slate-800 px-2 text-base font-semibold text-slate-200 active:bg-slate-700 disabled:opacity-60"
                            wire:click="openActions(<?php echo e($item->id); ?>, '<?php echo e($status->value); ?>')"
                            wire:loading.attr="disabled"
                            aria-label="<?php echo e(__('app.runs.item_options_for', ['number' => $item->sort_order])); ?>"
                        >
                            <span><?php echo e(__('app.item_status.not_applicable')); ?></span>
                            <span class="text-slate-500" aria-hidden="true">·</span>
                            <span><?php echo e(__('app.runs.fail')); ?></span>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->attachments->isNotEmpty()): ?>
                    <div class="mt-2 flex flex-wrap gap-4 sm:pl-16">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $item->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="relative" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'item-photo-'.e($photo->id).''; ?>wire:key="item-photo-<?php echo e($photo->id); ?>">
                                <img src="<?php echo e($photo->url); ?>" alt="<?php echo e($photo->original_name ?? __('app.runs.photo')); ?>"
                                    class="h-28 w-28 rounded-xl border border-slate-700 object-cover">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>
                                    <button type="button"
                                        class="absolute -right-3 -top-3 flex h-14 w-14 items-center justify-center rounded-full bg-rose-600 text-2xl font-bold text-white shadow"
                                        wire:click="removeAttachment(<?php echo e($photo->id); ?>)"
                                        wire:loading.attr="disabled"
                                        aria-label="<?php echo e(__('app.runs.remove_photo')); ?>">&times;</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['itemPhotos.'.$item->id];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-1 text-base text-rose-400 sm:pl-16"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </ol>

    

    <section class="card mt-8 p-5">
        <h2 class="text-xl font-bold"><?php echo e(__('app.runs.used_parts')); ?></h2>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->runParts->isEmpty()): ?>
            <p class="mt-3 text-lg text-slate-400"><?php echo e(__('app.parts.no_parts')); ?></p>
        <?php else: ?>
            <ul class="mt-2 divide-y divide-slate-800">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $run->runParts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $part): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'part-'.e($part->id).''; ?>wire:key="part-<?php echo e($part->id); ?>" class="flex min-h-20 flex-wrap items-center justify-between gap-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-semibold leading-snug"><?php echo e($part->part_name_snapshot); ?></p>
                            <p class="text-base text-slate-400">
                                <?php echo e(__('app.parts.part_code')); ?>: <?php echo e($part->part_code_snapshot); ?>

                                <span wire:loading wire:target="qty.<?php echo e($part->id); ?>" class="text-slate-300">· <?php echo e(__('app.runs.saving')); ?></span>
                            </p>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>
                            <?php if (isset($component)) { $__componentOriginal253a0e595a31cdb6ce08aeb4dfd5ebda = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal253a0e595a31cdb6ce08aeb4dfd5ebda = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stepper','data' => ['wire:model.live' => 'qty.'.e($part->id).'','label' => $part->part_name_snapshot,'min' => 0,'step' => 1]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stepper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:model.live' => 'qty.'.e($part->id).'','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($part->part_name_snapshot),'min' => 0,'step' => 1]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal253a0e595a31cdb6ce08aeb4dfd5ebda)): ?>
<?php $attributes = $__attributesOriginal253a0e595a31cdb6ce08aeb4dfd5ebda; ?>
<?php unset($__attributesOriginal253a0e595a31cdb6ce08aeb4dfd5ebda); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal253a0e595a31cdb6ce08aeb4dfd5ebda)): ?>
<?php $component = $__componentOriginal253a0e595a31cdb6ce08aeb4dfd5ebda; ?>
<?php unset($__componentOriginal253a0e595a31cdb6ce08aeb4dfd5ebda); ?>
<?php endif; ?>
                        <?php else: ?>
                            <p class="text-2xl font-bold tabular-nums"><?php echo e($part->qty_used); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    

    <section class="card mt-8 p-5">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-bold"><?php echo e(__('app.runs.notes')); ?></h2>
            <p class="text-base" aria-live="polite">
                <span class="text-slate-400" wire:loading wire:target="notes"><?php echo e(__('app.runs.saving')); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($notesSaved): ?>
                    <span class="font-semibold text-emerald-400" wire:loading.remove wire:target="notes"><?php echo e(__('app.runs.autosaved')); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
        </div>
        <textarea
            wire:model.live.debounce.1000ms="notes"
            rows="4"
            maxlength="5000"
            class="input mt-3 w-full text-lg"
            placeholder="<?php echo e(__('app.runs.notes_placeholder')); ?>"
            aria-label="<?php echo e(__('app.runs.notes')); ?>"
            <?php if(! $isEditable): echo 'disabled'; endif; ?>
        ></textarea>
    </section>

    

    <section class="card mt-8 p-5">
        <h2 class="text-xl font-bold"><?php echo e(__('app.runs.run_photos')); ?></h2>
        <p class="mt-1 text-base text-slate-400"><?php echo e(__('app.runs.run_photos_hint')); ?></p>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->attachments->isNotEmpty()): ?>
            <div class="mt-4 flex flex-wrap gap-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $run->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="relative" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'run-photo-'.e($photo->id).''; ?>wire:key="run-photo-<?php echo e($photo->id); ?>">
                        <img src="<?php echo e($photo->url); ?>" alt="<?php echo e($photo->original_name ?? __('app.runs.photo')); ?>"
                            class="h-28 w-28 rounded-xl border border-slate-700 object-cover">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>
                            <button type="button"
                                class="absolute -right-3 -top-3 flex h-14 w-14 items-center justify-center rounded-full bg-rose-600 text-2xl font-bold text-white shadow"
                                wire:click="removeAttachment(<?php echo e($photo->id); ?>)"
                                wire:loading.attr="disabled"
                                aria-label="<?php echo e(__('app.runs.remove_photo')); ?>">&times;</button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>
            <label class="mt-4 flex min-h-14 w-full cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 px-4 text-lg font-semibold text-slate-300 active:bg-slate-800">
                <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                </svg>
                <?php echo e(__('app.runs.add_photo')); ?>

                <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="runPhoto">
            </label>
            <p class="mt-2 text-base text-slate-400" wire:loading wire:target="runPhoto"><?php echo e(__('app.runs.photo_uploading')); ?></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['runPhoto'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-2 text-base text-rose-400"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->operator_signature_path || in_array($run->status, [RunStatus::Submitted, RunStatus::Approved, RunStatus::Rejected], true)): ?>
        <section class="card mt-8 p-5">
            <h2 class="text-xl font-bold"><?php echo e(__('app.runs.signoff')); ?></h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <?php if (isset($component)) { $__componentOriginalb36e88a294f8ac6fcc188090a374886f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb36e88a294f8ac6fcc188090a374886f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.signature-block','data' => ['label' => __('app.runs.operator_signature'),'user' => $run->operator,'path' => $run->operator_signature_path,'signedAt' => $run->operator_signed_at]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('signature-block'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('app.runs.operator_signature')),'user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->operator),'path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->operator_signature_path),'signed-at' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->operator_signed_at)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb36e88a294f8ac6fcc188090a374886f)): ?>
<?php $attributes = $__attributesOriginalb36e88a294f8ac6fcc188090a374886f; ?>
<?php unset($__attributesOriginalb36e88a294f8ac6fcc188090a374886f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb36e88a294f8ac6fcc188090a374886f)): ?>
<?php $component = $__componentOriginalb36e88a294f8ac6fcc188090a374886f; ?>
<?php unset($__componentOriginalb36e88a294f8ac6fcc188090a374886f); ?>
<?php endif; ?>

                <?php if (isset($component)) { $__componentOriginalb36e88a294f8ac6fcc188090a374886f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb36e88a294f8ac6fcc188090a374886f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.signature-block','data' => ['label' => __('app.runs.supervisor_signature'),'user' => $run->supervisor,'path' => $run->supervisor_signature_path,'signedAt' => $run->supervisor_signed_at,'note' => $run->supervisor_comment]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('signature-block'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('app.runs.supervisor_signature')),'user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->supervisor),'path' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->supervisor_signature_path),'signed-at' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->supervisor_signed_at),'note' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($run->supervisor_comment)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb36e88a294f8ac6fcc188090a374886f)): ?>
<?php $attributes = $__attributesOriginalb36e88a294f8ac6fcc188090a374886f; ?>
<?php unset($__attributesOriginalb36e88a294f8ac6fcc188090a374886f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb36e88a294f8ac6fcc188090a374886f)): ?>
<?php $component = $__componentOriginalb36e88a294f8ac6fcc188090a374886f; ?>
<?php unset($__componentOriginalb36e88a294f8ac6fcc188090a374886f); ?>
<?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->status === RunStatus::Submitted): ?>
                <p class="mt-4 text-base text-slate-400"><?php echo e(__('app.runs.awaiting_supervisor')); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>
        <section class="mt-8">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showMissing && $run->missing_required_items->isNotEmpty()): ?>
                <div class="mb-4 rounded-2xl border-2 border-rose-500 bg-rose-950/40 p-5" role="alert">
                    <p class="text-lg font-bold text-rose-200"><?php echo e(__('app.runs.cannot_submit_incomplete')); ?></p>
                    <ul class="mt-3 space-y-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $run->missing_required_items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $missing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'missing-'.e($missing->id).''; ?>wire:key="missing-<?php echo e($missing->id); ?>">
                                
                                <button type="button"
                                    class="flex min-h-14 w-full items-center gap-3 rounded-xl border border-rose-500/50 bg-rose-950/40 px-4 text-left text-lg text-rose-100 active:bg-rose-900/40"
                                    x-on:click="document.getElementById('run-item-<?php echo e($missing->id); ?>')?.scrollIntoView({ behavior: 'smooth', block: 'center' })">
                                    <span class="font-bold tabular-nums">#<?php echo e($missing->sort_order); ?></span>
                                    <span class="min-w-0 flex-1 truncate"><?php echo e($missing->description); ?></span>
                                </button>
                            </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['size' => 'kiosk','class' => 'w-full text-2xl','wire:click' => 'attemptSubmit','wire:target' => 'attemptSubmit','wire:loading.attr' => 'disabled']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'kiosk','class' => 'w-full text-2xl','wire:click' => 'attemptSubmit','wire:target' => 'attemptSubmit','wire:loading.attr' => 'disabled']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <?php echo e(__('app.runs.submit_run')); ?>

             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $attributes = $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561)): ?>
<?php $component = $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561; ?>
<?php unset($__componentOriginald0f1fd2689e4bb7060122a5b91fe8561); ?>
<?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditable): ?>

        
        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'item-actions','title' => $actionItem !== null ? __('app.runs.item_options_title', ['number' => $actionItem->sort_order]) : __('app.runs.na_fail')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'item-actions','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actionItem !== null ? __('app.runs.item_options_title', ['number' => $actionItem->sort_order]) : __('app.runs.na_fail'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionItem !== null): ?>
                <p class="text-lg leading-snug text-slate-200 [.kiosk_&]:text-slate-200"><?php echo e($actionItem->description); ?></p>

                <div class="mt-5 space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionItem->status !== RunItemStatus::NotApplicable): ?>
                        <button type="button"
                            class="flex min-h-14 w-full items-center justify-center rounded-xl border-2 border-slate-500 text-lg font-bold text-slate-100 active:bg-slate-800"
                            wire:click="markNotApplicable" wire:loading.attr="disabled">
                            <?php echo e(__('app.runs.mark_not_applicable')); ?>

                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <button type="button"
                        class="flex min-h-14 w-full items-center justify-center rounded-xl border-2 border-rose-500 bg-rose-600/20 text-lg font-bold text-rose-200 active:bg-rose-600/40"
                        wire:click="openFailForm" wire:loading.attr="disabled">
                        <?php echo e(__('app.runs.mark_failed')); ?>

                    </button>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionItem->status !== RunItemStatus::Pending): ?>
                        <button type="button"
                            class="flex min-h-14 w-full items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                            wire:click="undoItem" wire:loading.attr="disabled">
                            <?php echo e(__('app.runs.undo_to_pending')); ?>

                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <label class="flex min-h-14 w-full cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800">
                        <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                        <?php echo e(__('app.runs.add_photo')); ?>

                        <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="itemPhotos.<?php echo e($actionItem->id); ?>">
                    </label>
                    <p class="text-base text-slate-400" wire:loading wire:target="itemPhotos.<?php echo e($actionItem->id); ?>"><?php echo e(__('app.runs.photo_uploading')); ?></p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['itemPhotos.'.$actionItem->id];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-base text-rose-400"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>

        
        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'item-fail','title' => __('app.runs.mark_failed_title')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'item-fail','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('app.runs.mark_failed_title'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($actionItem !== null): ?>
                <p class="text-lg leading-snug text-slate-200"><?php echo e($actionItem->description); ?></p>

                <label for="fail-reason" class="mt-4 block text-lg font-semibold"><?php echo e(__('app.runs.fail_reason')); ?></label>
                <textarea id="fail-reason" wire:model="failReason" rows="3" maxlength="500" class="input mt-2 w-full text-lg"></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['failReason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-1 text-base text-rose-400"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php
                    $needsPhoto = (bool) ($actionItem->templateItem?->requires_photo_on_fail ?? false);
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($needsPhoto): ?>
                    <p class="mt-3 text-base font-semibold text-amber-300"><?php echo e(__('app.runs.photo_required_on_fail')); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <label class="mt-3 flex min-h-14 w-full cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800">
                    <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                    </svg>
                    <?php echo e(__('app.runs.add_photo')); ?>

                    <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="failPhoto">
                </label>
                <p class="mt-2 text-base text-slate-400" wire:loading wire:target="failPhoto"><?php echo e(__('app.runs.photo_uploading')); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($failPhoto): ?>
                    <p class="mt-2 text-base text-slate-300"><?php echo e($failPhoto->getClientOriginalName()); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['failPhoto'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-1 text-base text-rose-400"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="mt-6 flex gap-3">
                    <button type="button"
                        class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                        x-on:click="show = false">
                        <?php echo e(__('app.actions.cancel')); ?>

                    </button>
                    <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['variant' => 'danger','class' => 'min-h-14 flex-1 text-lg','wire:click' => 'confirmFail','wire:target' => 'confirmFail','wire:loading.attr' => 'disabled']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'danger','class' => 'min-h-14 flex-1 text-lg','wire:click' => 'confirmFail','wire:target' => 'confirmFail','wire:loading.attr' => 'disabled']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                        <?php echo e(__('app.runs.confirm_fail')); ?>

                     <?php echo $__env->renderComponent(); ?>
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
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>

        
        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'item-issue','title' => __('app.runs.issue_title')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'item-issue','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('app.runs.issue_title'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <p class="text-lg text-slate-200"><?php echo e(__('app.runs.raise_issue_prompt')); ?></p>

            <label for="issue-description" class="mt-4 block text-lg font-semibold"><?php echo e(__('app.runs.issue_description_label')); ?></label>
            <textarea id="issue-description" wire:model="issueDescription" rows="4" maxlength="2000" class="input mt-2 w-full text-lg"></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['issueDescription'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-1 text-base text-rose-400"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <p class="mt-4 text-lg font-semibold"><?php echo e(__('app.issues.severity')); ?></p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = IssueSeverity::cases(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $severity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button"
                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'severity-'.e($severity->value).''; ?>wire:key="severity-<?php echo e($severity->value); ?>"
                        wire:click="$set('issueSeverity', '<?php echo e($severity->value); ?>')"
                        class="min-h-14 rounded-xl border-2 text-lg font-bold transition-colors <?php echo e($issueSeverity === $severity->value ? 'border-amber-400 bg-amber-400/20 text-white' : 'border-slate-600 text-slate-300'); ?>"
                        aria-pressed="<?php echo e($issueSeverity === $severity->value ? 'true' : 'false'); ?>">
                        <?php echo e($severity->label()); ?>

                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['issueSeverity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-1 text-base text-rose-400"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="mt-6 flex gap-3">
                <button type="button"
                    class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                    wire:click="skipIssue">
                    <?php echo e(__('app.runs.issue_skip')); ?>

                </button>
                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['class' => 'min-h-14 flex-1 text-lg','wire:click' => 'raiseIssue','wire:target' => 'raiseIssue','wire:loading.attr' => 'disabled']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'min-h-14 flex-1 text-lg','wire:click' => 'raiseIssue','wire:target' => 'raiseIssue','wire:loading.attr' => 'disabled']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <?php echo e(__('app.runs.issue_raise')); ?>

                 <?php echo $__env->renderComponent(); ?>
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
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>

        
        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'confirm-submit','title' => __('app.runs.confirm_submit_title'),'maxWidth' => 'xl','xData' => '{ signature: \'\', confirmation: \'\', busy: false }']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'confirm-submit','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('app.runs.confirm_submit_title')),'max-width' => 'xl','x-data' => '{ signature: \'\', confirmation: \'\', busy: false }']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <p class="text-lg text-slate-200"><?php echo e(__('app.runs.confirm_submit_body')); ?></p>

            <dl class="mt-4 space-y-1 text-lg">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-400"><?php echo e(__('app.runs.machine')); ?></dt>
                    <dd class="font-semibold"><?php echo e($run->machine->name); ?></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-400"><?php echo e(__('app.runs.template')); ?></dt>
                    <dd class="font-semibold"><?php echo e($run->template->name); ?></dd>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->shift->isSplit()): ?>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400"><?php echo e(__('app.runs.shift')); ?></dt>
                        <dd class="font-semibold"><?php echo e($run->shift->label()); ?></dd>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-400"><?php echo e(__('app.common.status')); ?></dt>
                    <dd class="font-semibold"><?php echo e(__('app.runs.progress', ['done' => $progress['done'], 'total' => $progress['total']])); ?></dd>
                </div>
            </dl>

            
            <div class="mt-6">
                <?php if (isset($component)) { $__componentOriginal72332feea9f878ab2343bb6e35d6719d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal72332feea9f878ab2343bb6e35d6719d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.signature-pad','data' => ['xModel' => 'signature','label' => __('app.runs.operator_signature'),'hint' => __('app.runs.signature_hint')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('signature-pad'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['x-model' => 'signature','label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('app.runs.operator_signature')),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('app.runs.signature_hint'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal72332feea9f878ab2343bb6e35d6719d)): ?>
<?php $attributes = $__attributesOriginal72332feea9f878ab2343bb6e35d6719d; ?>
<?php unset($__attributesOriginal72332feea9f878ab2343bb6e35d6719d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal72332feea9f878ab2343bb6e35d6719d)): ?>
<?php $component = $__componentOriginal72332feea9f878ab2343bb6e35d6719d; ?>
<?php unset($__componentOriginal72332feea9f878ab2343bb6e35d6719d); ?>
<?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['signature'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-2 text-base text-rose-400"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <?php
                $method = $this->confirmationMethod;
            ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($method !== 'none'): ?>
                <div class="mt-6">
                    <label for="sign-confirmation" class="block text-lg font-semibold">
                        <?php echo e($method === 'pin' ? __('app.runs.confirm_with_pin') : __('app.runs.confirm_with_password')); ?>

                    </label>
                    <p class="mt-1 text-base text-slate-400">
                        <?php echo e(__('app.runs.confirm_identity_hint', ['name' => auth()->user()->full_name])); ?>

                    </p>
                    <input
                        id="sign-confirmation"
                        type="password"
                        <?php if($method === 'pin'): ?> inputmode="numeric" autocomplete="one-time-code" <?php else: ?> autocomplete="current-password" <?php endif; ?>
                        class="input mt-2 min-h-14 w-full text-center text-2xl tracking-[0.5em]"
                        x-model="confirmation"
                        x-on:keydown.enter.prevent="if (signature && ! busy) { busy = true; $wire.submit(signature, confirmation).finally(() => { confirmation = ''; busy = false }) }"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-2 text-base text-rose-400"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="mt-6 flex gap-3">
                <button type="button"
                    class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                    x-on:click="confirmation = ''; show = false">
                    <?php echo e(__('app.actions.cancel')); ?>

                </button>
                <?php if (isset($component)) { $__componentOriginald0f1fd2689e4bb7060122a5b91fe8561 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald0f1fd2689e4bb7060122a5b91fe8561 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['class' => 'min-h-14 flex-1 text-lg','xBind:disabled' => '! signature || busy','xOn:click' => 'busy = true; $wire.submit(signature, confirmation).finally(() => { confirmation = \'\'; busy = false })']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'min-h-14 flex-1 text-lg','x-bind:disabled' => '! signature || busy','x-on:click' => 'busy = true; $wire.submit(signature, confirmation).finally(() => { confirmation = \'\'; busy = false })']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <span x-show="! busy"><?php echo e(__('app.runs.sign_and_submit')); ?></span>
                    <span x-show="busy" x-cloak><?php echo e(__('app.runs.saving')); ?></span>
                 <?php echo $__env->renderComponent(); ?>
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
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/livewire/runs/run-form.blade.php ENDPATH**/ ?>