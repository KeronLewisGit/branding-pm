{{--
    "You are previewing another role" — and the way out.

    Included by BOTH layouts, deliberately. The sidebar picker is hidden while
    a preview is running, so this button is the only exit; and the run form
    uses the kiosk layout, which has no sidebar at all. A preview you can
    enter from one screen and not leave from another is a trap.

    It renders only when a preview is active, which requires an authenticated
    administrator's session — a real operator at a kiosk never has one, so
    this never appears on the shop floor.
--}}
@if (\App\Support\ViewAs::active())
    <div class="border-b-2 border-amber-400 bg-amber-100 px-4 py-3 sm:px-6 lg:px-8" role="status">
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-base font-bold text-amber-900">
                    {{ __('app.view_as.active', ['role' => __('app.roles.'.\App\Support\ViewAs::role())]) }}
                </p>
                <p class="text-sm text-amber-800">{{ __('app.view_as.active_hint') }}</p>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                {{--
                    Reading a role's walkthrough belongs here rather than in
                    the sidebar: "show me what an operator sees" and "show me
                    what an operator is told on day one" are the same question,
                    and the menu is not the place to answer half of it.
                --}}
                <form method="POST" action="{{ route('walkthrough.preview') }}">
                    @csrf
                    <input type="hidden" name="role" value="{{ \App\Support\ViewAs::role() }}">
                    <x-button type="submit" variant="ghost"
                              class="!min-h-11 border-amber-400 !text-base text-amber-900 hover:bg-amber-200">
                        {{ __('app.walkthrough.preview_from_banner') }}
                    </x-button>
                </form>

                <form method="POST" action="{{ route('view-as.stop') }}">
                    @csrf
                    <x-button type="submit" class="!min-h-11 !text-base">
                        {{ __('app.view_as.stop') }}
                    </x-button>
                </form>
            </div>
        </div>
    </div>
@endif
