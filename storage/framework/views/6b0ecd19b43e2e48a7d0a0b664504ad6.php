
<?php
    $items = $run->items->sortBy('sort_order')->values();
    // Two columns, filled down the left then the right, exactly as the
    // printed form reads.
    $half = (int) ceil($items->count() / 2);
    $left = $items->slice(0, $half);
    $right = $items->slice($half);

    $statusLabel = fn ($item) => $item->status->label();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($run->template->name); ?> — <?php echo e($run->machine->name); ?></title>
    <style>
        @page { margin: 14mm 12mm 18mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #0f172a; }
        h1 { font-size: 13pt; margin: 0 0 2mm; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 1mm 2mm; border: 0.4pt solid #94a3b8; vertical-align: top; }
        .meta .label { width: 22%; background: #f1f5f9; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.4pt; color: #475569; }
        .section { margin-top: 5mm; }
        .section-title { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.6pt; color: #475569; border-bottom: 0.8pt solid #0f172a; padding-bottom: 1mm; margin-bottom: 2mm; }
        .tasks td { vertical-align: top; width: 50%; padding-right: 3mm; }
        .task { border-bottom: 0.3pt solid #cbd5e1; padding: 1.2mm 0; }
        .task .no { display: inline-block; width: 6mm; font-weight: bold; }
        .task .state { float: right; font-size: 7.5pt; font-weight: bold; }
        .state-failed { color: #b91c1c; }
        .state-na { color: #64748b; }
        .reason { display: block; margin: 0.5mm 0 0 6mm; color: #b91c1c; font-size: 8pt; }
        .value { display: block; margin: 0.5mm 0 0 6mm; color: #334155; font-size: 8pt; }
        .parts th, .parts td { border: 0.4pt solid #94a3b8; padding: 1mm 2mm; text-align: left; }
        .parts th { background: #f1f5f9; font-size: 7.5pt; text-transform: uppercase; color: #475569; }
        .parts .qty { text-align: right; width: 18mm; }
        .notes { border: 0.4pt solid #94a3b8; min-height: 18mm; padding: 2mm; }
        .sign td { width: 50%; padding: 0 3mm 0 0; vertical-align: top; }
        .sign-box { border: 0.4pt solid #94a3b8; height: 26mm; padding: 1.5mm; text-align: center; }
        .sign-box img { max-height: 20mm; max-width: 100%; }
        .sign-none { color: #94a3b8; font-size: 8pt; padding-top: 8mm; }
        .sign-name { margin-top: 1mm; font-size: 8.5pt; }
        .sign-name strong { display: block; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #64748b; border-top: 0.3pt solid #cbd5e1; padding-top: 1.5mm; }
        .footer .right { float: right; }
    </style>
</head>
<body>

    <h1><?php echo e($run->template->name); ?></h1>
    <p style="margin:0 0 3mm; font-size:9pt;">
        <?php echo e($run->machine->location->site?->name); ?> · <?php echo e(__('app.runs.run')); ?> #<?php echo e($run->id); ?>

    </p>

    
    <table class="meta">
        <tr>
            <td class="label"><?php echo e(__('app.kiosk.equipment')); ?></td>
            <td><?php echo e($run->machine->name); ?> (<?php echo e($run->machine->code); ?>)</td>
            <td class="label"><?php echo e(__('app.runs.scheduled_for')); ?></td>
            <td><?php echo e($run->scheduled_for->format('D, j M Y')); ?></td>
        </tr>
        <tr>
            <td class="label"><?php echo e(__('app.locations.location')); ?></td>
            <td><?php echo e($run->machine->location->name); ?></td>
            <td class="label"><?php echo e(__('app.runs.shift')); ?></td>
            <td><?php echo e($run->display_shift); ?></td>
        </tr>
        <tr>
            <td class="label"><?php echo e(__('app.locations.building')); ?></td>
            <td><?php echo e($run->machine->location->site?->name); ?><?php echo e($run->machine->location->floor ? ' · '.$run->machine->location->floor : ''); ?></td>
            <td class="label"><?php echo e(__('app.templates.work_category')); ?></td>
            <td><?php echo e($run->template->work_category->label()); ?></td>
        </tr>
        <tr>
            <td class="label"><?php echo e(__('app.templates.work_description')); ?></td>
            <td colspan="3"><?php echo e($run->template->work_description); ?></td>
        </tr>
        <tr>
            <td class="label"><?php echo e(__('app.common.status')); ?></td>
            <td><?php echo e($run->status->label()); ?></td>
            <td class="label"><?php echo e(__('app.runs.submitted_at')); ?></td>
            <td><?php echo e($run->submitted_at?->timezone($displayTz)->format('j M Y, g:i A') ?? '—'); ?></td>
        </tr>
    </table>

    
    <div class="section">
        <div class="section-title"><?php echo e(__('app.templates.items')); ?></div>
        <table class="tasks">
            <tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [$left, $right]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $column; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="task">
                                <span class="state <?php echo e($item->status->value === 'failed' ? 'state-failed' : ($item->status->value === 'not_applicable' ? 'state-na' : '')); ?>">
                                    <?php echo e($statusLabel($item)); ?>

                                </span>
                                <span class="no"><?php echo e($item->sort_order); ?>.</span><?php echo e($item->description); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->fail_reason): ?>
                                    <span class="reason"><?php echo e($item->fail_reason); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->value_numeric !== null): ?>
                                    <span class="value"><?php echo e(__('app.runs.value_numeric')); ?>: <?php echo e($item->value_numeric); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->value_text): ?>
                                    <span class="value"><?php echo e($item->value_text); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </tr>
        </table>
    </div>

    
    <div class="section">
        <div class="section-title"><?php echo e(__('app.runs.used_parts')); ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->runParts->isEmpty()): ?>
            <p style="margin:0; color:#64748b;"><?php echo e(__('app.parts.no_parts')); ?></p>
        <?php else: ?>
            <table class="parts">
                <tr>
                    <th style="width:22mm;"><?php echo e(__('app.parts.part_code')); ?></th>
                    <th><?php echo e(__('app.parts.part')); ?></th>
                    <th class="qty"><?php echo e(__('app.runs.qty_used')); ?></th>
                </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $run->runParts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $part): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr>
                        <td><?php echo e($part->part_code_snapshot); ?></td>
                        <td><?php echo e($part->part_name_snapshot); ?></td>
                        <td class="qty"><?php echo e($part->qty_used); ?></td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </table>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="section">
        <div class="section-title"><?php echo e(__('app.runs.notes')); ?></div>
        <div class="notes"><?php echo e($run->notes); ?></div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($run->supervisor_comment): ?>
        <div class="section">
            <div class="section-title"><?php echo e(__('app.runs.supervisor_comment')); ?></div>
            <div class="notes"><?php echo e($run->supervisor_comment); ?></div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="section">
        <table class="sign">
            <tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    ['label' => __('app.runs.operator_signature'), 'image' => $operatorSignature, 'user' => $run->operator, 'at' => $run->operator_signed_at],
                    ['label' => __('app.runs.supervisor_signature'), 'image' => $supervisorSignature, 'user' => $run->supervisor, 'at' => $run->supervisor_signed_at],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $block): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <td>
                        <div class="section-title"><?php echo e($block['label']); ?></div>
                        <div class="sign-box">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($block['image']): ?>
                                <img src="<?php echo e($block['image']); ?>" alt="">
                            <?php else: ?>
                                <div class="sign-none"><?php echo e(__('app.runs.not_signed')); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="sign-name">
                            <strong><?php echo e($block['user']?->full_name ?? '—'); ?></strong>
                            <?php echo e($block['user']?->employee_number ? '#'.$block['user']->employee_number : ''); ?>

                            <?php echo e($block['at'] ? '· '.$block['at']->timezone($displayTz)->format('j M Y, g:i A') : ''); ?>

                        </div>
                    </td>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </tr>
        </table>
    </div>

    <div class="footer">
        <span class="right"><?php echo e(__('app.reports.generated_at', ['at' => $generatedAt->timezone($displayTz)->format('j M Y, g:i A')])); ?></span>
        <?php echo e(__('app.runs.run')); ?> #<?php echo e($run->id); ?>

        · <?php echo e(__('app.reports.verification')); ?> <strong><?php echo e($verification); ?></strong>
    </div>

</body>
</html>
<?php /**PATH /var/www/html/resources/views/pdf/run.blade.php ENDPATH**/ ?>