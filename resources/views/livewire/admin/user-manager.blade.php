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
    <div class="card mt-6 p-4">
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
        <div class="card mt-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-base">
                    <thead class="bg-slate-50 text-left text-sm font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">{{ __('app.users.full_name') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.users.employee_number') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.users.email') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.users.role') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.users.signs_in_with') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('app.common.status') }}</th>
                            <th scope="col" class="px-4 py-3 text-right">{{ __('app.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td class="px-4 py-3 font-semibold">
                                    {{ $user->full_name }}
                                    @if ($user->id === auth()->id())
                                        <span class="ml-1 text-sm font-normal text-slate-500">({{ __('app.users.you') }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 tabular-nums">{{ $user->employee_number }}</td>
                                <td class="px-4 py-3">{{ $user->email ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @foreach ($user->roles as $role)
                                        <x-badge>{{ __('app.roles.'.$role->name) }}</x-badge>
                                    @endforeach
                                </td>

                                {{--
                                    How this person actually gets in. A floor operator with
                                    no email and no password can only use the kiosk pad, and
                                    that is the single most common support question.
                                --}}
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    @php($methods = [])
                                    @if ($user->password) @php($methods[] = __('app.users.method_password')) @endif
                                    @if ($user->pin) @php($methods[] = __('app.users.method_pin')) @endif
                                    {{ $methods === [] ? __('app.users.method_none') : implode(' · ', $methods) }}
                                </td>

                                <td class="px-4 py-3">
                                    @if ($user->is_active)
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

                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <x-button variant="ghost" wire:click="openEditModal({{ $user->id }})">
                                            {{ __('app.actions.edit') }}
                                        </x-button>

                                        @if ($user->pin)
                                            <x-button variant="ghost" wire:click="clearPin({{ $user->id }})">
                                                {{ __('app.users.clear_pin') }}
                                            </x-button>
                                        @endif

                                        @if ($user->id !== auth()->id())
                                            <x-button variant="ghost" wire:click="toggleActive({{ $user->id }})">
                                                {{ $user->is_active ? __('app.actions.deactivate') : __('app.actions.activate') }}
                                            </x-button>
                                            <x-button variant="danger" wire:click="confirmDelete({{ $user->id }})">
                                                {{ __('app.actions.delete') }}
                                            </x-button>
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
                    <x-input id="user-number" wire:model="employeeNumber" maxlength="32" class="w-full" />
                    <p class="mt-1 text-sm text-slate-500">{{ __('app.users.employee_number_hint') }}</p>
                    @error('employeeNumber') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="user-email" class="mb-1 block text-base font-semibold">
                    {{ __('app.users.email') }}
                    <span class="font-normal text-slate-500">({{ __('app.common.optional') }})</span>
                </label>
                <x-input id="user-email" type="email" wire:model="email" maxlength="190" class="w-full" />
                <p class="mt-1 text-sm text-slate-500">{{ __('app.users.email_hint') }}</p>
                @error('email') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="user-role" class="mb-1 block text-base font-semibold">{{ __('app.users.role') }}</label>
                    <x-select id="user-role" wire:model="role" class="w-full">
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
                    <x-input id="user-password" type="password" wire:model="password" autocomplete="new-password" class="w-full" />
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $editingId ? __('app.users.password_hint_edit') : __('app.users.password_hint_new') }}
                    </p>
                    @error('password') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-pin" class="mb-1 block text-base font-semibold">{{ __('app.users.pin') }}</label>
                    <x-input id="user-pin" type="password" inputmode="numeric" wire:model="pin" maxlength="6" autocomplete="off" class="w-full" />
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $editingId ? __('app.users.pin_hint_edit') : __('app.users.pin_hint_new') }}
                    </p>
                    @error('pin') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

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
            <p class="text-base text-slate-700">
                {{ __('app.users.delete_confirm', ['name' => $this->deletingUser->full_name]) }}
            </p>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <x-button variant="ghost" x-on:click="show = false">{{ __('app.actions.cancel') }}</x-button>
                <x-button variant="danger" wire:click="deleteUser">{{ __('app.actions.delete') }}</x-button>
            </div>
        @endif
    </x-modal>
</div>
