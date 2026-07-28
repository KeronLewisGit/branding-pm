{{--
    Checkbox. With slot text it renders a full-width 56px labelled tap target.
    Without a slot it renders the bare 28px input — the caller then MUST wrap
    it in its own >=56px tap target (e.g. a .tap-row) itself.
--}}
@props(['id' => null])

@php($id = $id ?? $attributes->get('id') ?? 'checkbox-' . uniqid())

@if ($slot->isEmpty())
    <input type="checkbox" id="{{ $id }}" {{ $attributes->except('id')->merge(['class' => 'h-7 w-7 shrink-0 rounded-md border-slate-400 text-sky-600 focus:ring-sky-500']) }}>
@else
    <label for="{{ $id }}" class="flex min-h-14 cursor-pointer select-none items-center gap-3">
        <input type="checkbox" id="{{ $id }}" {{ $attributes->except('id')->merge(['class' => 'h-7 w-7 shrink-0 rounded-md border-slate-400 text-sky-600 focus:ring-sky-500']) }}>
        <span class="text-lg">{{ $slot }}</span>
    </label>
@endif
