<?php

declare(strict_types=1);

use App\Livewire\Admin\UserManager;
use App\Models\MailSetting;
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

it('tells them to change it, and to keep it to themselves', function (): void {
    $user = User::factory()->create();

    $rendered = (string) (new AccountCredentials('the-password', null))->toMail($user)->render();

    expect($rendered)->toContain(__('app.credentials_mail.first_login'))
        ->and($rendered)->toContain(__('app.credentials_mail.keep_safe'));
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

/*
|--------------------------------------------------------------------------
| Copying somebody in
|--------------------------------------------------------------------------
| A setting rather than an address in the source: a name in a repository is a
| published personal address, and one that goes quietly wrong when that person
| changes role. Worth knowing what a copy means — that mailbox accumulates the
| credentials of everybody ever created.
*/

it('copies the address configured in mail settings', function (): void {
    MailSetting::query()->create([
        'transport' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'from_address' => 'pm@labelhouse.com',
        'from_name' => 'Branding PM',
        'credentials_cc' => 'records@labelhouse.com',
        'is_active' => true,
    ]);
    MailSetting::forget();

    $user = User::factory()->create(['email' => 'newstarter@labelhouse.com']);
    $message = (new AccountCredentials('the-password', null))->toMail($user);

    expect(collect($message->cc)->flatten())->toContain('records@labelhouse.com');
});

it('copies nobody when no address is configured', function (): void {
    $message = (new AccountCredentials('the-password', null))->toMail(User::factory()->create());

    expect($message->cc)->toBeEmpty();
});

it('copies the address even while .env is still doing the relaying', function (): void {
    /*
     * `row()` rather than `active()`. Who gets a copy is a property of the
     * mail, not of the route it takes — an address saved while the relay is
     * still switched off would otherwise be ignored in silence, and the person
     * expecting a copy finds out by never receiving one.
     */
    MailSetting::query()->create([
        'transport' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'from_address' => 'pm@labelhouse.com',
        'from_name' => 'Branding PM',
        'credentials_cc' => 'records@labelhouse.com',
        'is_active' => false,
    ]);
    MailSetting::forget();

    $message = (new AccountCredentials('the-password', null))->toMail(User::factory()->create());

    expect(collect($message->cc)->flatten())->toContain('records@labelhouse.com');
});

it('does not copy somebody on their own email', function (): void {
    // Where the person configured to keep the record is also the person being
    // set up, one copy is enough.
    MailSetting::query()->create([
        'transport' => 'smtp',
        'host' => 'smtp.example.com',
        'port' => 587,
        'from_address' => 'pm@labelhouse.com',
        'from_name' => 'Branding PM',
        'credentials_cc' => 'records@labelhouse.com',
        'is_active' => true,
    ]);
    MailSetting::forget();

    $user = User::factory()->create(['email' => 'records@labelhouse.com']);
    $message = (new AccountCredentials('the-password', null))->toMail($user);

    expect($message->cc)->toBeEmpty();
});
