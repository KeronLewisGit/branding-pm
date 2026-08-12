<?php

declare(strict_types=1);

use App\Models\MailSetting;
use App\Support\MailRelay;

/*
|--------------------------------------------------------------------------
| Knowing where the mail actually goes
|--------------------------------------------------------------------------
| The relay that is configured and the relay that is used can differ, and
| nothing said so. A saved relay that was never switched on looks identical, on
| screen, to one in force — until a message goes out through the local mail
| server and comes back "554 Client host rejected".
|
| These cover the two states that produce that rejection, because each one cost
| a round of guessing at a symptom whose cause nobody could see.
*/

function storedRelay(array $overrides = []): MailSetting
{
    return MailSetting::query()->create(array_merge([
        'transport' => MailRelay::TRANSPORT_SMTP,
        'host' => 'smtp.sendgrid.net',
        'port' => 587,
        'username' => 'apikey',
        'password' => 'SG.a-key',
        'from_address' => 'pm@labelhouse.com',
        'from_name' => 'Branding PM',
        'is_active' => true,
    ], $overrides));
}

it('reports the route a message will actually take', function (): void {
    storedRelay();
    MailSetting::forget();
    MailRelay::apply();

    $this->artisan('mail:doctor')
        ->expectsOutputToContain('smtp.sendgrid.net')
        ->assertExitCode(0);
});

it('says when a saved relay is not switched on', function (): void {
    // The gap between "saved" and "in use" is where the confusion lives.
    storedRelay(['is_active' => false]);
    MailSetting::forget();

    $this->artisan('mail:doctor')
        ->expectsOutputToContain('NOT ticked')
        ->assertExitCode(1);
});

it('fails when the site is handing mail to the local server', function (): void {
    /*
     * The state that produces the rejection. A shared host runs a mail server
     * that accepts mail for its own domains and relays nothing else, so this
     * looks configured, connects fine, and is refused on delivery.
     */
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'localhost']);

    $this->artisan('mail:doctor')
        ->expectsOutputToContain('554')
        ->assertExitCode(1);
});

it('does not mistake a real relay for the local one', function (): void {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.sendgrid.net']);
    storedRelay();
    MailSetting::forget();

    $this->artisan('mail:doctor')->assertExitCode(0);
});

it('says when the API transport is chosen but its package is missing', function (): void {
    // Otherwise the fall back to SMTP is silent, and the administrator is
    // looking at a screen that says SendGrid while mail goes elsewhere.
    if (MailRelay::sendgridApiAvailable()) {
        $this->markTestSkipped('The SendGrid bridge is installed here, so this state cannot be reached.');
    }

    storedRelay(['transport' => MailRelay::TRANSPORT_SENDGRID_API]);
    MailSetting::forget();

    $this->artisan('mail:doctor')
        ->expectsOutputToContain('composer install')
        ->assertExitCode(1);
});

it('warns on the settings screen, not only in a terminal', function (): void {
    // An administrator on shared hosting lives in a control panel, not an SSH
    // session. A diagnosis they cannot reach is not a diagnosis.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'localhost']);

    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);

    $admin = App\Models\User::factory()->create();
    $admin->assignRole('admin');

    Livewire\Livewire::actingAs($admin)
        ->test(App\Livewire\Admin\MailSettings::class)
        ->assertSee('554');
});
