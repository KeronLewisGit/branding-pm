{{--
    Issue detail: the evidence first (where the fault came from, what the
    operator saw, their photos), then the decisions, then the history.

    The history is the activity log, not a hand-rolled table — every
    transition is written through the model, so the audit trail and the screen
    cannot drift apart.
--}}
@use('App\Enums\IssueSeverity')
@use('App\Enums\IssueStatus')

@php
    $displayTz = (string) config('app.display_timezone', 'UTC');
    $nextStatuses = $this->nextStatuses;
@endphp

<div class="mx-auto w-full max-w-4xl">

    <div class="mb-6">
        <a href="{{ route('issues.index') }}" class="text-base font-semibold text-sky-700 hover:underline">
            &larr; {{ __('app.issues.back_to_register') }}
        </a>

        <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-900">
                    {{ __('app.issues.issue') }} #{{ $issue->id }}
                </h1>
                <p class="mt-1 text-lg text-slate-600">
                    {{ $issue->machine->name }}
                    <span class="text-slate-400">· {{ $issue->machine->location->name }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-badge :color="$issue->severity->color()">{{ $issue->severity->label() }}</x-badge>
                <x-badge :color="$issue->status->color()">{{ $issue->status->label() }}</x-badge>
            </div>
        </div>
    </div>

    @error('status')
        <x-alert type="error" class="mb-6">{{ $message }}</x-alert>
    @enderror

    @if ($issue->severity === IssueSeverity::Breakdown && $issue->status->isOpen())
        <x-alert type="error" class="mb-6">{{ __('app.issues.breakdown_banner', ['machine' => $issue->machine->name]) }}</x-alert>
    @endif

    {{-- What is wrong --}}
    <x-card>
        <h2 class="text-xl font-bold text-slate-900">{{ __('app.issues.what_is_wrong') }}</h2>
        <p class="mt-2 whitespace-pre-line text-base text-slate-800">{{ $issue->description }}</p>

        <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 text-base sm:grid-cols-3">
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.issues.raised_by') }}</dt>
                <dd class="text-slate-800">{{ $issue->raisedBy?->full_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.issues.raised') }}</dt>
                <dd class="tabular-nums text-slate-800">{{ $issue->created_at?->timezone($displayTz)->format('D j M Y, g:i A') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.issues.assigned_to') }}</dt>
                <dd class="text-slate-800">{{ $issue->assignedTo?->full_name ?? __('app.issues.unassigned') }}</dd>
            </div>
        </dl>
    </x-card>

    {{-- Where it came from — the failed checklist item and its photos --}}
    @if ($issue->runItem !== null || $issue->run !== null)
        <x-card class="mt-6">
            <h2 class="text-xl font-bold text-slate-900">{{ __('app.issues.origin') }}</h2>

            @if ($issue->runItem !== null)
                <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-base font-semibold text-rose-900">
                        <span class="tabular-nums">#{{ $issue->runItem->sort_order }}</span>
                        {{ $issue->runItem->description }}
                    </p>
                    @if ($issue->runItem->fail_reason)
                        <p class="mt-1 text-base text-rose-800">“{{ $issue->runItem->fail_reason }}”</p>
                    @endif
                </div>

                @if ($issue->runItem->attachments->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-3">
                        @foreach ($issue->runItem->attachments as $photo)
                            <a href="{{ $photo->url }}" target="_blank" rel="noopener" wire:key="issue-photo-{{ $photo->id }}">
                                <img src="{{ $photo->url }}"
                                     alt="{{ $photo->original_name ?? __('app.runs.photo') }}"
                                     class="h-28 w-28 rounded-lg border border-slate-200 object-cover">
                            </a>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="mt-3 text-base text-slate-600">{{ __('app.issues.raised_outside_run') }}</p>
            @endif

            @if ($issue->run !== null)
                <p class="mt-4 text-base text-slate-600">
                    {{ __('app.issues.from_run', [
                        'template' => $issue->run->template->name,
                        'date' => $issue->run->scheduled_for->format('j M Y'),
                    ]) }}
                    @if ($this->canViewRun)
                        <a href="{{ route('runs.show', $issue->run) }}" class="ml-1 font-semibold text-sky-700 hover:underline">
                            {{ __('app.issues.open_checklist') }}
                        </a>
                    @endif
                </p>
            @endif
        </x-card>
    @endif

    {{-- Triage and repair --}}
    <x-card class="mt-6">
        <h2 class="text-xl font-bold text-slate-900">{{ __('app.issues.actions_title') }}</h2>

        @if (empty($nextStatuses) && ! $this->canAssign)
            <p class="mt-2 text-base text-slate-600">{{ __('app.issues.read_only') }}</p>
        @else
            @if ($this->canAssign)
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.issues.assign') }}</span>
                        <select class="input min-h-14 w-full"
                                x-on:change="$wire.assign($event.target.value)">
                            <option value="" @selected($issue->assigned_to === null)>{{ __('app.issues.unassigned') }}</option>
                            @foreach ($this->assignableUsers as $candidate)
                                <option value="{{ $candidate->id }}" @selected($issue->assigned_to === $candidate->id)>
                                    {{ $candidate->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <div>
                        <span class="mb-1 block text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.issues.severity') }}</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach (IssueSeverity::cases() as $severityOption)
                                <button type="button"
                                    wire:key="severity-{{ $severityOption->value }}"
                                    wire:click="setSeverity('{{ $severityOption->value }}')"
                                    class="min-h-14 rounded-xl border-2 px-4 font-semibold {{ $issue->severity === $severityOption ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-50' }}"
                                    aria-pressed="{{ $issue->severity === $severityOption ? 'true' : 'false' }}">
                                    {{ $severityOption->label() }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Resolution notes are required to resolve, so they sit above
                 the buttons rather than behind a second dialog. --}}
            @if (in_array(IssueStatus::Resolved, $nextStatuses, true))
                <div class="mt-6">
                    <label for="resolution-notes" class="block text-lg font-semibold text-slate-900">
                        {{ __('app.issues.resolution_notes') }}
                    </label>
                    <p class="text-base text-slate-500">{{ __('app.issues.resolution_hint') }}</p>
                    <x-textarea id="resolution-notes" wire:model="resolutionNotes" rows="3" maxlength="2000" class="mt-2 w-full" />
                    @error('resolutionNotes')
                        <p class="mt-1 text-base text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @if (! empty($nextStatuses))
                <div class="mt-6 flex flex-wrap gap-3">
                    @foreach ($nextStatuses as $next)
                        <x-button
                            wire:key="move-{{ $next->value }}"
                            :variant="$next === IssueStatus::Open ? 'ghost' : 'primary'"
                            wire:click="moveTo('{{ $next->value }}')"
                            wire:target="moveTo"
                            wire:loading.attr="disabled">
                            {{ __('app.issues.move_to.'.$next->value) }}
                        </x-button>
                    @endforeach
                </div>
            @endif
        @endif

        @if ($issue->resolved_at)
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">
                    {{ __('app.issues.resolved_at') }}
                    <span class="tabular-nums">{{ $issue->resolved_at->timezone($displayTz)->format('D j M Y, g:i A') }}</span>
                </p>
                @if ($issue->resolution_notes)
                    <p class="mt-1 whitespace-pre-line text-base text-emerald-900">{{ $issue->resolution_notes }}</p>
                @endif
            </div>
        @endif
    </x-card>

    {{-- Other open faults on the same machine — context for the decision --}}
    @if ($this->otherOpenIssues->isNotEmpty())
        <x-card class="mt-6">
            <h2 class="text-xl font-bold text-slate-900">{{ __('app.issues.other_open', ['machine' => $issue->machine->name]) }}</h2>
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($this->otherOpenIssues as $other)
                    <li wire:key="other-{{ $other->id }}" class="flex items-start justify-between gap-3 py-3">
                        <a href="{{ route('issues.show', $other->id) }}" class="min-w-0 flex-1 text-base text-slate-800 underline-offset-2 hover:underline">
                            {{ \Illuminate\Support\Str::limit($other->description, 120) }}
                        </a>
                        <x-badge :color="$other->severity->color()">{{ $other->severity->label() }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- History, straight from the activity log --}}
    <x-card class="mt-6">
        <h2 class="text-xl font-bold text-slate-900">{{ __('app.issues.history') }}</h2>

        @php
            // By id, not created_at: two transitions in the same second are
            // common (assign then start) and must not come out shuffled.
            $entries = $issue->activitiesAsSubject->sortByDesc('id');
        @endphp

        @if ($entries->isEmpty())
            <p class="mt-3 text-base text-slate-500">{{ __('app.issues.no_history') }}</p>
        @else
            <ol class="mt-4 space-y-4">
                @foreach ($entries as $entry)
                    @php
                        // activitylog 5 keeps the diff in its own column
                        // (`attribute_changes`), not in `properties` as v4 did,
                        // and has no changes() helper. `old` is shown beside
                        // the new value so a status move reads as a move.
                        $changes = $entry->attribute_changes['attributes'] ?? [];
                        $previous = $entry->attribute_changes['old'] ?? [];
                    @endphp
                    <li wire:key="activity-{{ $entry->id }}" class="border-l-2 border-slate-200 pl-4">
                        <p class="text-base text-slate-800">
                            <span class="font-semibold">{{ $entry->causer?->full_name ?? __('app.issues.system') }}</span>
                            <span class="text-slate-500">
                                · {{ $entry->created_at?->timezone($displayTz)->format('D j M Y, g:i A') }}
                            </span>
                        </p>
                        @if (! empty($changes))
                            <ul class="mt-1 text-base text-slate-600">
                                @foreach ($changes as $field => $value)
                                    @php
                                        $was = $previous[$field] ?? null;
                                        $format = fn ($raw) => match (true) {
                                            $raw === null, $raw === '' => __('app.common.none'),
                                            $field === 'status' => \App\Enums\IssueStatus::tryFrom((string) $raw)?->label() ?? $raw,
                                            $field === 'severity' => \App\Enums\IssueSeverity::tryFrom((string) $raw)?->label() ?? $raw,
                                            $field === 'assigned_to' => $this->assignableUsers->firstWhere('id', (int) $raw)?->full_name ?? '#'.$raw,
                                            is_scalar($raw) => (string) $raw,
                                            default => json_encode($raw),
                                        };
                                    @endphp
                                    <li wire:key="activity-{{ $entry->id }}-{{ $field }}">
                                        {{ __('app.issues.field.'.$field) }}:
                                        <span class="text-slate-500">{{ $format($was) }}</span>
                                        <span aria-hidden="true">&rarr;</span>
                                        <span class="font-medium text-slate-800">{{ $format($value) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </x-card>
</div>
