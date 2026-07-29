
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e($title ?? config('app.name', 'Branding PM')); ?></title>

    
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#020617">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body
    class="kiosk flex min-h-full flex-col bg-slate-950 font-sans text-lg text-slate-100 antialiased"
    x-data="idleRelease(120, '<?php echo e(route('kiosk.release')); ?>')"
>
    
    <header data-nav-chrome class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900">
        <div class="flex min-h-20 items-center justify-between gap-4 px-4 py-2 sm:px-6">
            <div class="min-w-0 flex-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($header)): ?>
                    <?php echo e($header); ?>

                <?php else: ?>
                    <p class="truncate text-2xl font-bold text-white"><?php echo e(config('app.name', 'Branding PM')); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <?php ($displayTz = config('app.display_timezone', 'UTC')); ?>
            <?php ($jsLocale = str_replace('_', '-', app()->getLocale())); ?>
            <div
                class="shrink-0 text-right tabular-nums"
                x-data="{
                    time: '',
                    date: '',
                    tick() {
                        const now = new Date();
                        this.time = new Intl.DateTimeFormat('<?php echo e($jsLocale); ?>', {
                            hour: 'numeric', minute: '2-digit', hour12: true, timeZone: '<?php echo e($displayTz); ?>',
                        }).format(now);
                        this.date = new Intl.DateTimeFormat('<?php echo e($jsLocale); ?>', {
                            weekday: 'short', day: 'numeric', month: 'short', timeZone: '<?php echo e($displayTz); ?>',
                        }).format(now);
                    },
                }"
                x-init="tick(); setInterval(() => tick(), 1000)"
            >
                <p class="text-3xl font-bold leading-tight text-white" x-text="time"><?php echo e(now($displayTz)->format('g:i A')); ?></p>
                <p class="text-base text-slate-300" x-text="date"><?php echo e(now($displayTz)->format('D j M')); ?></p>
            </div>
        </div>
    </header>

    
    <div x-data="connectionStatus" x-cloak>
        <div x-show="! online || queued > 0"
             class="sticky top-20 z-40 flex items-center gap-3 border-b border-amber-500 bg-amber-950/90 px-4 py-3 text-amber-100 backdrop-blur sm:px-6"
             role="status" aria-live="polite">
            <svg class="h-7 w-7 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75h.008v.008H12v-.008zM3.98 8.223a13.5 13.5 0 0116.04 0M6.62 11.1a9.5 9.5 0 0110.76 0M9.26 13.98a5.5 5.5 0 015.48 0" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
            </svg>
            <p class="flex-1 text-lg font-semibold">
                <span x-show="! online"><?php echo e(__('app.offline.working_offline')); ?></span>
                <span x-show="online && queued > 0"><?php echo e(__('app.offline.syncing')); ?></span>
                <span x-show="queued > 0" class="block text-base font-normal"
                      x-text="`<?php echo e(__('app.offline.queued_count')); ?>`.replace(':count', queued)"></span>
            </p>
        </div>

        
        <div x-show="stranded > 0"
             class="sticky top-20 z-40 border-b-2 border-rose-500 bg-rose-950/95 px-4 py-3 text-rose-100 sm:px-6"
             role="alert">
            <p class="text-lg font-bold"><?php echo e(__('app.offline.stranded_title')); ?></p>
            <p class="mt-1 text-base" x-text="`<?php echo e(__('app.offline.stranded_body')); ?>`.replace(':count', stranded)"></p>
            <button type="button"
                    class="mt-2 min-h-14 rounded-xl border-2 border-rose-400 px-5 text-base font-semibold"
                    x-on:click="discardStranded()">
                <?php echo e(__('app.offline.stranded_dismiss')); ?>

            </button>
        </div>
    </div>

    <main class="flex-1 px-4 py-6 sm:px-6">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'success','class' => 'mb-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'success','class' => 'mb-6']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e(session('status')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
            <?php if (isset($component)) { $__componentOriginal5194778a3a7b899dcee5619d0610f5cf = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.alert','data' => ['type' => 'error','class' => 'mb-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'error','class' => 'mb-6']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e(session('error')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $attributes = $__attributesOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__attributesOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf)): ?>
<?php $component = $__componentOriginal5194778a3a7b899dcee5619d0610f5cf; ?>
<?php unset($__componentOriginal5194778a3a7b899dcee5619d0610f5cf); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php echo e($slot); ?>

    </main>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/kiosk.blade.php ENDPATH**/ ?>