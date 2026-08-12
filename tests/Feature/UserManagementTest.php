<?php

declare(strict_types=1);

use App\Livewire\Admin\UserManager;
use App\Models\ChecklistRun;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Admin → Users
|--------------------------------------------------------------------------
| Gated on `user.manage`, which only the admin role holds. The interesting
| tests are the guards: an administrator must not be able to lock themselves,
| or everybody, out of the one screen that could undo it.
*/

function admin(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole('admin');

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Access
|--------------------------------------------------------------------------
*/

it('is reachable by an administrator and nobody else', function (string $role, bool $allowed): void {
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)->get('/admin/users');

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'operator' => ['operator', false],
    'supervisor' => ['supervisor', false],
    // A maintenance manager runs the plant, not the user list — `user.manage`
    // is deliberately not in their cumulative grant.
    'maintenance manager' => ['maintenance_manager', false],
    'admin' => ['admin', true],
]);

/*
|--------------------------------------------------------------------------
| Creating people
|--------------------------------------------------------------------------
*/

it('creates a PIN-only floor operator with no email and no password', function (): void {
    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->set('fullName', 'Darnell Joseph')
        ->set('employeeNumber', 'OP-1001')
        ->set('email', '')
        ->set('role', 'operator')
        ->set('pin', '4321')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->firstWhere('employee_number', 'OP-1001');

    expect($user)->not->toBeNull()
        ->and($user->email)->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->hasRole('operator'))->toBeTrue()
        // Hashed by the model cast, never stored or shown in the clear.
        ->and($user->pin)->not->toBe('4321')
        ->and(Hash::check('4321', $user->pin))->toBeTrue()
        ->and($user->pin_set_at)->not->toBeNull();
});

it('rejects a PIN that is not 4 to 6 digits', function (string $pin): void {
    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->set('fullName', 'Someone')
        ->set('employeeNumber', 'OP-9999')
        ->set('pin', $pin)
        ->call('save')
        ->assertHasErrors('pin');
})->with(['123', '1234567', 'abcd', '12a4']);

it('will not reuse an employee number', function (): void {
    admin(['employee_number' => 'OP-1001']);

    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->set('fullName', 'Someone Else')
        ->set('employeeNumber', 'OP-1001')
        ->call('save')
        ->assertHasErrors('employeeNumber');
});

/*
|--------------------------------------------------------------------------
| Editing without destroying credentials
|--------------------------------------------------------------------------
*/

it('keeps the existing PIN and password when those fields are left blank', function (): void {
    $operator = User::factory()->create([
        'full_name' => 'Darnell Joseph',
        'pin' => '4321',
        'password' => 'secret-password',
    ]);
    $operator->assignRole('operator');

    $pinBefore = $operator->pin;
    $passwordBefore = $operator->password;

    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->call('openEditModal', $operator->id)
        ->set('fullName', 'Darnell A. Joseph')
        ->call('save')
        ->assertHasNoErrors();

    $operator->refresh();

    // Correcting a spelling must not silently lock somebody out of a kiosk.
    expect($operator->full_name)->toBe('Darnell A. Joseph')
        ->and($operator->pin)->toBe($pinBefore)
        ->and($operator->password)->toBe($passwordBefore);
});

it('replaces a PIN when a new one is typed', function (): void {
    $operator = User::factory()->create(['pin' => '4321']);
    $operator->assignRole('operator');

    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->call('openEditModal', $operator->id)
        ->set('pin', '9876')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('9876', $operator->fresh()->pin))->toBeTrue();
});

