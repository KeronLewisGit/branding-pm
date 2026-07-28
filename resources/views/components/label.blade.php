@props(['for' => null, 'required' => false])

<label @if ($for) for="{{ $for }}" @endif {{ $attributes->merge(['class' => 'label']) }}>
    {{ $slot }}@if ($required)<span class="text-rose-600" aria-hidden="true"> *</span><span class="sr-only"> ({{ __('app.form.required') }})</span>@endif
</label>
