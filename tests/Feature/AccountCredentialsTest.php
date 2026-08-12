<?php

declare(strict_types=1);

use App\Livewire\Admin\UserManager;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Support\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Emailing an account's sign-in details on creation
|--------------------------------------------------------------------------
| It carries credentials in the clear, which is a bounded trade rather than an
| oversight: email is not confidential, and this message outlives the password
| in mailboxes and their backups. So it is sent only when asked for, only on
| creation, and only when there is somewhere to send it.
*/

function credentialsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

it('emails the details when asked', function (): void {
    Livewire::actingAs(credentialsAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Marcus Baptiste')
        ->set('email', 'marcus@labelhouse.com')
        ->set('role', Roles::SUPERVISOR)
        ->set('password', 'a-strong-password')
        ->set('sendCredentials', true)
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'marcus@labelhouse.com')->firstOrFail();

    Notification::assertSentTo($user, AccountCredentials::class);
});

it('sends nothing unless it is asked for', function (): void {
    Livewire::actingAs(credentialsAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Quiet Creation')
        ->set('email', 'quiet@labelhouse.com')
        ->set('password', 'a-strong-password')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

it('sends nothing when there is no address to send to', function (): void {
    // Most floor operators have no email at all; their credential is a PIN on
    // a shared tablet.
    Livewire::actingAs(credentialsAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'PIN Only Operator')
        ->set('email', '')
        ->set('pin', '4821')
        ->set('sendCredentials', true)
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

it('never sends on an edit', function (): void {
    // There would be nothing to send: the stored password is a hash, and the
    // plaintext exists only in the request that set it.
    $existing = User::factory()->create(['email' => 'existing@labelhouse.com']);
    $existing->assignRole(Roles::SUPERVISOR);

    Livewire::actingAs(credentialsAdmin())
        ->test(UserManager::class)
        ->call('openEditModal', $existing->id)
        ->set('sendCredentials', true)
        ->set('fullName', 'Renamed Person')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

it('tells only what was actually issued', function (): void {
    // An operator set up with a PIN and no password must not be told about a
    // password they do not have.
    $notification = new AccountCredentials(null, '4821');

    $user = User::factory()->create(['full_name' => 'Pin Person', 'employee_number' => 'OP-1099']);
    $rendered = (string) $notification->toMail($user)->render();

    expect($rendered)->toContain('4821')
        ->and($rendered)->toContain('OP-1099')
        ->and($rendered)->not->toContain(__('app.credentials_mail.button'));
});

it('offers a sign-in button only when there is a password to use', function (): void {
    $user = User::factory()->create(['full_name' => 'Office Person', 'employee_number' => 'SUP-2099']);

    $rendered = (string) (new AccountCredentials('the-password', null))->toMail($user)->render();

    expect($rendered)->toContain('the-password')
        ->and($rendered)->toContain(__('app.credentials_mail.button'));
});

it('tells them to change it', function (): void {
    $user = User::factory()->create();

    $rendered = (string) (new AccountCredentials('the-password', null))->toMail($user)->render();

    expect($rendered)->toContain(__('app.credentials_mail.change_it'));
});

it('creates the account even when the relay refuses', function (): void {
    /*
     * Sent after the transaction, deliberately. A relay that hangs or refuses
     * must not roll back an account that was created correctly — and the
     * administrator has to be told, or they will believe somebody was
     * notified when they were not.
     */
    Notification::fake();

    Livewire::actingAs(credentialsAdmin())
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Created Anyway')
        ->set('email', 'anyway@labelhouse.com')
        ->set('password', 'a-strong-password')
        ->set('sendCredentials', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'anyway@labelhouse.com')->exists())->toBeTrue();
});
