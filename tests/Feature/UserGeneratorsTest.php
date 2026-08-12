<?php

declare(strict_types=1);

use App\Livewire\Admin\UserManager;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Generating an employee number, a password and a PIN
|--------------------------------------------------------------------------
| The numbering is a convention people read at a glance — OP-1001 is an
| operator, SUP-2001 a supervisor — so a generator that ignores the blocks is
| worse than typing them by hand.
*/

function generatorAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('numbers each role in its own block', function (string $role, string $expected): void {
    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->set('role', $role)
        ->call('generateEmployeeNumber')
        ->assertSet('employeeNumber', $expected);
})->with([
    [Roles::OPERATOR, 'OP-1001'],
    [Roles::SUPERVISOR, 'SUP-2001'],
    [Roles::MAINTENANCE_MANAGER, 'MM-3001'],
    [Roles::QUALITY_ASSURANCE, 'QA-4001'],
]);

it('pads the admin block, which starts at zero', function (): void {
    // ADMIN-0001, not ADMIN-1. The others start at 1000 and need no padding.
    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->set('role', Roles::ADMIN)
        ->call('generateEmployeeNumber')
        ->assertSet('employeeNumber', 'ADMIN-0001');
});

it('continues from the highest number already issued', function (): void {
    User::factory()->create(['employee_number' => 'OP-1007']);

    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->set('role', Roles::OPERATOR)
        ->call('generateEmployeeNumber')
        ->assertSet('employeeNumber', 'OP-1008');
});

it('does not reuse a departed employee number', function (): void {
    // Soft-deleted rows keep their unique index, and reusing the number would
    // attach new work to an old person's history.
    $gone = User::factory()->create(['employee_number' => 'OP-1042']);
    $gone->delete();

    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->set('role', Roles::OPERATOR)
        ->call('generateEmployeeNumber')
        ->assertSet('employeeNumber', 'OP-1043');
});

it('is not confused by a hand-typed number in another format', function (): void {
    User::factory()->create(['employee_number' => 'OP-CASUAL']);
    User::factory()->create(['employee_number' => 'OP-1003']);

    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->set('role', Roles::OPERATOR)
        ->call('generateEmployeeNumber')
        ->assertSet('employeeNumber', 'OP-1004');
});

it('generates a password long enough to pass its own validation', function (): void {
    $component = Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('generatePassword');

    $password = $component->get('password');

    expect(strlen($password))->toBe(16)
        ->and($component->get('passwordGenerated'))->toBeTrue();
});

it('generates a four-digit PIN and keeps a leading zero', function (): void {
    // The column is a string and "0421" is a valid PIN; generating a number
    // and formatting it would silently drop the zero.
    $pins = collect(range(1, 8))->map(function () {
        return Livewire::actingAs(generatorAdmin())
            ->test(UserManager::class)
            ->call('generatePin')
            ->get('pin');
    });

    expect($pins->every(fn (string $p): bool => strlen($p) === 4 && ctype_digit($p)))->toBeTrue()
        ->and($pins->unique()->count())->toBeGreaterThan(1);
});

it('saves a user created entirely from generated values', function (): void {
    $component = Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Generated Operator')
        ->set('role', Roles::OPERATOR)
        ->call('generateEmployeeNumber')
        ->call('generatePassword')
        ->call('generatePin');

    $password = $component->get('password');
    $pin = $component->get('pin');

    $component->call('save')->assertHasNoErrors();

    $user = User::query()->where('full_name', 'Generated Operator')->firstOrFail();

    expect($user->employee_number)->toBe('OP-1001')
        ->and(Illuminate\Support\Facades\Hash::check($password, $user->password))->toBeTrue()
        ->and(Illuminate\Support\Facades\Hash::check($pin, $user->pin))->toBeTrue();
});

it('clears generated values when the form is reopened', function (): void {
    // Otherwise the next user created inherits the previous one's password.
    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('generatePassword')
        ->call('generatePin')
        ->call('openCreateModal')
        ->assertSet('password', '')
        ->assertSet('pin', '')
        ->assertSet('passwordGenerated', false)
        ->assertSet('pinGenerated', false);
});

/*
|--------------------------------------------------------------------------
| Filling itself in
|--------------------------------------------------------------------------
*/

it('fills the employee number in when the create form opens', function (): void {
    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->assertSet('employeeNumber', 'OP-1001');   // operator is the default role
});

it('does not fill in a password, because blank is a legitimate answer', function (): void {
    // A shop-floor operator signs in with a PIN and may have no password at
    // all; generating one would create a credential nobody asked for.
    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->assertSet('password', '')
        ->assertSet('pin', '');
});

it('re-derives the number when the role changes', function (): void {
    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->assertSet('employeeNumber', 'OP-1001')
        ->set('role', Roles::SUPERVISOR)
        ->assertSet('employeeNumber', 'SUP-2001');
});

it('leaves a hand-typed number alone when the role changes', function (): void {
    // Changing the role must not silently overwrite a number somebody chose.
    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('employeeNumber', 'CONTRACTOR-7')
        ->set('role', Roles::SUPERVISOR)
        ->assertSet('employeeNumber', 'CONTRACTOR-7');
});

it('does not touch the number when editing an existing user', function (): void {
    $existing = User::factory()->create(['employee_number' => 'OP-1099']);
    $existing->assignRole(Roles::OPERATOR);

    Livewire::actingAs(generatorAdmin())
        ->test(UserManager::class)
        ->call('openEditModal', $existing->id)
        ->assertSet('employeeNumber', 'OP-1099')
        ->set('role', Roles::SUPERVISOR)
        ->assertSet('employeeNumber', 'OP-1099');
});
