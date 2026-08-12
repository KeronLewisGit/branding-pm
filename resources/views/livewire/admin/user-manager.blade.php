<div>
    <x-page-header :title="__('app.users.title')">
        <x-slot:actions>
            <x-button wire:click="openCreateModal">{{ __('app.users.add_user') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    <p class="mt-2 max-w-3xl text-base text-slate-600">{{ __('app.users.description') }}</p>

    @if (session('flash.success'))
        <x-alert type="success" class="mt-6">{{ session('flash.success') }}</x-alert>
    @endif
    @if (session('flash.error'))
        <x-alert type="error" class="mt-6">{{ session('flash.error') }}</x-alert>
    @endif

    {{-- Filters --}}
    <div class="filter-bar">
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            <x-input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('app.users.search_placeholder') }}"
                aria-label="{{ __('app.actions.search') }}"
                class="w-full md:max-w-xs"
            />

            <x-select wire:model.live="roleFilter" aria-label="{{ __('app.users.role') }}" class="w-full md:w-56">
                <option value="">{{ __('app.users.all_roles') }}</option>
                @foreach ($this->roles as $roleOption)
                    <option value="{{ $roleOption }}">{{ __('app.roles.'.$roleOption) }}</option>
                @endforeach
            </x-select>

            <x-checkbox id="include-inactive" wire:model.live="includeInactive">
                {{ __('app.users.include_inactive') }}
            </x-checkbox>

            {{-- Reachable, but off by default: the everyday list is the people
                 who exist. It matters that it is here at all — a removed
                 account keeps its email and employee number, so without this
                 "that address is already taken" names a user who appears
                 nowhere. --}}
            <x-checkbox id="show-deleted" wire:model.live="showDeleted">
                {{ __('app.users.show_deleted') }}
            </x-checkbox>
        </div>
    </div>

    @if ($users->count() === 0)
        <x-empty-state
            class="mt-6"
            :title="__('app.users.empty_title')"
            :description="__('app.users.empty_description')"
        >
            <x-slot:action>
                <x-button wire:click="openCreateModal">{{ __('app.users.add_user') }}</x-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="table-wrap mt-6">
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('app.users.full_name') }}</th>
                            <th scope="col">{{ __('app.users.employee_number') }}</th>
                            <th scope="col">{{ __('app.users.email') }}</th>
                            <th scope="col">{{ __('app.users.role') }}</th>
                            <th scope="col">{{ __('app.users.signs_in_with') }}</th>
                            <th scope="col">{{ __('app.common.status') }}</th>
                            <th scope="col" class="text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php($removed = $user->trashed())
                            <tr wire:key="user-{{ $user->id }}" @class(['opacity-60' => $removed])>
                                <td class="font-semibold">
                                    {{ $user->full_name }}
                                    @if ($user->id === auth()->id())
                                        <span class="ml-1 text-sm font-normal text-slate-500">({{ __('app.users.you') }})</span>
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ $user->employee_number }}</td>
                                <td>{{ $user->email ?? '—' }}</td>
                                <td>
                                    @foreach ($user->roles as $role)
                                        <x-badge>{{ __('app.roles.'.$role->name) }}</x-badge>
                                    @endforeach
                                </td>

                                {{--
                                    How this person actually gets in. A floor operator with
                                    no email and no password can only use the kiosk pad, and
                                    that is the single most common support question.
                                --}}
                                <td class="text-sm text-slate-600">
                                    @php($methods = [])
                                    @if ($user->password) @php($methods[] = __('app.users.method_password')) @endif
                                    @if ($user->pin) @php($methods[] = __('app.users.method_pin')) @endif
                                    {{ $methods === [] ? __('app.users.method_none') : implode(' · ', $methods) }}
                                </td>

                                <td>
                                    @if ($removed)
                                        {{-- Said plainly, because a removed account still
                                             holds its email and employee number and that is
                                             why a new one cannot reuse them. --}}
                                        <span class="inline-flex items-center gap-2 rounded-full bg-rose-50 px-3 py-1 text-sm font-semibold text-rose-800">
                                            <span class="h-2 w-2 rounded-full bg-rose-500" aria-hidden="true"></span>
                                            {{ __('app.users.deleted_badge') }}
                                        </span>
                                    @elseif ($user->is_active)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-800">
                                            <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                            {{ __('app.common.active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">
                                            <span class="h-2 w-2 rounded-full bg-slate-400" aria-hidden="true"></span>
                                            {{ __('app.common.inactive') }}
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        @if ($removed)
                                            {{-- One action only. Editing, clearing a PIN or
                                                 deactivating a removed account are all
                                                 answers to questions nobody asked; putting
                                                 it back is the only useful move. --}}
                                            <x-icon-button
                                                icon="restore"
                                                :label="__('app.users.restore')"
                                                wire:click="restoreUser({{ $user->id }})"
                                            />
                                        @else
                                        <x-icon-button icon="edit" :label="__('app.actions.edit')"
                                            wire:click="openEditModal({{ $user->id }})" />

                                        {{--
                                            Actions you cannot use are greyed out and disabled
                                            rather than dropped. Every row then has the same
                                            icons in the same places, and the tooltip says why
                                            this one is unavailable instead of leaving a gap
                                            the reader has to interpret.
                                        --}}
                                        @php($isSelf = $user->id === auth()->id())

                                        <x-icon-button
                                            icon="pin"
                                            :label="$user->pin ? __('app.users.clear_pin') : __('app.users.no_pin_to_clear')"
                                            :disabled="! $user->pin"
                                            wire:click="clearPin({{ $user->id }})"
                                        />

                                        <x-icon-button
                                            icon="restore"
                                            :label="__('app.walkthrough.reset_for_user')"
                                            wire:click="resetWalkthrough({{ $user->id }})"
                                        />

                                        <x-icon-button
                                            :icon="$user->is_active ? 'deactivate' : 'activate'"
                                            :label="$isSelf
                                                ? __('app.users.cannot_deactivate_self')
                                                : ($user->is_active ? __('app.actions.deactivate') : __('app.actions.activate'))"
                                            :disabled="$isSelf"
                                            wire:click="toggleActive({{ $user->id }})"
                                        />

                                        <x-icon-button
                                            icon="delete"
                                            variant="danger"
                                            :label="$isSelf ? __('app.users.cannot_delete_self') : __('app.actions.delete')"
                                            :disabled="$isSelf"
                                            wire:click="confirmDelete({{ $user->id }})"
                                        />
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>
    @endif

    {{-- Create / edit --}}
    <x-modal name="user-form" max-width="2xl" :title="$editingId ? __('app.users.edit_user') : __('app.users.add_user')">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="user-name" class="mb-1 block text-base font-semibold">{{ __('app.users.full_name') }}</label>
                    <x-input id="user-name" wire:model="fullName" maxlength="160" class="w-full" />
                    @error('fullName') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-number" class="mb-1 block text-base font-semibold">{{ __('app.users.employee_number') }}</label>
                    <div class="flex gap-2">
                        <x-input id="user-number" wire:model.live.debounce.400ms="employeeNumber" maxlength="32" class="w-full" />
                        {{-- Reads the highest number already issued in this role's
                             block, so it cannot collide with one typed by hand. --}}
                        <x-button type="button" variant="ghost" class="shrink-0" wire:click="generateEmployeeNumber">
                            {{ __('app.users.generate') }}
                        </x-button>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.users.employee_number_hint') }}</p>
                    @error('employeeNumber') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="user-email" class="mb-1 block text-base font-semibold">
                    {{ __('app.users.email') }}
                    <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                </label>
                <x-input id="user-email" type="email" wire:model.live.debounce.400ms="email" maxlength="190" class="w-full" />
                <p class="mt-1 text-sm text-slate-500">{{ __('app.users.email_hint') }}</p>
                @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="user-role" class="mb-1 block text-base font-semibold">{{ __('app.users.role') }}</label>
                    <x-select id="user-role" wire:model.live="role" class="w-full">
                        @foreach ($this->roles as $roleOption)
                            <option value="{{ $roleOption }}">{{ __('app.roles.'.$roleOption) }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.users.role_hint') }}</p>
                    @error('role') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-site" class="mb-1 block text-base font-semibold">
                        {{ __('app.users.default_site') }}
                        <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                    </label>
                    <x-select id="user-site" wire:model="siteId" class="w-full">
                        <option value="">{{ __('app.common.none') }}</option>
                        @foreach ($this->sites as $site)
                            <option value="{{ $site->id }}">{{ $site->name }}</option>
                        @endforeach
                    </x-select>
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.users.default_site_hint') }}</p>
                    @error('siteId') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
                <div>
                    <label for="user-password" class="mb-1 block text-base font-semibold">{{ __('app.users.password') }}</label>
                    <div class="flex gap-2">
                        {{-- Shown as text once generated: a password the
                             administrator cannot read is one they cannot pass on,
                             and it is never displayed again after saving. --}}
                        <x-input id="user-password"
                                 type="{{ $passwordGenerated ? 'text' : 'password' }}"
                                 wire:model="password" autocomplete="new-password" class="w-full font-mono" />
                        <x-button type="button" variant="ghost" class="shrink-0" wire:click="generatePassword">
                            {{ __('app.users.generate') }}
                        </x-button>
                    </div>
                    @if ($passwordGenerated)
                        <p class="mt-1 text-sm font-semibold text-amber-700">{{ __('app.users.generated_write_down') }}</p>
                    @endif
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $editingId ? __('app.users.password_hint_edit') : __('app.users.password_hint_new') }}
                    </p>
                    @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-pin" class="mb-1 block text-base font-semibold">{{ __('app.users.pin') }}</label>
                    <div class="flex gap-2">
                        <x-input id="user-pin"
                                 type="{{ $pinGenerated ? 'text' : 'password' }}"
                                 inputmode="numeric" wire:model="pin" maxlength="6" autocomplete="off"
                                 class="w-full font-mono" />
                        <x-button type="button" variant="ghost" class="shrink-0" wire:click="generatePin">
                            {{ __('app.users.generate') }}
                        </x-button>
                    </div>
                    @if ($pinGenerated)
                        <p class="mt-1 text-sm font-semibold text-amber-700">{{ __('app.users.generated_write_down') }}</p>
                    @endif
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $editingId ? __('app.users.pin_hint_edit') : __('app.users.pin_hint_new') }}
                    </p>
                    @error('pin') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>


            {{-- Create only, and only with an address to send to. On an edit
                 there is nothing to send: the stored password is a hash, so
                 the plaintext exists solely in the request that set it. --}}
            @if (! $editingId)
                <div class="border-t border-slate-200 pt-4">
                    <x-checkbox id="user-send-credentials" wire:model="sendCredentials"
                                :disabled="trim($email) === ''">
                        {{ __('app.users.send_credentials') }}
                    </x-checkbox>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ trim($email) === ''
                            ? __('app.users.send_credentials_no_email')
                            : __('app.users.send_credentials_hint') }}
                    </p>
                </div>
            @endif
            <div>
                <x-checkbox id="user-active" wire:model="isActive">{{ __('app.common.active') }}</x-checkbox>
                <p class="mt-1 text-sm text-slate-500">{{ __('app.users.is_active_hint') }}</p>
                @error('isActive') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button type="submit">{{ $editingId ? __('app.actions.update') : __('app.actions.create') }}</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Delete confirmation --}}
    <x-modal name="confirm-delete-user" :title="__('app.users.delete_user')">
        @if ($this->deletingUser)
            {{-- Which of the two is about to happen, said before the click.
                 "This can be undone" and "this cannot" are different
                 decisions. --}}
            <p class="text-base text-slate-700">
                {{ $this->deletingKeepsRecord
                    ? __('app.users.delete_keeps_record', ['name' => $this->deletingUser->full_name])
                    : __('app.users.delete_removes', ['name' => $this->deletingUser->full_name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="deleteUser">{{ __('app.actions.delete') }}</x-button>
            </div>
        @endif
    </x-modal>
</div>
