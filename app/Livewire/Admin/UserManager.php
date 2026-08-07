<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Site;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * User administration (route `admin.users`), gated on `user.manage` — which
 * only the `admin` role holds, so this screen is admin-only by construction
 * rather than by a separate check.
 *
 * Until now there was no way to create a person. Operators, their PINs and
 * their roles came from a seeder or tinker, which meant a new starter on a
 * Monday needed a developer.
 *
 * Two things this screen will not let an administrator do, because both are
 * unrecoverable from inside the application:
 *
 *   - lock themselves out (deactivate, delete or demote their own account)
 *   - remove the last active administrator
 *
 * `UserPolicy::before()` grants admins everything, so those guards live here
 * and are re-checked on every action rather than relying on the policy.
 */
#[Layout('layouts::app')]
class UserManager extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    /** The four roles from BUILD-CONTRACT §5, most privileged last. */
    private const ROLES = ['operator', 'supervisor', 'maintenance_manager', 'admin'];

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'role')]
    public string $roleFilter = '';

    #[Url(as: 'inactive')]
    public bool $includeInactive = false;

    // ── Create / edit form ───────────────────────────────────────────

    public ?int $editingId = null;

    public string $fullName = '';

    public string $employeeNumber = '';

    public string $email = '';

    public string $role = 'operator';

    public string $siteId = '';

    public bool $isActive = true;

    /** Left blank on edit means "leave the existing one alone". */
    public string $password = '';

    public string $pin = '';

    // ── Confirmations ────────────────────────────────────────────────

    public ?int $deletingId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updating(string $property, mixed $value): void
    {
        if (in_array($property, ['search', 'roleFilter', 'includeInactive'], true)) {
            $this->resetPage();
        }
    }

    // ── Data ─────────────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function roles(): array
    {
        // Read from the database so a role added later shows up, but ordered
        // by the contract's hierarchy rather than alphabetically — "admin"
        // sorting above "operator" would read as a ranking and is not one.
        $existing = Role::query()->pluck('name')->all();

        return array_values(array_filter(self::ROLES, fn (string $role) => in_array($role, $existing, true)));
    }

    /**
     * @return Collection<int, Site>
     */
    #[Computed]
    public function sites(): Collection
    {
        return Site::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function deletingUser(): ?User
    {
        return $this->deletingId === null ? null : User::query()->find($this->deletingId);
    }

    /**
     * Active administrators, counted so the last one cannot be removed.
     */
    private function activeAdminCount(): int
    {
        return User::query()->where('is_active', true)->role('admin')->count();
    }

    /**
     * True when changing this user would leave nobody able to administer the
     * system.
     */
    private function isLastActiveAdmin(User $user): bool
    {
        return $user->is_active
            && $user->hasRole('admin')
            && $this->activeAdminCount() <= 1;
    }

    // ── Create / edit ────────────────────────────────────────────────

    public function openCreateModal(): void
    {
        $this->authorize('create', User::class);

        $this->resetForm();
        $this->dispatch('open-modal', name: 'user-form');
    }

    public function openEditModal(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->authorize('update', $user);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->fullName = $user->full_name;
        $this->employeeNumber = $user->employee_number;
        $this->email = $user->email ?? '';
        $this->role = $user->roles->first()?->name ?? 'operator';
        $this->siteId = (string) ($user->default_site_id ?? '');
        $this->isActive = $user->is_active;

        $this->dispatch('open-modal', name: 'user-form');
    }

    public function save(): void
    {
        $editing = $this->editingId !== null
            ? User::query()->findOrFail($this->editingId)
            : null;

        $this->authorize($editing !== null ? 'update' : 'create', $editing ?? User::class);

        $this->validate();

        // Guards before anything is written. An administrator who removes
        // their own admin role, or switches off their own account, cannot
        // put it back — there is no other screen.
        if ($editing !== null && $editing->id === auth()->id()) {
            if ($this->role !== 'admin') {
                $this->addError('role', __('app.users.cannot_demote_self'));

                return;
            }

            if (! $this->isActive) {
                $this->addError('isActive', __('app.users.cannot_deactivate_self'));

                return;
            }
        }

        if ($editing !== null && $this->isLastActiveAdmin($editing) && ($this->role !== 'admin' || ! $this->isActive)) {
            $this->addError('role', __('app.users.cannot_remove_last_admin'));

            return;
        }

        $data = [
            'full_name' => trim($this->fullName),
            'employee_number' => trim($this->employeeNumber),
            'email' => trim($this->email) !== '' ? trim($this->email) : null,
            'default_site_id' => $this->siteId !== '' ? (int) $this->siteId : null,
            'is_active' => $this->isActive,
        ];

        // The 'hashed' cast on both columns does the hashing on assignment;
        // a blank field means "leave whatever is there", so that editing a
        // name does not wipe somebody's PIN.
        if ($this->password !== '') {
            $data['password'] = $this->password;
        }

        if ($this->pin !== '') {
            $data['pin'] = $this->pin;
            $data['pin_set_at'] = now();
        }

        $user = DB::transaction(function () use ($editing, $data): User {
            $user = $editing ?? new User;
            $user->fill($data);
            $user->save();

            $user->syncRoles([$this->role]);

            return $user;
        });

        session()->flash('flash.success', $editing !== null
            ? __('app.users.updated_message', ['name' => $user->full_name])
            : __('app.users.created_message', ['name' => $user->full_name]));

        $this->dispatch('close-modal', name: 'user-form');
        $this->resetForm();
    }

    /**
     * Clear somebody's PIN — the "they left it on a whiteboard" action.
     * There is no way to read a PIN back, so this is a reset, not a reveal.
     */
    public function clearPin(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->authorize('update', $user);

        DB::transaction(fn () => $user->forceFill(['pin' => null, 'pin_set_at' => null])->save());

        activity('user')
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->log('user.pin_cleared');

        session()->flash('flash.success', __('app.users.pin_cleared', ['name' => $user->full_name]));
    }

    public function toggleActive(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            session()->flash('flash.error', __('app.users.cannot_deactivate_self'));

            return;
        }

        if ($user->is_active && $this->isLastActiveAdmin($user)) {
            session()->flash('flash.error', __('app.users.cannot_remove_last_admin'));

            return;
        }

        DB::transaction(fn () => $user->update(['is_active' => ! $user->is_active]));

        session()->flash('flash.success', $user->is_active
            ? __('app.users.activated_message', ['name' => $user->full_name])
            : __('app.users.deactivated_message', ['name' => $user->full_name]));
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function confirmDelete(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        $this->authorize('update', $user);

        $this->deletingId = $user->id;
        unset($this->deletingUser);

        $this->dispatch('open-modal', name: 'confirm-delete-user');
    }

    /**
     * Soft delete only. Runs, signatures and issues reference users with
     * `nullOnDelete`, so a hard delete would strip the name off signed work
     * — the record must keep saying who did it.
     */
    public function deleteUser(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $user = User::query()->findOrFail($this->deletingId);

        if ($user->id === auth()->id()) {
            session()->flash('flash.error', __('app.users.cannot_delete_self'));
            $this->closeDelete();

            return;
        }

        $this->authorize('delete', $user);

        if ($this->isLastActiveAdmin($user)) {
            session()->flash('flash.error', __('app.users.cannot_remove_last_admin'));
            $this->closeDelete();

            return;
        }

        $name = $user->full_name;

        DB::transaction(function () use ($user): void {
            // Deactivate as well as soft-delete: `is_active` is what the
            // login and the kiosk PIN pad check, and a restored row should
            // not silently be able to sign in again.
            $user->update(['is_active' => false]);
            $user->delete();
        });

        session()->flash('flash.success', __('app.users.deleted_message', ['name' => $name]));

        $this->closeDelete();
    }

    private function closeDelete(): void
    {
        $this->dispatch('close-modal', name: 'confirm-delete-user');
        $this->deletingId = null;
        unset($this->deletingUser);
    }

    // ── Render ───────────────────────────────────────────────────────

    public function render(): View
    {
        $users = User::query()
            ->with('roles:id,name')
            ->when(! $this->includeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->when($this->roleFilter !== '', fn (Builder $q) => $q->role($this->roleFilter))
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.addcslashes($this->search, '\\%_').'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->where('full_name', 'like', $term)
                        ->orWhere('employee_number', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderBy('full_name')
            ->paginate(20);

        return view('livewire.admin.user-manager', [
            'users' => $users,
        ])->title(__('app.users.title'));
    }

    // ── Validation ───────────────────────────────────────────────────

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:160'],
            // Soft-deleted rows keep their unique index, so the rule does not
            // exclude them — reusing a departed employee's number would
            // attach new work to an old person's history.
            'employeeNumber' => [
                'required',
                'string',
                'max:32',
                Rule::unique('users', 'employee_number')->ignore($this->editingId),
            ],
            'email' => [
                'nullable',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            'role' => ['required', Rule::in($this->roles())],
            'siteId' => ['nullable', Rule::exists('sites', 'id')],
            'isActive' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            // Digits only: the kiosk pad has no letters on it.
            'pin' => ['nullable', 'digits_between:4,6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'fullName' => __('app.users.full_name'),
            'employeeNumber' => __('app.users.employee_number'),
            'email' => __('app.users.email'),
            'role' => __('app.users.role'),
            'siteId' => __('app.users.default_site'),
            'password' => __('app.users.password'),
            'pin' => __('app.users.pin'),
        ];
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'fullName', 'employeeNumber', 'email', 'role', 'siteId', 'isActive', 'password', 'pin');
        $this->resetValidation();
    }
}
