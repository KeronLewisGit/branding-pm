{{--
    Run completion form — the screen that must beat the paper sheet.

    Layout notes (SPEC §2, contract §8):
    - One scrolling list, numbered by sort_order like the paper form. Never
      paginated, never behind accordions — the whole sheet stays visible,
      even at 16 items (MATAN weekly).
    - Rows ≥ 80px (min-h-20); tap anywhere on a check row = done. The
      "N/A / Fail" button is always visible per row; long-press (Android
      fires `contextmenu`) is only an accelerator for the same sheet.
    - Progress ("7 of 9") is sticky under the kiosk header (top-20 = its
      80px height) so it is reachable without scrolling.
    - Per-row wire:target keeps a slow save on ONE row — the list never
      greys out wholesale.

    Raw PHP in this file is written in the BLOCK form only, deliberately.
    Blade extracts raw PHP with a non-greedy regex that pairs the first
    opening directive with the first closing one, so the parenthesised
    one-line form, placed above a block, is taken as that block's opening tag
    and swallows every line between the two. Do not mix the two forms here.
    (Contract §1.1. Note the literal directives are avoided even in this
    comment — the extraction runs before comments are stripped.)
--}}
@use('App\Enums\ResponseType')
@use('App\Enums\RunItemStatus')
@use('App\Enums\RunStatus')
@use('App\Enums\Shift')
@use('App\Enums\IssueSeverity')

@php
    $isEditable = $this->isEditable;
@endphp

