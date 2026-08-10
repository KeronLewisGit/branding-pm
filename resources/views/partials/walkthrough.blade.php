{{--
    First-run walkthrough: a few cards over the page, once, for whoever just
    signed in.

    Included by BOTH layouts. An operator's first sight of this system is the
    kiosk, which is precisely the person the introduction is for — showing it
    only in the office chrome would miss them entirely.

    Steps are rendered server-side and stepped through in Alpine, so moving
    between cards costs no round trip. Only the dismissal touches the server.
--}}
@php($walkthroughUser = auth()->user())

@if (\App\Support\Walkthrough::shouldShow($walkthroughUser))
    @php($walkthroughRole = \App\Support\Walkthrough::displayRoleFor($walkthroughUser))
    @php($walkthroughPreview = \App\Support\Walkthrough::isPreviewing())
    @php($walkthroughSteps = \App\Support\Walkthrough::stepsFor($walkthroughRole))

    <div
        x-data="{ step: 0, total: {{ count($walkthroughSteps) }} }"
        class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/70 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="walkthrough-title"
        {{-- Escape dismisses, like every other modal in the app. --}}
        x-on:keydown.escape.window="$refs.skip.click()"
    >
        <div class="light-panel w-full max-w-lg rounded-2xl bg-white p-6 text-slate-900 shadow-xl">
            @if ($walkthroughPreview)
                {{-- An administrator is inspecting somebody else's cards. Say
                     so plainly, or it reads as their own introduction. --}}
                <p class="rounded-lg bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-900">
                    {{ __('app.walkthrough.previewing', ['role' => __('app.roles.'.$walkthroughRole)]) }}
                </p>
            @else
                <p class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                    {{ __('app.walkthrough.welcome', ['name' => $walkthroughUser->full_name]) }}
                    <span class="text-slate-400">· {{ __('app.roles.'.$walkthroughRole) }}</span>
                </p>
            @endif

            @foreach ($walkthroughSteps as $index => $walkthroughStep)
                <div x-show="step === {{ $index }}" x-cloak>
                    <h2 id="walkthrough-title" class="mt-3 text-2xl font-bold">{{ $walkthroughStep['title'] }}</h2>
                    <p class="mt-3 text-lg leading-relaxed text-slate-700">{{ $walkthroughStep['body'] }}</p>
                </div>
            @endforeach

            {{-- Progress. Dots alone would not say how many are left. --}}
            <div class="mt-6 flex items-center gap-3">
                <div class="flex gap-1.5" aria-hidden="true">
                    @foreach ($walkthroughSteps as $index => $walkthroughStep)
                        <span class="h-2 w-6 rounded-full transition-colors"
                              x-bind:class="step >= {{ $index }} ? 'bg-sky-600' : 'bg-slate-200'"></span>
                    @endforeach
                </div>
                <p class="text-sm text-slate-500" aria-live="polite"
                   x-text="`{{ __('app.walkthrough.step_of', ['current' => ':c', 'total' => ':t']) }}`.replace(':c', step + 1).replace(':t', total)"></p>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                {{-- Skip and finish do the same thing: somebody who skips has
                     decided they do not need it. --}}
                <form method="POST" action="{{ route('walkthrough.complete') }}">
                    @csrf
                    <x-button type="submit" variant="ghost" x-ref="skip">
                        {{ $walkthroughPreview ? __('app.walkthrough.close_preview') : __('app.walkthrough.skip') }}
                    </x-button>
                </form>

                <div class="flex items-center gap-2">
                    <x-button
                        type="button"
                        variant="ghost"
                        x-show="step > 0"
                        x-cloak
                        x-on:click="step--"
                    >
                        {{ __('app.walkthrough.back') }}
                    </x-button>

                    <x-button
                        type="button"
                        x-show="step < total - 1"
                        x-on:click="step++"
                    >
                        {{ __('app.walkthrough.next') }}
                    </x-button>

                    <form method="POST" action="{{ route('walkthrough.complete') }}" x-show="step === total - 1" x-cloak>
                        @csrf
                        <x-button type="submit">
                            {{ $walkthroughPreview ? __('app.walkthrough.close_preview') : __('app.walkthrough.done') }}
                        </x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
