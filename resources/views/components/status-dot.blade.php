{{--
    Run status indicator: colour dot (contract §8 tokens, defined as
    `status.*` colours in tailwind.config.js) PLUS a text label — status is
    never conveyed by colour alone.

    Accepts a RunStatus enum or its string value:
        <x-status-dot :status="$run->status" />
--}}
@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;

    // Literal class names so Tailwind's JIT compiles them.
    $dotClass = match ($value) {
        'pending' => 'bg-status-pending',
        'in_progress' => 'bg-status-in_progress',
        'submitted' => 'bg-status-submitted',
        'approved' => 'bg-status-approved',
        'rejected' => 'bg-status-rejected',
        'missed' => 'bg-status-missed',
        default => 'bg-slate-400',
    };

    $label = $status instanceof \BackedEnum && method_exists($status, 'label')
        ? $status->label()
        : __('app.status.' . $value);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <span class="status-dot {{ $dotClass }}" aria-hidden="true"></span>
    <span class="font-medium">{{ $label }}</span>
</span>
