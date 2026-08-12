<?php

declare(strict_types=1);

use App\Livewire\Admin\UserManager;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Changing an issued password on first sign-in
|--------------------------------------------------------------------------
| A password somebody else chose is a shared secret from the moment it is
| issued. Requiring the first sign-in to replace it bounds how long that
| matters to a single login.
*/

function changeAdmin(): User
{
    $admin = User::factory()->create(['must_change_password' => false]);
    $admin->assignRole('admin');

    return $admin;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('flags an account created with a password', function (): void {
    Livewire::actingAs(changeAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'New Supervisor')
        ->set('email', 'new@labelhouse.com')
        ->set('role', Roles::SUPERVISOR)
        ->set('password', 'issued-password')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'new@labelhouse.com')->first()->must_change_password)->toBeTrue();
});

it('does not flag a PIN-only operator', function (): void {
    // They have no password to change, and their PIN is cleared by an
    // administrator rather than by them.
    Livewire::actingAs(changeAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Floor Operator')
        ->set('pin', '4821')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('full_name', 'Floor Operator')->first()->must_change_password)->toBeFalse();
});

it('does not flag on an edit', function (): void {
    // Otherwise correcting a typo in somebody's name would lock them out of
    // their own account.
    $existing = User::factory()->create(['must_change_password' => false]);
    $existing->assignRole(Roles::SUPERVISOR);

    Livewire::actingAs(changeAdmin())
        ->test(UserManager::class)
        ->call('openEditModal', $existing->id)
        ->set('password', 'a-reset-password')
        ->call('save')
        ->assertHasNoErrors();

    expect($existing->refresh()->must_change_password)->toBeFalse();
});

it('redirects a flagged user away from everything else', function (): void {
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(Roles::SUPERVISOR);

    $this->actingAs($user)->get(route('runs.index'))->assertRedirect(route('password.change'));
});

it('lets a flagged user reach the change screen and log out', function (): void {
    // Somebody who cannot get past this must still be able to leave, rather
    // than being held in the application by the thing meant to protect them.
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(Roles::SUPERVISOR);

    $this->actingAs($user)->get(route('password.change'))->assertOk();
    $this->actingAs($user)->post(route('logout'))->assertRedirect();
});

it('lets everybody else through', function (): void {
    $user = User::factory()->create(['must_change_password' => false]);
    $user->assignRole(Roles::SUPERVISOR);

    $this->actingAs($user)->get(route('runs.index'))->assertOk();
});

it('changes the password and clears the flag', function (): void {
    $user = User::factory()->create([
        'password' => 'the-issued-one',
        'must_change_password' => true,
    ]);
    $user->assignRole(Roles::SUPERVISOR);

    $this->actingAs($user)->post(route('password.change.store'), [
        'current_password' => 'the-issued-one',
        'password' => 'a-password-of-my-own',
        'password_confirmation' => 'a-password-of-my-own',
    ])->assertRedirect();

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('a-password-of-my-own', $user->password))->toBeTrue();
});

it('requires the current password even when forced', function (): void {
    /*
     * The account was reached with a password that arrived by email, so the
     * person at the keyboard may be whoever read that mailbox. Asking again
     * proves they are the one it was given to.
     */
    $user = User::factory()->create([
        'password' => 'the-issued-one',
        'must_change_password' => true,
    ]);
    $user->assignRole(Roles::SUPERVISOR);

    $this->actingAs($user)->post(route('password.change.store'), [
        'current_password' => 'a-guess',
        'password' => 'a-password-of-my-own',
        'password_confirmation' => 'a-password-of-my-own',
    ])->assertSessionHasErrors('current_password');

    expect($user->refresh()->must_change_password)->toBeTrue();
});

it('does not interrupt a kiosk session', function (): void {
    // Bouncing somebody out of a machine screen mid-shift would stop the work
    // this system exists to record, and a shared tablet is not where a new
    // password should be typed.
    $user = User::factory()->create(['must_change_password' => true]);
    $user->assignRole(Roles::OPERATOR);

    $this->actingAs($user)
        ->withSession(['kiosk.authenticated_at' => now()->timestamp])
        ->get(route('runs.index'))
        ->assertOk();
});
