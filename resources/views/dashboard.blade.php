{{--
    Deliberate milestone 7 placeholder — no fake numbers. Rendered with
    @component because layouts/app.blade.php is a plain slot-based view
    (Livewire full-page components consume it via #[Layout('layouts.app')]).
--}}
@component('layouts.app')
    @slot('header')
        <x-page-header :title="__('app.dashboard.title')" />
    @endslot

    <x-empty-state
        :title="__('app.dashboard.placeholder_title')"
        :description="__('app.dashboard.placeholder_description')"
    />
@endcomponent
