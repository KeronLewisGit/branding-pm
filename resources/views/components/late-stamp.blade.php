{{--
    "Completed late" stamp for a signed sheet.

        <x-late-stamp :run="$run" />

    Renders nothing unless the sheet was signed after the day it was due.

    There is no stored flag and no editable field behind this: the wording is
    built from `scheduled_for` (set by the generator) and `submitted_at` (the
    server clock at submission), neither of which an operator can reach. That
    is what makes it uneditable — not a permission check that a later screen
    might forget to apply, but the absence of anything to edit.

    Deliberately plain rather than alarming. It records a fact about when the
    work was signed; the sheet is still a completed sheet, and an operator who
    rescues three-week-old work has done the right thing.
--}}
@props(['run'])

@php($lateDays = $run->completedLateByDays())

@if ($lateDays !== null)
    @php($displayTz = config('app.display_timezone', 'UTC'))

    <p {{ $attributes->merge(['class' => 'rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-base font-semibold text-amber-900']) }}>
        {{ __('app.runs.late_stamp', [
            'due' => $run->scheduled_for->format('j M Y'),
            'signed' => $run->submitted_at->timezone($displayTz)->format('j M Y'),
            'days' => $lateDays,
        ]) }}
    </p>
@endif