it('clears a PIN outright', function (): void {
    $operator = User::factory()->create(['pin' => '4321']);
    $operator->assignRole('operator');

    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->call('clearPin', $operator->id);

    expect($operator->fresh()->pin)->toBeNull()
        ->and($operator->fresh()->pin_set_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The lockout guards
|--------------------------------------------------------------------------
*/

it('will not let an administrator remove their own admin role', function (): void {
    $me = admin();
    admin(); // a second admin, so the last-admin guard is not what fires

    Livewire::actingAs($me)
        ->test(UserManager::class)
        ->call('openEditModal', $me->id)
        ->set('role', 'operator')
        ->call('save')
        ->assertHasErrors('role');

    expect($me->fresh()->hasRole('admin'))->toBeTrue();
});

it('will not let an administrator deactivate or delete themselves', function (): void {
    $me = admin();
    admin();

    Livewire::actingAs($me)
        ->test(UserManager::class)
        ->call('openEditModal', $me->id)
        ->set('isActive', false)
        ->call('save')
        ->assertHasErrors('isActive');

    Livewire::actingAs($me)
        ->test(UserManager::class)
        ->call('confirmDelete', $me->id)
        ->call('deleteUser');

    expect($me->fresh())->not->toBeNull()
        ->and($me->fresh()->is_active)->toBeTrue()
        ->and($me->fresh()->trashed())->toBeFalse();
});

it('will not remove the last active administrator', function (): void {
    $lastAdmin = admin();

    // Only an admin can normally reach this screen, and an admin cannot act
    // on themselves — so the count guard is only reachable when `user.manage`
    // has been granted directly to somebody who is not an administrator.
    // That is exactly when it matters, and it is defence in depth for the
    // self-guard above rather than a duplicate of it.
    $deputy = User::factory()->create();
    $deputy->assignRole('maintenance_manager');
    $deputy->givePermissionTo('user.manage');

    expect(User::query()->where('is_active', true)->role('admin')->count())->toBe(1);

    Livewire::actingAs($deputy)
        ->test(UserManager::class)
        ->call('toggleActive', $lastAdmin->id);

    expect($lastAdmin->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($deputy)
        ->test(UserManager::class)
        ->call('confirmDelete', $lastAdmin->id)
        ->call('deleteUser');

    expect($lastAdmin->fresh()->trashed())->toBeFalse();
});

it('allows an administrator to be deactivated while another one remains', function (): void {
    $actor = admin();
    $spare = admin();

    // Two active admins, so removing one is safe and must NOT be blocked —
    // a guard that fires here would make the role impossible to hand over.
    Livewire::actingAs($actor)
        ->test(UserManager::class)
        ->call('toggleActive', $spare->id);

    expect($spare->fresh()->is_active)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Removal keeps the record
|--------------------------------------------------------------------------
*/

it('soft-deletes and deactivates rather than erasing somebody named on the record', function (): void {
    $operator = User::factory()->create(['full_name' => 'Darnell Joseph']);
    $operator->assignRole('operator');

    /*
     * The run is the point, and this test did not have one. Without work
     * attached it was asserting that everybody is retired, which turned out to
     * be the bug rather than the guarantee: an account named nowhere held its
     * email address for good, and a mistake could never be undone. What has to
     * hold is narrower — somebody the record names is never erased.
     */
    ChecklistRun::factory()->create(['operator_id' => $operator->id]);

    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->call('confirmDelete', $operator->id)
        ->call('deleteUser');

    $trashed = User::withTrashed()->find($operator->id);

    // Runs and signatures reference users with nullOnDelete — a hard delete
    // would strip the name off signed work.
    expect($trashed)->not->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->is_active)->toBeFalse()
        ->and($trashed->full_name)->toBe('Darnell Joseph');
});

/*
|--------------------------------------------------------------------------
| Every seeded role is assignable
|--------------------------------------------------------------------------
| This screen writes with `syncRoles([$role])`, so a role missing from the
| dropdown is not merely unassignable — opening somebody who holds it and
| pressing Save takes it away. Quality Assurance was in exactly that state:
| seeded, held by real people, and absent from a hand-written list here.
*/

it('offers every seeded role, not a hand-written subset', function (): void {
    $offered = Livewire::actingAs(admin())->test(UserManager::class)->instance()->roles();

    expect($offered)->toEqualCanonicalizing(Role::query()->pluck('name')->all());
});

it('does not demote somebody whose role is missing from the dropdown', function (): void {
    $officer = User::factory()->create();
    $officer->assignRole('quality_assurance');

    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->call('openEditModal', $officer->id)
        // The role the form loaded is what Save writes back. If the dropdown
        // cannot represent it, this is where the demotion happens.
        ->assertSet('role', 'quality_assurance')
        ->call('save')
        ->assertHasNoErrors();

    expect($officer->fresh()->hasRole('quality_assurance'))->toBeTrue();
});

it('keeps the most senior role when somebody holds several', function (): void {
    $user = User::factory()->create();
    $user->assignRole('operator');
    $user->assignRole('maintenance_manager');

    // `roles->first()` is row order, not seniority — loading the junior role
    // and saving would silently strip the senior one.
    Livewire::actingAs(admin())
        ->test(UserManager::class)
        ->call('openEditModal', $user->id)
        ->assertSet('role', 'maintenance_manager');
});
