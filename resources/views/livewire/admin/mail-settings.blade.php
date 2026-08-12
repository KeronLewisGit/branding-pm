<div class="mx-auto w-full max-w-2xl">
    <x-slot:header>
        <x-page-header :title="__('app.mail.title')" />
    </x-slot:header>

    <p class="mb-6 text-lg text-slate-600">{{ __('app.mail.intro') }}</p>

    @if (session('flash.success'))
        <x-alert type="success" class="mb-6">{{ session('flash.success') }}</x-alert>
    @endif

    {{--
        What is in force right now, which is not always what is in the boxes.
        Saved-but-not-enabled is a real state, and without saying so the screen
        would show SendGrid while every email still went through .env.
    --}}
    <x-card class="mb-6">
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('app.mail.in_force') }}</p>
        <p class="mt-1 text-lg font-semibold text-slate-900">
            {{ $setting?->is_active ? $setting->host : $envHost }}
            <span class="text-base font-normal text-slate-500">
                · {{ $setting?->is_active ? __('app.mail.source_app') : __('app.mail.source_env') }}
            </span>
        </p>

        @if ($setting?->last_tested_at)
            <p class="mt-2 text-sm text-slate-500">
                {{ __('app.mail.last_tested', ['when' => $setting->last_tested_at->diffForHumans()]) }}
                — {{ $setting->last_test_result }}
            </p>
        @endif

        @if ($setting?->updatedBy)
            <p class="mt-1 text-sm text-slate-500">
                {{ __('app.mail.last_changed', ['name' => $setting->updatedBy->full_name, 'when' => $setting->updated_at->diffForHumans()]) }}
            </p>
        @endif
    </x-card>

    <x-card>
        <div class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-label for="mail-host" required>{{ __('app.mail.host') }}</x-label>
                    <x-input id="mail-host" wire:model="host" class="mt-1 w-full" maxlength="190" />
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.mail.host_hint') }}</p>
                    @error('host') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="mail-port" required>{{ __('app.mail.port') }}</x-label>
                    <x-input id="mail-port" type="number" wire:model="port" class="mt-1 w-full" />
                    @error('port') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-label for="mail-username">{{ __('app.mail.username') }}</x-label>
                    <x-input id="mail-username" wire:model="username" class="mt-1 w-full" maxlength="190" autocomplete="off" />
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.mail.username_hint') }}</p>
                    @error('username') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="mail-password">{{ __('app.mail.password') }}</x-label>
                    <x-input id="mail-password" type="password" wire:model="password" class="mt-1 w-full font-mono"
                             maxlength="500" autocomplete="new-password"
                             placeholder="{{ $setting?->host ? __('app.mail.password_unchanged') : '' }}" />
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.mail.password_hint') }}</p>
                    @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-label for="mail-encryption">{{ __('app.mail.encryption') }}</x-label>
                    <x-select id="mail-encryption" wire:model="encryption" class="mt-1 w-full">
                        <option value="tls">{{ __('app.mail.encryption_tls') }}</option>
                        <option value="ssl">{{ __('app.mail.encryption_ssl') }}</option>
                        <option value="">{{ __('app.mail.encryption_none') }}</option>
                    </x-select>
                    @error('encryption') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="mail-from-name" required>{{ __('app.mail.from_name') }}</x-label>
                    <x-input id="mail-from-name" wire:model="fromName" class="mt-1 w-full" maxlength="190" />
                    @error('fromName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <x-label for="mail-from" required>{{ __('app.mail.from_address') }}</x-label>
                <x-input id="mail-from" type="email" wire:model="fromAddress" class="mt-1 w-full" maxlength="190" />
                <p class="mt-1 text-sm text-slate-500">{{ __('app.mail.from_address_hint') }}</p>
                @error('fromAddress') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-200 pt-4">
                <x-checkbox wire:model="isActive" id="mail-active">{{ __('app.mail.use_these') }}</x-checkbox>
                <p class="mt-1 text-sm text-slate-500">{{ __('app.mail.use_these_hint') }}</p>
            </div>

            {{-- The reply from the last test, in the provider's own words. --}}
            @if ($testResult !== null)
                <x-alert :type="$testPassed ? 'success' : 'error'">
                    <p class="font-semibold">{{ $testPassed ? __('app.mail.test_ok') : __('app.mail.test_failed') }}</p>
                    <p class="mt-1 break-words font-mono text-sm">{{ $testResult }}</p>
                </x-alert>
            @endif

            <div class="flex flex-wrap gap-3 pt-2">
                {{-- Test first, deliberately listed first: it uses what is in
                     the boxes, so a wrong value is caught before it becomes
                     the relay every password reset depends on. --}}
                <x-button variant="ghost" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest">
                    <span wire:loading.remove wire:target="sendTest">{{ __('app.mail.send_test') }}</span>
                    <span wire:loading wire:target="sendTest">{{ __('app.mail.sending') }}</span>
                </x-button>

                <x-button wire:click="save">{{ __('app.mail.save') }}</x-button>
            </div>
        </div>
    </x-card>
</div>
