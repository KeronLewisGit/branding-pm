{{--
    Issue register. Reads top-down as "what needs doing next": open before
    closed, breakdown before everything, then oldest first.

    Severity and status are always a badge WITH its label — never colour on
    its own (contract §8).
--}}
@use('App\Enums\IssueSeverity')
@use('App\Enums\IssueStatus')
@use('App\Livewire\Issues\IssueRegister')

<div>
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('app.issues.title') }}</h1>
            <p class="mt-1 text-base text-slate-600">{{ __('app.issues.subtitle') }}</p>
        </div>

        @can('create', \App\Models\Issue::class)
            <x-button wire:click="openCreate">{{ __('app.issues.raise_issue') }}</x-button>
        @endcan
    </div>

    {{-- Open work by severity, within the current machine/location scope --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        @foreach (IssueSeverity::cases() as $severityOption)
            @php
                $count = (int) ($openCounts[$severityOption->value] ?? 0);
                $tone = match ($severityOption) {
                    IssueSeverity::Breakdown => 'border-red-300 bg-red-50 text-red-900',
                    IssueSeverity::High => 'border-rose-200 bg-rose-50 text-rose-900',
                    IssueSeverity::Medium => 'border-amber-200 bg-amber-50 text-amber-900',
                    default => 'border-slate-200 bg-slate-50 text-slate-700',
                };
            @endphp
            <button type="button"
                wire:key="count-{{ $severityOption->value }}"
                wire:click="$set('severity', '{{ $severity === $severityOption->value ? '' : $severityOption->value }}')"
                class="inline-flex min-h-14 items-center gap-2 rounded-lg border px-4 font-semibold {{ $tone }} {{ $severity === $severityOption->value ? 'ring-2 ring-slate-900 ring-offset-1' : '' }}"
                aria-pressed="{{ $severity === $severityOption->value ? 'true' : 'false' }}">
                <span class="tabular-nums">{{ $count }}</span>
                {{ $severityOption->label() }}
                <span class="sr-only">{{ __('app.issues.open_of_severity') }}</span>
            </button>
        @endforeach
    </div>

    {{-- Filters --}}
    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <label class="block lg:col-span-2">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.actions.search') }}</span>
            <input type="search" wire:model.live.debounce.400ms="search"
                   placeholder="{{ __('app.issues.search_placeholder') }}"
                   class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.runs.machine') }}</span>
            <select wire:model.live="machine" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value="">{{ __('app.runs.all_machines') }}</option>
                @foreach ($machines as $machineOption)
                    <option value="{{ $machineOption->id }}">{{ $machineOption->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.locations.location') }}</span>
            <select wire:model.live="location" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value="">{{ __('app.runs.all_locations') }}</option>
                @foreach ($locations as $locationOption)
                    <option value="{{ $locationOption->id }}">{{ $locationOption->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.common.status') }}</span>
            <select wire:model.live="status" class="min-h-14 w-full rounded-lg border-slate-300 text-base shadow-sm">
                <option value="{{ IssueRegister::FILTER_OPEN }}">{{ __('app.issues.status_open_any') }}</option>
                <option value="">{{ __('app.issues.all_statuses') }}</option>
                @foreach (IssueStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="mb-6 flex flex-wrap items-end gap-3">
        <label class="block">
            <span class="mb-1 block text-sm font-medium text-slate-700">{{ __('app.issues.assigned_to') }}</span>
            <select wire:model.live="assignee" class="min-h-14 rounded-lg border-slate-300 text-base shadow-sm">
                <option value="">{{ __('app.issues.anyone') }}</option>
                <option value="me">{{ __('app.issues.assigned_to_me') }}</option>
                <option value="unassigned">{{ __('app.issues.unassigned') }}</option>
                @foreach ($assignees as $assigneeOption)
                    <option value="{{ $assigneeOption->id }}">{{ $assigneeOption->full_name }}</option>
                @endforeach
            </select>
        </label>

        <x-button variant="ghost" wire:click="clearFilters">{{ __('app.actions.clear') }}</x-button>
    </div>

    @if ($issues->isEmpty())
        <x-empty-state
            :title="__('app.issues.empty_title')"
            :description="__('app.issues.empty_description')" />
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('app.issues.severity') }}</th>
                        <th>{{ __('app.runs.machine') }}</th>
                        <th>{{ __('app.common.description') }}</th>
                        <th>{{ __('app.common.status') }}</th>
                        <th>{{ __('app.issues.assigned_to') }}</th>
                        <th>{{ __('app.issues.raised') }}</th>
                        <th><span class="sr-only">{{ __('app.common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($issues as $issue)
                        <tr wire:key="issue-{{ $issue->id }}" class="hover:bg-slate-50">
                            <td>
                                <x-badge :color="$issue->severity->color()">{{ $issue->severity->label() }}</x-badge>
                            </td>
                            <td>
                                <p class="font-semibold text-slate-900">
                                    @can('view', $issue->machine)
                                        <a href="{{ route('machines.show', ['machine' => $issue->machine->code]) }}"
                                           class="underline decoration-slate-300 underline-offset-2 hover:decoration-slate-900">{{ $issue->machine->name }}</a>
                                    @else
                                        {{ $issue->machine->name }}
                                    @endcan
                                </p>
                                <p class="text-sm text-slate-500">{{ $issue->machine->location->name }}</p>
                            </td>
                            <td class="max-w-md">
                                <a href="{{ route('issues.show', $issue) }}"
                                   class="line-clamp-2 text-slate-800 underline-offset-2 hover:underline">
                                    {{ $issue->description }}
                                </a>
                            </td>
                            <td>
                                <x-badge :color="$issue->status->color()">{{ $issue->status->label() }}</x-badge>
                            </td>
                            <td class="text-slate-700">
                                {{ $issue->assignedTo?->full_name ?? __('app.issues.unassigned') }}
                            </td>
                            <td class="text-slate-700">
                                <p class="tabular-nums">{{ $issue->created_at?->timezone($displayTz)->format('j M Y') }}</p>
                                <p class="text-sm text-slate-500">{{ $issue->raisedBy?->full_name ?? '—' }}</p>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('issues.show', $issue) }}"
                                   class="inline-flex min-h-14 items-center rounded-lg px-4 font-semibold text-sky-700 hover:bg-sky-50">
                                    {{ __('app.actions.view') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $issues->links() }}
        </div>
    @endif

    {{-- Raise an issue that no checklist found --}}
    @can('create', \App\Models\Issue::class)
        <x-modal name="create-issue" :title="__('app.issues.raise_issue')" max-width="xl">
            @if ($creating)
                <p class="text-base text-slate-600">{{ __('app.issues.raise_issue_hint') }}</p>

                <label for="new-machine" class="mt-4 block text-lg font-semibold">{{ __('app.runs.machine') }}</label>
                <select id="new-machine" wire:model="newMachineId" class="input mt-2 min-h-14 w-full">
                    <option value="">{{ __('app.issues.choose_machine') }}</option>
                    @foreach ($this->creatableMachines() as $machineOption)
                        <option value="{{ $machineOption->id }}">{{ $machineOption->name }}</option>
                    @endforeach
                </select>
                @error('newMachineId')
                    <p class="mt-1 text-base text-rose-600">{{ $message }}</p>
                @enderror

                <p class="mt-4 text-lg font-semibold">{{ __('app.issues.severity') }}</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach (IssueSeverity::cases() as $severityOption)
                        <button type="button"
                            wire:key="new-severity-{{ $severityOption->value }}"
                            wire:click="$set('newSeverity', '{{ $severityOption->value }}')"
                            class="min-h-14 rounded-xl border-2 text-lg font-bold transition-colors {{ $newSeverity === $severityOption->value ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 text-slate-700' }}"
                            aria-pressed="{{ $newSeverity === $severityOption->value ? 'true' : 'false' }}">
                            {{ $severityOption->label() }}
                        </button>
                    @endforeach
                </div>
                @error('newSeverity')
                    <p class="mt-1 text-base text-rose-600">{{ $message }}</p>
                @enderror

                <label for="new-description" class="mt-4 block text-lg font-semibold">{{ __('app.issues.what_is_wrong') }}</label>
                <x-textarea id="new-description" wire:model="newDescription" rows="4" maxlength="2000" class="mt-2 w-full" />
                @error('newDescription')
                    <p class="mt-1 text-base text-rose-600">{{ $message }}</p>
                @enderror

                <div class="mt-6 flex gap-3">
                    <x-button variant="ghost" class="flex-1" wire:click="cancelCreate">{{ __('app.actions.cancel') }}</x-button>
                    <x-button class="flex-1" wire:click="createIssue" wire:target="createIssue" wire:loading.attr="disabled">
                        {{ __('app.issues.raise_issue') }}
                    </x-button>
                </div>
            @endif
        </x-modal>
    @endcan
</div>
