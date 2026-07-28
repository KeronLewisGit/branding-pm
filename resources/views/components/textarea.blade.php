@props(['rows' => 4])

<textarea rows="{{ $rows }}" {{ $attributes->merge(['class' => 'input min-h-28 py-3']) }}>{{ $slot }}</textarea>
