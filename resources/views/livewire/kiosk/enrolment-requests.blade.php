<div>
    <x-slot:header>
        <x-page-header :title="__('app.kiosk_requests.queue_title')" :description="__('app.kiosk_requests.queue_intro')" />
    </x-slot:header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    @if ($pendingRequests->isEmpty())
        <x-empty-state
            :title="__('app.kiosk_requests.queue_empty_title')"
            :description="__('app.kiosk_requests.queue_empty_description')"
        />
    @else
        <div class="space-y-4">
            @foreach ($pendingRequests as $request)
                <x-card wire:key="req-{{ $request->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-lg font-bold">{{ $request->suggested_name }}</p>
                            <p class="mt-1 text-slate-600">
                                {{ __('app.kiosk_requests.asked_by', [
                                    'name' => $request->requestedBy?->full_name ?? '—',
                                    'when' => $request->created_at->diffForHumans(),
                                ]) }}
                            </p>
                        </div>
                        <x-status-dot :status="$request->status" />
                    </div>

                    @if ($request->note)
                        <p class="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-slate-700">{{ $request->note }}</p>
                    @endif

                    {{--
                        Client-supplied and forgeable — it exists so a human can
                        tell one black tablet from another, and authorises
                        nothing. Same rule as the fleet screen.
                    --}}
                    @php($summary = \App\Support\DeviceReport::summarise($request->device_info))
                    @if ($summary !== '')
                        <p class="mt-3 text-sm text-slate-500">{{ $summary }}</p>
                    @endif

                    @if ($approvingId === $request->id)
                        <div class="mt-5 border-t border-slate-200 pt-5">
                            <x-label for="deviceName-{{ $request->id }}" required>{{ __('app.kiosk_devices.name') }}</x-label>
                            <x-input id="deviceName-{{ $request->id }}" wire:model="deviceName" class="mt-1 w-full" maxlength="120" />
                            <p class="mt-1 text-sm text-slate-500">{{ __('app.kiosk_requests.approve_name_hint') }}</p>
                            @error('deviceName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                            <div class="mt-4 flex flex-wrap gap-3">
                                <x-button wire:click="approve">{{ __('app.kiosk_requests.confirm_approve') }}</x-button>
                                <x-button variant="ghost" wire:click="cancel">{{ __('app.actions.cancel') }}</x-button>
                            </div>
                        </div>
                    @elseif ($decliningId === $request->id)
                        <div class="mt-5 border-t border-slate-200 pt-5">
                            <x-label for="declineReason-{{ $request->id }}" required>{{ __('app.kiosk_requests.decline_reason') }}</x-label>
                            <x-textarea id="declineReason-{{ $request->id }}" wire:model="declineReason" :rows="2" class="mt-1 w-full" maxlength="500" />
                            <p class="mt-1 text-sm text-slate-500">{{ __('app.kiosk_requests.decline_reason_hint') }}</p>
                            @error('declineReason') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror

                            <div class="mt-4 flex flex-wrap gap-3">
                                <x-button variant="danger" wire:click="decline">{{ __('app.kiosk_requests.confirm_decline') }}</x-button>
                                <x-button variant="ghost" wire:click="cancel">{{ __('app.actions.cancel') }}</x-button>
                            </div>
                        </div>
                    @else
                        <div class="mt-5 flex flex-wrap gap-3">
                            <x-button wire:click="startApprove({{ $request->id }})">{{ __('app.kiosk_requests.approve') }}</x-button>
                            <x-button variant="ghost" wire:click="startDecline({{ $request->id }})">{{ __('app.kiosk_requests.decline') }}</x-button>
                        </div>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif

    @if ($decided->isNotEmpty())
        <h2 class="mt-10 text-lg font-bold">{{ __('app.kiosk_requests.recent_title') }}</h2>
        <p class="mt-1 text-slate-600">{{ __('app.kiosk_requests.recent_intro') }}</p>

        <div class="mt-4 space-y-3">
            @foreach ($decided as $request)
                <x-card wire:key="done-{{ $request->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $request->device?->name ?? $request->suggested_name }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ __('app.kiosk_requests.reviewed_by', [
                                    'name' => $request->reviewedBy?->full_name ?? '—',
                                    'when' => $request->reviewed_at?->diffForHumans() ?? '—',
                                ]) }}
                            </p>
                        </div>
                        <x-status-dot :status="$request->status" />
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