<div class="mx-auto w-full max-w-3xl pb-24">

    {{-- ── Banners ─────────────────────────────────────────────── --}}

    @if ($run->status === RunStatus::Rejected)
        <div class="mb-6 rounded-2xl border-2 border-rose-500 bg-rose-950/50 p-5" role="alert">
            <p class="text-xl font-bold text-rose-200">{{ __('app.runs.rejected_banner') }}</p>
            @if ($run->supervisor_comment)
                <p class="mt-2 text-sm font-semibold uppercase tracking-wide text-rose-400">{{ __('app.runs.supervisor_comment') }}</p>
                <blockquote class="mt-1 border-l-4 border-rose-500 pl-3 text-lg text-rose-100">{{ $run->supervisor_comment }}</blockquote>
            @endif
        </div>
    @endif

    @unless ($isEditable)
        <div class="mb-6 rounded-2xl border-2 border-slate-500 bg-slate-900 p-5">
            <x-status-dot :status="$run->status" class="text-xl" />
            <p class="mt-2 text-lg text-slate-200">{{ __('app.runs.read_only', ['status' => $run->status->label()]) }}</p>
            <p class="mt-1 text-base text-slate-400">{{ __('app.runs.read_only_hint') }}</p>
        </div>
    @endunless

    @if ($conflictNotice)
        <div class="mb-6 flex items-center gap-4 rounded-2xl border-2 border-amber-500 bg-amber-950/50 p-4" role="alert" aria-live="assertive">
            <p class="flex-1 text-lg text-amber-100">{{ $conflictNotice }}</p>
            <button type="button" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-2xl text-amber-300"
                wire:click="$set('conflictNotice', null)" aria-label="{{ __('app.common.close') }}">&times;</button>
        </div>
    @endif

    @if ($notice)
        <div class="mb-6 flex items-center gap-4 rounded-2xl border-2 border-emerald-500 bg-emerald-950/50 p-4" role="status" aria-live="polite">
            <p class="flex-1 text-lg text-emerald-100">{{ $notice }}</p>
            <button type="button" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl text-2xl text-emerald-300"
                wire:click="$set('notice', null)" aria-label="{{ __('app.common.close') }}">&times;</button>
        </div>
    @endif

    {{-- ── Header block — mirrors the paper form ───────────────── --}}

    <section class="card card-body">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-extrabold leading-tight">{{ $run->template->name }}</h1>
                <p class="mt-1 text-lg text-slate-300">
                    {{ $run->machine->name }}
                    <span class="text-slate-500">· {{ $run->machine->code }}</span>
                </p>
            </div>
            <x-status-dot :status="$run->status" class="text-lg" />
        </div>

        {{-- Shift — unmistakable, so a night operator never fills the day sheet --}}
        @if ($run->shift->isSplit())
            @php
                $isNight = $run->shift === Shift::Night;
            @endphp
            <div class="mt-4 flex items-center gap-3 rounded-2xl border-2 px-5 py-4 {{ $isNight ? 'border-indigo-400 bg-indigo-950/70 text-indigo-100' : 'border-amber-400 bg-amber-400/10 text-amber-100' }}">
                @if ($isNight)
                    <svg class="h-9 w-9 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                @else
                    <svg class="h-9 w-9 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                    </svg>
                @endif
                <p class="text-2xl font-extrabold uppercase tracking-wider">
                    {{ __('app.runs.shift_sheet', ['shift' => $run->shift->label()]) }}
                </p>
            </div>
        @endif

        <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-lg sm:grid-cols-2">
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.templates.work_category') }}</dt>
                <dd class="font-semibold">{{ $run->template->work_category->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.runs.scheduled_for') }}</dt>
                <dd class="font-semibold">{{ $run->scheduled_for->translatedFormat('D, j M Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.locations.location') }}</dt>
                <dd>{{ $run->machine->location->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.locations.building') }} / {{ __('app.locations.floor') }}</dt>
                <dd>
                    {{ $run->machine->location->site?->name }}@if ($run->machine->location->floor) · {{ $run->machine->location->floor }}@endif
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.templates.work_description') }}</dt>
                <dd>{{ $run->template->work_description }}</dd>
            </div>
        </dl>
    </section>

    {{-- ── Sticky progress — always reachable without scrolling ── --}}

    <div class="sticky top-20 z-30 -mx-4 mt-6 border-b border-slate-800 bg-slate-950/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-xl font-bold tabular-nums" aria-live="polite">
                {{ __('app.runs.progress', ['done' => $progress['done'], 'total' => $progress['total']]) }}
            </p>
            @if ($run->shift->isSplit())
                <span class="rounded-full border-2 px-3 py-1 text-base font-bold uppercase tracking-wide {{ $run->shift === Shift::Night ? 'border-indigo-400 text-indigo-200' : 'border-amber-400 text-amber-200' }}">
                    {{ $run->shift->label() }}
                </span>
            @endif
        </div>
        @php
            $pct = $progress['total'] > 0 ? (int) round($progress['done'] / $progress['total'] * 100) : 0;
        @endphp
        <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-800" role="progressbar"
            aria-valuemin="0" aria-valuemax="{{ $progress['total'] }}" aria-valuenow="{{ $progress['done'] }}">
            <div class="h-full rounded-full bg-emerald-500 transition-all duration-300" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    @if ($isEditable)
        <p class="mt-4 text-base text-slate-400">{{ __('app.runs.tap_hint') }}</p>
    @endif

    {{-- ── Item list — the core ────────────────────────────────── --}}

    <ol class="mt-4 space-y-3">
        @foreach ($run->items as $item)
            @php
                $status = $item->status;
                $guidance = $item->templateItem?->guidance;
                $rowTone = match ($status) {
                    RunItemStatus::Done => 'border-emerald-500/60 bg-emerald-950/30',
                    RunItemStatus::NotApplicable => 'border-slate-500/70 bg-slate-800/50',
                    RunItemStatus::Failed => 'border-rose-500/70 bg-rose-950/30',
                    default => 'border-slate-700 bg-slate-900',
                };
                // Must be character-identical in wire:click and wire:target
                // so the spinner lights up on THIS row only.
                $rowTarget = "toggleDone({$item->id}, '{$status->value}')";
            @endphp

            <li id="run-item-{{ $item->id }}" wire:key="item-{{ $item->id }}" class="scroll-mt-44">
                <div class="flex items-stretch gap-2">

                    @if ($item->response_type === ResponseType::Check)
                        {{-- Whole row is the tap target: tap = done, tap again = undo --}}
                        <button
                            type="button"
                            class="flex min-h-20 flex-1 items-center gap-4 rounded-2xl border-2 p-4 text-left transition-colors {{ $rowTone }} disabled:opacity-60"
                            wire:click="{{ $rowTarget }}"
                            wire:target="{{ $rowTarget }}"
                            wire:loading.attr="disabled"
                            x-on:contextmenu.prevent="$wire.openActions({{ $item->id }}, '{{ $status->value }}')"
                            aria-pressed="{{ $status === RunItemStatus::Done ? 'true' : 'false' }}"
                            @disabled(! $isEditable)
                        >
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-slate-600 text-xl font-bold tabular-nums">{{ $item->sort_order }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-lg font-semibold leading-snug">{{ $item->description }}</span>
                                @unless ($item->is_required)
                                    <span class="text-sm text-slate-500">{{ __('app.common.optional') }}</span>
                                @endunless
                                @if ($guidance)
                                    <span class="mt-1 block text-base leading-snug text-slate-400">{{ $guidance }}</span>
                                @endif
                                @if ($status === RunItemStatus::Failed && $item->fail_reason)
                                    <span class="mt-1 block text-base font-medium text-rose-300">{{ $item->fail_reason }}</span>
                                @endif
                                @if ($item->completedBy !== null)
                                    <span class="mt-1 block text-sm text-slate-500">{{ __('app.runs.answered_by', ['name' => $item->completedBy->full_name]) }}</span>
                                @endif
                            </span>
                            <span class="ml-2 flex shrink-0 flex-col items-center gap-1">
                                <svg wire:loading wire:target="{{ $rowTarget }}" class="h-9 w-9 animate-spin text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="{{ $rowTarget }}" class="flex flex-col items-center gap-1">
                                    @if ($status === RunItemStatus::Done)
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white">
                                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </span>
                                        <span class="text-sm font-semibold text-emerald-300">{{ $status->label() }}</span>
                                    @elseif ($status === RunItemStatus::NotApplicable)
                                        <span class="rounded-full border-2 border-slate-400 px-3 py-1 text-base font-bold text-slate-200">{{ $status->label() }}</span>
                                    @elseif ($status === RunItemStatus::Failed)
                                        <span class="rounded-full bg-rose-600 px-3 py-1 text-base font-bold text-white">{{ $status->label() }}</span>
                                    @else
                                        <span class="h-10 w-10 rounded-full border-2 border-slate-500" aria-hidden="true"></span>
                                        <span class="sr-only">{{ $status->label() }}</span>
                                    @endif
                                </span>
                            </span>
                        </button>
                    @else
                        {{-- pass_fail / numeric / text — control rendered from the
                             RUN item's snapshotted response_type, never the template --}}
                        <div
                            class="min-h-20 flex-1 rounded-2xl border-2 p-4 {{ $rowTone }}"
                            x-data="{ saving: false }"
                            x-on:contextmenu.prevent="$wire.openActions({{ $item->id }}, '{{ $status->value }}')"
                        >
                            <div class="flex items-start gap-4">
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-slate-600 text-xl font-bold tabular-nums">{{ $item->sort_order }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-lg font-semibold leading-snug">{{ $item->description }}</span>
                                    @unless ($item->is_required)
                                        <span class="text-sm text-slate-500">{{ __('app.common.optional') }}</span>
                                    @endunless
                                    @if ($guidance)
                                        <span class="mt-1 block text-base leading-snug text-slate-400">{{ $guidance }}</span>
                                    @endif
                                    @if ($status === RunItemStatus::Failed && $item->fail_reason)
                                        <span class="mt-1 block text-base font-medium text-rose-300">{{ $item->fail_reason }}</span>
                                    @endif
                                    @if ($item->completedBy !== null)
                                        <span class="mt-1 block text-sm text-slate-500">{{ __('app.runs.answered_by', ['name' => $item->completedBy->full_name]) }}</span>
                                    @endif
                                </span>
                                <span class="ml-2 shrink-0">
                                    @if ($status === RunItemStatus::Done)
                                        <span class="rounded-full bg-emerald-600 px-3 py-1 text-base font-bold text-white">{{ $status->label() }}</span>
                                    @elseif ($status === RunItemStatus::NotApplicable)
                                        <span class="rounded-full border-2 border-slate-400 px-3 py-1 text-base font-bold text-slate-200">{{ $status->label() }}</span>
                                    @elseif ($status === RunItemStatus::Failed)
                                        <span class="rounded-full bg-rose-600 px-3 py-1 text-base font-bold text-white">{{ $status->label() }}</span>
                                    @else
                                        <span class="rounded-full border-2 border-slate-600 px-3 py-1 text-base font-semibold text-slate-400">{{ $status->label() }}</span>
                                    @endif
                                </span>
                            </div>

                            @if ($item->response_type === ResponseType::PassFail)
                                @php
                                    $passTarget = "markPass({$item->id}, '{$status->value}')";
                                @endphp
                                <div class="mt-3 flex gap-3 sm:pl-16">
                                    <button type="button"
                                        class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 text-lg font-bold transition-colors disabled:opacity-60 {{ $status === RunItemStatus::Done ? 'border-emerald-500 bg-emerald-600 text-white' : 'border-slate-600 bg-slate-800 text-slate-200' }}"
                                        wire:click="{{ $passTarget }}"
                                        wire:target="{{ $passTarget }}"
                                        wire:loading.attr="disabled"
                                        @disabled(! $isEditable)
                                    >{{ __('app.runs.pass') }}</button>
                                    <button type="button"
                                        class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 text-lg font-bold transition-colors disabled:opacity-60 {{ $status === RunItemStatus::Failed ? 'border-rose-500 bg-rose-600 text-white' : 'border-slate-600 bg-slate-800 text-slate-200' }}"
                                        wire:click="openFailFormFor({{ $item->id }}, '{{ $status->value }}')"
                                        wire:loading.attr="disabled"
                                        @disabled(! $isEditable)
                                    >{{ __('app.runs.fail') }}</button>
                                </div>
                            @elseif ($item->response_type === ResponseType::Numeric)
                                <div class="mt-3 flex items-center gap-3 sm:pl-16">
                                    <input
                                        type="number"
                                        inputmode="decimal"
                                        step="any"
                                        value="{{ $item->value_numeric }}"
                                        class="input min-h-14 w-44 text-center text-xl font-semibold"
                                        aria-label="{{ __('app.runs.value_numeric') }} — {{ $item->description }}"
                                        x-on:change="saving = true; $wire.setNumeric({{ $item->id }}, $event.target.value, '{{ $status->value }}').finally(() => saving = false)"
                                        @disabled(! $isEditable)
                                    >
                                    <span x-show="saving" x-cloak class="text-base text-slate-400">{{ __('app.runs.saving') }}</span>
                                </div>
                            @elseif ($item->response_type === ResponseType::Text)
                                <div class="mt-3 sm:pl-16">
                                    <textarea
                                        rows="2"
                                        class="input w-full text-lg"
                                        aria-label="{{ __('app.runs.value_text') }} — {{ $item->description }}"
                                        x-on:change="saving = true; $wire.setText({{ $item->id }}, $event.target.value, '{{ $status->value }}').finally(() => saving = false)"
                                        @disabled(! $isEditable)
                                    >{{ $item->value_text }}</textarea>
                                    <span x-show="saving" x-cloak class="text-base text-slate-400">{{ __('app.runs.saving') }}</span>
                                </div>
                            @endif

                            @error('value.'.$item->id)
                                <p class="mt-1 text-base text-rose-400 sm:pl-16">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Always-visible secondary control — never long-press-only --}}
                    @if ($isEditable)
                        <button
                            type="button"
                            class="flex min-h-20 w-24 shrink-0 flex-col items-center justify-center gap-0.5 rounded-2xl border-2 border-slate-600 bg-slate-800 px-2 text-base font-semibold text-slate-200 active:bg-slate-700 disabled:opacity-60"
                            wire:click="openActions({{ $item->id }}, '{{ $status->value }}')"
                            wire:loading.attr="disabled"
                            aria-label="{{ __('app.runs.item_options_for', ['number' => $item->sort_order]) }}"
                        >
                            <span>{{ __('app.item_status.not_applicable') }}</span>
                            <span class="text-slate-500" aria-hidden="true">·</span>
                            <span>{{ __('app.runs.fail') }}</span>
                        </button>
                    @endif
                </div>

                {{-- Per-item photo thumbnails --}}
                @if ($item->attachments->isNotEmpty())
                    <div class="mt-2 flex flex-wrap gap-4 sm:pl-16">
                        @foreach ($item->attachments as $photo)
                            <div class="relative" wire:key="item-photo-{{ $photo->id }}">
                                <img src="{{ $photo->url }}" alt="{{ $photo->original_name ?? __('app.runs.photo') }}"
                                    class="h-28 w-28 rounded-xl border border-slate-700 object-cover">
                                @if ($isEditable)
                                    <button type="button"
                                        class="absolute -right-3 -top-3 flex h-14 w-14 items-center justify-center rounded-full bg-rose-600 text-2xl font-bold text-white shadow"
                                        wire:click="removeAttachment({{ $photo->id }})"
                                        wire:loading.attr="disabled"
                                        aria-label="{{ __('app.runs.remove_photo') }}">&times;</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @error('itemPhotos.'.$item->id)
                    <p class="mt-1 text-base text-rose-400 sm:pl-16">{{ $message }}</p>
                @enderror
            </li>
        @endforeach
    </ol>

    {{-- ── Used Parts — paper sheet order (sort_order), never alphabetical ── --}}

    <section class="card mt-8 p-5">
        <h2 class="text-xl font-bold">{{ __('app.runs.used_parts') }}</h2>
        @if ($run->runParts->isEmpty())
            <p class="mt-3 text-lg text-slate-400">{{ __('app.parts.no_parts') }}</p>
        @else
            <ul class="mt-2 divide-y divide-slate-800">
                @foreach ($run->runParts as $part)
                    <li wire:key="part-{{ $part->id }}" class="flex min-h-20 flex-wrap items-center justify-between gap-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-lg font-semibold leading-snug">{{ $part->part_name_snapshot }}</p>
                            <p class="text-base text-slate-400">
                                {{ __('app.parts.part_code') }}: {{ $part->part_code_snapshot }}
                                <span wire:loading wire:target="qty.{{ $part->id }}" class="text-slate-300">· {{ __('app.runs.saving') }}</span>
                            </p>
                        </div>
                        @if ($isEditable)
                            <x-stepper wire:model.live="qty.{{ $part->id }}" :label="$part->part_name_snapshot" :min="0" :step="1" />
                        @else
                            <p class="text-2xl font-bold tabular-nums">{{ $part->qty_used }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ── Notes — debounced autosave with a visible saved indicator ── --}}

    <section class="card mt-8 p-5">
        <div class="flex items-center justify-between gap-4">
            <h2 class="text-xl font-bold">{{ __('app.runs.notes') }}</h2>
            <p class="text-base" aria-live="polite">
                <span class="text-slate-400" wire:loading wire:target="notes">{{ __('app.runs.saving') }}</span>
                @if ($notesSaved)
                    <span class="font-semibold text-emerald-400" wire:loading.remove wire:target="notes">{{ __('app.runs.autosaved') }}</span>
                @endif
            </p>
        </div>
        <textarea
            wire:model.live.debounce.1000ms="notes"
            rows="4"
            maxlength="5000"
            class="input mt-3 w-full text-lg"
            placeholder="{{ __('app.runs.notes_placeholder') }}"
            aria-label="{{ __('app.runs.notes') }}"
            @disabled(! $isEditable)
        ></textarea>
    </section>

    {{-- ── Run-level photos ────────────────────────────────────── --}}

    <section class="card mt-8 p-5">
        <h2 class="text-xl font-bold">{{ __('app.runs.run_photos') }}</h2>
        <p class="mt-1 text-base text-slate-400">{{ __('app.runs.run_photos_hint') }}</p>

        @if ($run->attachments->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-4">
                @foreach ($run->attachments as $photo)
                    <div class="relative" wire:key="run-photo-{{ $photo->id }}">
                        <img src="{{ $photo->url }}" alt="{{ $photo->original_name ?? __('app.runs.photo') }}"
                            class="h-28 w-28 rounded-xl border border-slate-700 object-cover">
                        @if ($isEditable)
                            <button type="button"
                                class="absolute -right-3 -top-3 flex h-14 w-14 items-center justify-center rounded-full bg-rose-600 text-2xl font-bold text-white shadow"
                                wire:click="removeAttachment({{ $photo->id }})"
                                wire:loading.attr="disabled"
                                aria-label="{{ __('app.runs.remove_photo') }}">&times;</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($isEditable)
            <label class="mt-4 flex min-h-14 w-full cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 px-4 text-lg font-semibold text-slate-300 active:bg-slate-800">
                <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                </svg>
                {{ __('app.runs.add_photo') }}
                <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="runPhoto">
            </label>
            <p class="mt-2 text-base text-slate-400" wire:loading wire:target="runPhoto">{{ __('app.runs.photo_uploading') }}</p>
            @error('runPhoto')
                <p class="mt-2 text-base text-rose-400">{{ $message }}</p>
            @enderror
        @endif
    </section>

    {{-- ── Sign-off record — the two signature blocks off the paper form ── --}}

    @if ($run->operator_signature_path || in_array($run->status, [RunStatus::Submitted, RunStatus::Approved, RunStatus::Rejected], true))
        <section class="card mt-8 p-5">
            <h2 class="text-xl font-bold">{{ __('app.runs.signoff') }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-signature-block
                    :label="__('app.runs.operator_signature')"
                    :user="$run->operator"
                    :run="$run"
                    role="operator"
                    :signed-at="$run->operator_signed_at" />

                <x-signature-block
                    :label="__('app.runs.supervisor_signature')"
                    :user="$run->supervisor"
                    :run="$run"
                    role="supervisor"
                    :signed-at="$run->supervisor_signed_at"
                    :note="$run->supervisor_comment" />
            </div>

            @if ($run->status === RunStatus::Submitted)
                <p class="mt-4 text-base text-slate-400">{{ __('app.runs.awaiting_supervisor') }}</p>
            @endif
        </section>
    @endif

    {{-- ── Submission ──────────────────────────────────────────── --}}

    @if ($isEditable)
        <section class="mt-8">
            @if ($showMissing && $run->missing_required_items->isNotEmpty())
                <div class="mb-4 rounded-2xl border-2 border-rose-500 bg-rose-950/40 p-5" role="alert">
                    <p class="text-lg font-bold text-rose-200">{{ __('app.runs.cannot_submit_incomplete') }}</p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($run->missing_required_items as $missing)
                            <li wire:key="missing-{{ $missing->id }}">
                                {{-- Tap to scroll to the row (scroll-mt clears the sticky bars) --}}
                                <button type="button"
                                    class="flex min-h-14 w-full items-center gap-3 rounded-xl border border-rose-500/50 bg-rose-950/40 px-4 text-left text-lg text-rose-100 active:bg-rose-900/40"
                                    x-on:click="document.getElementById('run-item-{{ $missing->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' })">
                                    <span class="font-bold tabular-nums">#{{ $missing->sort_order }}</span>
                                    <span class="min-w-0 flex-1 truncate">{{ $missing->description }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-button size="kiosk" class="w-full text-2xl" wire:click="attemptSubmit" wire:target="attemptSubmit" wire:loading.attr="disabled">
                {{ __('app.runs.submit_run') }}
            </x-button>
        </section>
    @endif

    {{-- ── Modals (only ever reachable while editable) ─────────── --}}

    @if ($isEditable)

        {{-- Options sheet: N/A · Fail · Undo · photo --}}
        <x-modal name="item-actions" :title="$actionItem !== null ? __('app.runs.item_options_title', ['number' => $actionItem->sort_order]) : __('app.runs.na_fail')">
            @if ($actionItem !== null)
                <p class="text-lg leading-snug text-slate-200 [.kiosk_&]:text-slate-200">{{ $actionItem->description }}</p>

                <div class="mt-5 space-y-3">
                    @if ($actionItem->status !== RunItemStatus::NotApplicable)
                        <button type="button"
                            class="flex min-h-14 w-full items-center justify-center rounded-xl border-2 border-slate-500 text-lg font-bold text-slate-100 active:bg-slate-800"
                            wire:click="markNotApplicable" wire:loading.attr="disabled">
                            {{ __('app.runs.mark_not_applicable') }}
                        </button>
                    @endif

                    <button type="button"
                        class="flex min-h-14 w-full items-center justify-center rounded-xl border-2 border-rose-500 bg-rose-600/20 text-lg font-bold text-rose-200 active:bg-rose-600/40"
                        wire:click="openFailForm" wire:loading.attr="disabled">
                        {{ __('app.runs.mark_failed') }}
                    </button>

                    @if ($actionItem->status !== RunItemStatus::Pending)
                        <button type="button"
                            class="flex min-h-14 w-full items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                            wire:click="undoItem" wire:loading.attr="disabled">
                            {{ __('app.runs.undo_to_pending') }}
                        </button>
                    @endif

                    <label class="flex min-h-14 w-full cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800">
                        <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                        {{ __('app.runs.add_photo') }}
                        <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="itemPhotos.{{ $actionItem->id }}">
                    </label>
                    <p class="text-base text-slate-400" wire:loading wire:target="itemPhotos.{{ $actionItem->id }}">{{ __('app.runs.photo_uploading') }}</p>
                    @error('itemPhotos.'.$actionItem->id)
                        <p class="text-base text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif
        </x-modal>

        {{-- Fail form: reason required, photo when the item demands it --}}
        <x-modal name="item-fail" :title="__('app.runs.mark_failed_title')">
            @if ($actionItem !== null)
                <p class="text-lg leading-snug text-slate-200">{{ $actionItem->description }}</p>

                <label for="fail-reason" class="mt-4 block text-lg font-semibold">{{ __('app.runs.fail_reason') }}</label>
                <textarea id="fail-reason" wire:model="failReason" rows="3" maxlength="500" class="input mt-2 w-full text-lg"></textarea>
                @error('failReason')
                    <p class="mt-1 text-base text-rose-400">{{ $message }}</p>
                @enderror

                @php
                    $needsPhoto = (bool) ($actionItem->templateItem?->requires_photo_on_fail ?? false);
                @endphp
                @if ($needsPhoto)
                    <p class="mt-3 text-base font-semibold text-amber-300">{{ __('app.runs.photo_required_on_fail') }}</p>
                @endif
                <label class="mt-3 flex min-h-14 w-full cursor-pointer items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800">
                    <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                    </svg>
                    {{ __('app.runs.add_photo') }}
                    <input type="file" accept="image/*" capture="environment" class="sr-only" wire:model="failPhoto">
                </label>
                <p class="mt-2 text-base text-slate-400" wire:loading wire:target="failPhoto">{{ __('app.runs.photo_uploading') }}</p>
                @if ($failPhoto)
                    <p class="mt-2 text-base text-slate-300">{{ $failPhoto->getClientOriginalName() }}</p>
                @endif
                @error('failPhoto')
                    <p class="mt-1 text-base text-rose-400">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex gap-3">
                    <button type="button"
                        class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                        x-on:click="show = false">
                        {{ __('app.actions.cancel') }}
                    </button>
                    <x-button variant="danger" class="min-h-14 flex-1 text-lg" wire:click="confirmFail" wire:target="confirmFail" wire:loading.attr="disabled">
                        {{ __('app.runs.confirm_fail') }}
                    </x-button>
                </div>
            @endif
        </x-modal>

        {{-- Issue prompt — pre-filled after a fail --}}
        <x-modal name="item-issue" :title="__('app.runs.issue_title')">
            <p class="text-lg text-slate-200">{{ __('app.runs.raise_issue_prompt') }}</p>

            <label for="issue-description" class="mt-4 block text-lg font-semibold">{{ __('app.runs.issue_description_label') }}</label>
            <textarea id="issue-description" wire:model="issueDescription" rows="4" maxlength="2000" class="input mt-2 w-full text-lg"></textarea>
            @error('issueDescription')
                <p class="mt-1 text-base text-rose-400">{{ $message }}</p>
            @enderror

            <p class="mt-4 text-lg font-semibold">{{ __('app.issues.severity') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                @foreach (IssueSeverity::cases() as $severity)
                    <button type="button"
                        wire:key="severity-{{ $severity->value }}"
                        wire:click="$set('issueSeverity', '{{ $severity->value }}')"
                        class="min-h-14 rounded-xl border-2 text-lg font-bold transition-colors {{ $issueSeverity === $severity->value ? 'border-amber-400 bg-amber-400/20 text-white' : 'border-slate-600 text-slate-300' }}"
                        aria-pressed="{{ $issueSeverity === $severity->value ? 'true' : 'false' }}">
                        {{ $severity->label() }}
                    </button>
                @endforeach
            </div>
            @error('issueSeverity')
                <p class="mt-1 text-base text-rose-400">{{ $message }}</p>
            @enderror

            <div class="mt-6 flex gap-3">
                <button type="button"
                    class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                    wire:click="skipIssue">
                    {{ __('app.runs.issue_skip') }}
                </button>
                <x-button class="min-h-14 flex-1 text-lg" wire:click="raiseIssue" wire:target="raiseIssue" wire:loading.attr="disabled">
                    {{ __('app.runs.issue_raise') }}
                </x-button>
            </div>
        </x-modal>

        {{-- Confirm submission — signature + identity re-confirmation --}}
        <x-modal name="confirm-submit" :title="__('app.runs.confirm_submit_title')" max-width="xl"
            x-data="{ signature: '', confirmation: '', busy: false }">
            <p class="text-lg text-slate-200">{{ __('app.runs.confirm_submit_body') }}</p>

            <dl class="mt-4 space-y-1 text-lg">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-400">{{ __('app.runs.machine') }}</dt>
                    <dd class="font-semibold">{{ $run->machine->name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-400">{{ __('app.runs.template') }}</dt>
                    <dd class="font-semibold">{{ $run->template->name }}</dd>
                </div>
                @if ($run->shift->isSplit())
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-400">{{ __('app.runs.shift') }}</dt>
                        <dd class="font-semibold">{{ $run->shift->label() }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-400">{{ __('app.common.status') }}</dt>
                    <dd class="font-semibold">{{ __('app.runs.progress', ['done' => $progress['done'], 'total' => $progress['total']]) }}</dd>
                </div>
            </dl>

            {{-- Operator signature. The canvas value lives in this Alpine
                 scope and is handed to submit() as an argument — it is never
                 a Livewire property (see resources/js/app.js). --}}
            <div class="mt-6">
                <x-signature-pad
                    x-model="signature"
                    :label="__('app.runs.operator_signature')"
                    :hint="__('app.runs.signature_hint')" />
                @error('signature')
                    <p class="mt-2 text-base text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Identity re-confirmation. On a shared tablet the session only
                 says someone signed in earlier, not who is signing now. --}}
            @php
                $method = $this->confirmationMethod;
            @endphp
            @if ($method !== 'none')
                <div class="mt-6">
                    <label for="sign-confirmation" class="block text-lg font-semibold">
                        {{ $method === 'pin' ? __('app.runs.confirm_with_pin') : __('app.runs.confirm_with_password') }}
                    </label>
                    <p class="mt-1 text-base text-slate-400">
                        {{ __('app.runs.confirm_identity_hint', ['name' => auth()->user()->full_name]) }}
                    </p>
                    <input
                        id="sign-confirmation"
                        type="password"
                        @if ($method === 'pin') inputmode="numeric" autocomplete="one-time-code" @else autocomplete="current-password" @endif
                        class="input mt-2 min-h-14 w-full text-center text-2xl tracking-[0.5em]"
                        x-model="confirmation"
                        x-on:keydown.enter.prevent="if (signature && ! busy) { busy = true; $wire.submit(signature, confirmation).finally(() => { confirmation = ''; busy = false }) }"
                    >
                    @error('confirmation')
                        <p class="mt-2 text-base text-rose-400">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="mt-6 flex gap-3">
                <button type="button"
                    class="flex min-h-14 flex-1 items-center justify-center rounded-xl border-2 border-slate-600 text-lg font-semibold text-slate-300 active:bg-slate-800"
                    x-on:click="confirmation = ''; show = false">
                    {{ __('app.actions.cancel') }}
                </button>
                <x-button class="min-h-14 flex-1 text-lg"
                    x-bind:disabled="! signature || busy"
                    x-on:click="busy = true; $wire.submit(signature, confirmation).finally(() => { confirmation = ''; busy = false })">
                    <span x-show="! busy">{{ __('app.runs.sign_and_submit') }}</span>
                    <span x-show="busy" x-cloak>{{ __('app.runs.saving') }}</span>
                </x-button>
            </div>
        </x-modal>

    @endif
</div>
