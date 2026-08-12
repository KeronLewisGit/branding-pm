<?php

declare(strict_types=1);

use App\Livewire\Admin\MailSettings;
use App\Models\MailSetting;
use App\Models\User;
use App\Support\MailRelay;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The mail relay, editable in the application
|--------------------------------------------------------------------------
| This project removed a generic settings table on purpose. Mail is the
| exception: the relay changes for reasons outside the plant, and needing SSH
| to fix a password reset is how a locked-out supervisor stays locked out.
|
| The rules that matter are that .env stays the floor, the API key never
| leaves the server, and nothing here can stop the application booting.
*/

function settingsAdmin(): User
{
    $user = User::factory()->create(['email' => 'admin@example.test']);
    $user->assignRole('admin');

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    MailSetting::forget();
});

it('keeps the screen to holders of setting.manage', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)->get(route('admin.mail'))->assertForbidden();
    $this->actingAs(settingsAdmin())->get(route('admin.mail'))->assertOk();
});

it('opens showing what .env is actually using', function (): void {
    // Empty boxes would invite somebody to type a relay from memory over one
    // that already works.
    config(['mail.mailers.smtp.host' => 'mailpit', 'mail.from.address' => 'no-reply@branding-pm.local']);

    Livewire::actingAs(settingsAdmin())
        ->test(MailSettings::class)
        ->assertSet('host', 'mailpit')
        ->assertSet('fromAddress', 'no-reply@branding-pm.local');
});

it('leaves .env in force until the settings are switched on', function (): void {
    MailSetting::create([
        'host' => 'smtp.sendgrid.net', 'port' => 587, 'username' => 'apikey',
        'password' => 'SG.secret', 'encryption' => 'tls',
        'from_address' => 'no-reply@labelhouse.com', 'from_name' => 'Branding PM',
        'is_active' => false,
    ]);

    config(['mail.mailers.smtp.host' => 'mailpit']);
    MailSetting::forget();
    MailRelay::apply();

    expect(config('mail.mailers.smtp.host'))->toBe('mailpit');
});

it('overrides .env once switched on', function (): void {
    MailSetting::create([
        'host' => 'smtp.sendgrid.net', 'port' => 587, 'username' => 'apikey',
        'password' => 'SG.secret', 'encryption' => 'tls',
        'from_address' => 'no-reply@labelhouse.com', 'from_name' => 'Branding PM',
        'is_active' => true,
    ]);

    config(['mail.mailers.smtp.host' => 'mailpit', 'mail.from.address' => 'x@y.local']);
    MailSetting::forget();
    MailRelay::apply();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.sendgrid.net')
        ->and(config('mail.mailers.smtp.username'))->toBe('apikey')
        ->and(config('mail.mailers.smtp.password'))->toBe('SG.secret')
        ->and(config('mail.from.address'))->toBe('no-reply@labelhouse.com');
});

it('never lets a broken relay stop the application booting', function (): void {
    // MailRelay runs on every boot, including `artisan migrate` on an install
    // where this table does not exist yet. A config override that can throw is
    // one that can take the whole site down.
    Schema::drop('mail_settings');

    MailSetting::forget();
    MailRelay::apply();

    expect(true)->toBeTrue();   // reaching here at all is the assertion
});

it('stores the API key encrypted', function (): void {
    // The column is in every nightly dump, and a key in one is a key somebody
    // else can send as you with.
    Livewire::actingAs(settingsAdmin())
        ->test(MailSettings::class)
        ->set('host', 'smtp.sendgrid.net')
        ->set('port', '587')
        ->set('username', 'apikey')
        ->set('password', 'SG.plaintext-key')
        ->set('fromAddress', 'no-reply@labelhouse.com')
        ->set('fromName', 'Branding PM')
        ->call('save')
        ->assertHasNoErrors();

    $raw = (string) DB::table('mail_settings')->value('password');

    expect($raw)->not->toContain('SG.plaintext-key')
        ->and(MailSetting::query()->first()->password)->toBe('SG.plaintext-key');
});

it('does not send the stored key to the browser', function (): void {
    MailSetting::create([
        'host' => 'smtp.sendgrid.net', 'port' => 587, 'username' => 'apikey',
        'password' => 'SG.do-not-leak', 'encryption' => 'tls',
        'from_address' => 'no-reply@labelhouse.com', 'from_name' => 'Branding PM',
        'is_active' => true,
    ]);

    Livewire::actingAs(settingsAdmin())
        ->test(MailSettings::class)
        ->assertSet('password', '')
        ->assertDontSee('SG.do-not-leak');
});

it('keeps the saved key when another field is changed', function (): void {
    // Otherwise editing the from-name would silently wipe the credential.
    MailSetting::create([
        'host' => 'smtp.sendgrid.net', 'port' => 587, 'username' => 'apikey',
        'password' => 'SG.keep-me', 'encryption' => 'tls',
        'from_address' => 'no-reply@labelhouse.com', 'from_name' => 'Old Name',
        'is_active' => true,
    ]);

    Livewire::actingAs(settingsAdmin())
        ->test(MailSettings::class)
        ->set('fromName', 'New Name')
        ->call('save')
        ->assertHasNoErrors();

    $setting = MailSetting::query()->first();

    expect($setting->from_name)->toBe('New Name')
        ->and($setting->password)->toBe('SG.keep-me');
});

it('reports the relay error in the provider own words', function (): void {
    // "Connection could not be established" and "535 authentication failed"
    // need different fixes; one friendly sentence would hide which it is.
    $component = Livewire::actingAs(settingsAdmin())
        ->test(MailSettings::class)
        ->set('host', '127.0.0.1')
        ->set('port', '1')                       // nothing listens here
        ->set('username', 'apikey')
        ->set('password', 'SG.whatever')
        ->set('fromAddress', 'no-reply@labelhouse.com')
        ->set('fromName', 'Branding PM')
        ->call('sendTest');

    expect($component->get('testPassed'))->toBeFalse()
        ->and($component->get('testResult'))->not->toBeEmpty();
});

it('records the test result against the saved relay', function (): void {
    MailSetting::create([
        'host' => '127.0.0.1', 'port' => 1, 'username' => 'apikey',
        'password' => 'SG.x', 'encryption' => 'tls',
        'from_address' => 'no-reply@labelhouse.com', 'from_name' => 'Branding PM',
        'is_active' => false,
    ]);

    Livewire::actingAs(settingsAdmin())
        ->test(MailSettings::class)
        ->call('sendTest');

    $setting = MailSetting::query()->first();

    expect($setting->last_tested_at)->not->toBeNull()
        ->and($setting->last_test_result)->toStartWith('FAILED:');
});

it('keeps the key out of the activity log', function (): void {
    // An activity log that records a credential is a second place it leaks
    // from.
    MailSetting::create([
        'host' => 'smtp.sendgrid.net', 'port' => 587, 'username' => 'apikey',
        'password' => 'SG.never-logged', 'encryption' => 'tls',
        'from_address' => 'no-reply@labelhouse.com', 'from_name' => 'Branding PM',
        'is_active' => true,
    ]);

    $logged = DB::table('activity_log')->pluck('properties')->implode(' ');

    expect($logged)->not->toContain('SG.never-logged');
});
