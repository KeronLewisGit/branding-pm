{{--
    Supervisor review. The whole sheet as it was completed — every item with
    its answer, the failure reasons, the photos, the parts used and the
    operator's notes — then the two decisions at the bottom.

    Failed items are pulled to the top as a summary because they are the
    reason a supervisor reads this screen at all; they still appear in their
    proper numbered place in the list below.
--}}
@use('App\Enums\ResponseType')
@use('App\Enums\RunItemStatus')

@php
    $displayTz = (string) config('app.display_timezone', 'UTC');
    $failedItems = $run->items->where('status', RunItemStatus::Failed);
    $canDecide = $this->canDecide;
@endphp

<div class="mx-auto w-full max-w-4xl">

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <a href="{{ route('runs.approvals') }}" class="text-base font-semibold text-sky-700 hover:underline">
                &larr; {{ __('app.approvals.back_to_queue') }}
            </a>
            <a href="{{ route('runs.pdf', $run) }}" class="ml-4 text-base font-semibold text-sky-700 hover:underline">
                {{ __('app.runs.download_pdf') }}
            </a>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $run->template->name }}</h1>
            <p class="mt-1 text-lg text-slate-600">
                {{ $run->machine->name }}
                <span class="text-slate-400">· {{ $run->machine->code }}</span>
            </p>
        </div>
        <x-status-dot :status="$run->status" class="text-lg" />
    </div>

    {{-- Header block — the same fields as the paper work order --}}
    <x-card>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 text-base sm:grid-cols-3">
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.runs.scheduled_for') }}</dt>
                <dd class="font-semibold text-slate-900">{{ $run->scheduled_for->format('D, j M Y') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.runs.shift') }}</dt>
                <dd class="font-semibold text-slate-900">{{ $run->display_shift }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.templates.work_category') }}</dt>
                <dd class="font-semibold text-slate-900">{{ $run->template->work_category->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.locations.location') }}</dt>
                <dd class="text-slate-700">{{ $run->machine->location->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.runs.started_at') }}</dt>
                <dd class="tabular-nums text-slate-700">{{ $run->started_at?->timezone($displayTz)->format('D j M, g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.runs.submitted_at') }}</dt>
                <dd class="tabular-nums text-slate-700">{{ $run->submitted_at?->timezone($displayTz)->format('D j M, g:i A') ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-3">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.templates.work_description') }}</dt>
                <dd class="text-slate-700">{{ $run->template->work_description }}</dd>
            </div>
        </dl>
    </x-card>

    {{-- What needs attention, before anything else --}}
    @if ($failedItems->isNotEmpty())
        <div class="mt-6 rounded-xl border-2 border-rose-300 bg-rose-50 p-5" role="alert">
            <p class="text-lg font-bold text-rose-900">
                {{ trans_choice('app.approvals.failed_summary', $failedItems->count(), ['count' => $failedItems->count()]) }}
            </p>
            <ul class="mt-3 space-y-2">
                @foreach ($failedItems as $failed)
                    <li wire:key="failed-{{ $failed->id }}" class="text-base text-rose-900">
                        <span class="font-bold tabular-nums">#{{ $failed->sort_order }}</span>
                        {{ $failed->description }}
                        @if ($failed->fail_reason)
                            <span class="block pl-6 text-rose-700">“{{ $failed->fail_reason }}”</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($run->issues->isNotEmpty())
        <x-card class="mt-6">
            <h2 class="text-xl font-bold text-slate-900">{{ __('app.issues.title') }}</h2>
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($run->issues as $issue)
                    <li wire:key="issue-{{ $issue->id }}" class="flex flex-wrap items-start justify-between gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            @can('issue.view')
                                <a href="{{ route('issues.show', $issue->id) }}"
                                   class="text-base text-slate-800 underline-offset-2 hover:underline">{{ $issue->description }}</a>
                            @else
                                <p class="text-base text-slate-800">{{ $issue->description }}</p>
                            @endcan
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('app.issues.raised_by') }}: {{ $issue->raisedBy?->full_name ?? '—' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <x-badge :color="$issue->severity->color()">{{ $issue->severity->label() }}</x-badge>
                            <x-badge :color="$issue->status->color()">{{ $issue->status->label() }}</x-badge>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- The completed sheet, in the paper form's numbering --}}
    <x-card class="mt-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-xl font-bold text-slate-900">{{ __('app.templates.items') }}</h2>
            <p class="text-base font-semibold tabular-nums text-slate-600">
                {{ __('app.runs.progress', ['done' => $progress['done'], 'total' => $progress['total']]) }}
            </p>
        </div>

        <ol class="mt-4 divide-y divide-slate-100">
            @foreach ($run->items as $item)
                <li wire:key="review-item-{{ $item->id }}" class="py-4">
                    <div class="flex items-start gap-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-300 text-base font-bold tabular-nums text-slate-700">
                            {{ $item->sort_order }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-base font-semibold leading-snug text-slate-900">{{ $item->description }}</p>

                            @if ($item->response_type === ResponseType::Numeric && $item->value_numeric !== null)
                                <p class="mt-1 text-base text-slate-700">
                                    {{ __('app.runs.value_numeric') }}: <span class="font-semibold tabular-nums">{{ $item->value_numeric }}</span>
                                </p>
                            @elseif ($item->response_type === ResponseType::Text && $item->value_text)
                                <p class="mt-1 text-base text-slate-700">{{ $item->value_text }}</p>
                            @endif

                            @if ($item->status === RunItemStatus::Failed && $item->fail_reason)
                                <p class="mt-1 text-base font-medium text-rose-700">{{ $item->fail_reason }}</p>
                            @endif

                            <p class="mt-1 text-sm text-slate-500">
                                @if ($item->completedBy)
                                    {{ __('app.runs.answered_by', ['name' => $item->completedBy->full_name]) }}
                                @endif
                                @if ($item->completed_at)
                                    <span class="tabular-nums">· {{ $item->completed_at->timezone($displayTz)->format('g:i A') }}</span>
                                @endif
                            </p>

                            @if ($item->attachments->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-3">
                                    @foreach ($item->attachments as $photo)
                                        <a href="{{ $photo->url }}" target="_blank" rel="noopener"
                                           wire:key="review-photo-{{ $photo->id }}">
                                            <img src="{{ $photo->url }}"
                                                 alt="{{ $photo->original_name ?? __('app.runs.photo') }}"
                                                 class="h-24 w-24 rounded-lg border border-slate-200 object-cover">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <span class="shrink-0">
                            @if ($item->status === RunItemStatus::Done)
                                <x-badge color="emerald">{{ $item->status->label() }}</x-badge>
                            @elseif ($item->status === RunItemStatus::Failed)
                                <x-badge color="rose">{{ $item->status->label() }}</x-badge>
                            @elseif ($item->status === RunItemStatus::NotApplicable)
                                <x-badge>{{ $item->status->label() }}</x-badge>
                            @else
                                <x-badge color="amber">{{ $item->status->label() }}</x-badge>
                            @endif
                        </span>
                    </div>
                </li>
            @endforeach
        </ol>
    </x-card>

    {{-- Used parts, in the paper sheet's order --}}
    <x-card class="mt-6">
        <h2 class="text-xl font-bold text-slate-900">{{ __('app.runs.used_parts') }}</h2>
        @if ($run->runParts->isEmpty())
            <p class="mt-3 text-base text-slate-500">{{ __('app.parts.no_parts') }}</p>
        @else
            <table class="mt-3 min-w-full divide-y divide-slate-200 text-base">
                <thead class="text-left text-sm font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2">{{ __('app.parts.part_code') }}</th>
                        <th class="py-2">{{ __('app.parts.part') }}</th>
                        <th class="py-2 text-right">{{ __('app.runs.qty_used') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($run->runParts as $part)
                        <tr wire:key="review-part-{{ $part->id }}">
                            <td class="py-2 text-slate-500">{{ $part->part_code_snapshot }}</td>
                            <td class="py-2 text-slate-800">{{ $part->part_name_snapshot }}</td>
                            <td class="py-2 text-right font-semibold tabular-nums text-slate-900">{{ $part->qty_used }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    {{-- Operator notes and whole-run photos --}}
    <x-card class="mt-6">
        <h2 class="text-xl font-bold text-slate-900">{{ __('app.runs.notes') }}</h2>
        <p class="mt-2 whitespace-pre-line text-base text-slate-700">{{ $run->notes ?: __('app.common.none') }}</p>

        @if ($run->attachments->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ($run->attachments as $photo)
                    <a href="{{ $photo->url }}" target="_blank" rel="noopener" wire:key="review-run-photo-{{ $photo->id }}">
                        <img src="{{ $photo->url }}"
                             alt="{{ $photo->original_name ?? __('app.runs.photo') }}"
                             class="h-24 w-24 rounded-lg border border-slate-200 object-cover">
                    </a>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- Signatures --}}
    <x-card class="mt-6">
        <h2 class="text-xl font-bold text-slate-900">{{ __('app.runs.signoff') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-signature-block
                :label="__('app.runs.operator_signature')"
                :user="$run->operator"
                :path="$run->operator_signature_path"
                :signed-at="$run->operator_signed_at" />
            <x-signature-block
                :label="__('app.runs.supervisor_signature')"
                :user="$run->supervisor"
                :path="$run->supervisor_signature_path"
                :signed-at="$run->supervisor_signed_at" />
        </div>
    </x-card>

    {{-- The decision --}}
    <x-card class="mt-6" x-data="{ signature: '', busy: false }">
        <h2 class="text-xl font-bold text-slate-900">{{ __('app.approvals.decision') }}</h2>

        @if (! $canDecide)
            <x-alert type="warning" class="mt-4">{{ $this->blockedReason }}</x-alert>
        @else
            <p class="mt-1 text-base text-slate-600">{{ __('app.approvals.decision_hint') }}</p>

            <label for="supervisor-comment" class="mt-5 block text-lg font-semibold text-slate-900">
                {{ __('app.runs.supervisor_comment') }}
            </label>
            <p class="text-base text-slate-500">{{ __('app.approvals.comment_hint') }}</p>
            <x-textarea id="supervisor-comment" wire:model="comment" rows="3" maxlength="2000" class="mt-2 w-full" />
            @error('comment')
                <p class="mt-1 text-base text-rose-600">{{ $message }}</p>
            @enderror

            <div class="mt-6">
                <x-signature-pad
                    x-model="signature"
                    :label="__('app.runs.supervisor_signature')"
                    :hint="__('app.approvals.signature_hint')" />
                @error('signature')
                    <p class="mt-2 text-base text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-button variant="danger" class="min-h-14 flex-1 text-lg"
                    x-bind:disabled="busy"
                    x-on:click="busy = true; $wire.reject().finally(() => busy = false)">
                    {{ __('app.approvals.reject_run') }}
                </x-button>

                <x-button class="min-h-14 flex-1 text-lg"
                    x-bind:disabled="! signature || busy"
                    x-on:click="busy = true; $wire.approve(signature).finally(() => busy = false)">
                    <span x-show="! busy">{{ __('app.approvals.sign_and_approve') }}</span>
                    <span x-show="busy" x-cloak>{{ __('app.runs.saving') }}</span>
                </x-button>
            </div>

            <p class="mt-3 text-base text-slate-500">{{ __('app.approvals.immutable_hint') }}</p>
        @endif
    </x-card>
</div>
