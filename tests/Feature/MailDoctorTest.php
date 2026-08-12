<?php

declare(strict_types=1);

use App\Models\MailSetting;
use App\Support\MailRelay;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
    MailRelay::fakeBridge(false);

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

it('falls back to SendGrid over SMTP when the API package is missing', function (): void {
    /*
     * The fallback used to read the stored host, which for an API relay was a
     * placeholder, and the username SendGrid requires had never been asked
     * for — so it authenticated as nobody and failed with `535`, a wrong-key
     * error for a problem that was not the key.
     *
     * SendGrid's SMTP settings are fixed and the API key doubles as the SMTP
     * password, so the fallback can be exact.
     */
    MailRelay::fakeBridge(false);

    storedRelay([
        'transport' => MailRelay::TRANSPORT_SENDGRID_API,
        'host' => null,
        'port' => null,
        'username' => null,
        'password' => 'SG.the-key',
    ]);
    MailSetting::forget();
    MailRelay::apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe(MailRelay::SENDGRID_SMTP_HOST)
        ->and(config('mail.mailers.smtp.username'))->toBe(MailRelay::SENDGRID_SMTP_USERNAME)
        ->and(config('mail.mailers.smtp.password'))->toBe('SG.the-key');
});

it('does not call the log or array mailers a local relay', function (): void {
    // Both are legitimate — `log` is what a machine without Docker uses, and
    // the test suite runs on `array`. Warning about either would be noise.
    config(['mail.default' => 'log']);
    expect(MailRelay::sendsLocally())->toBeFalse();

    config(['mail.default' => 'array']);
    expect(MailRelay::sendsLocally())->toBeFalse();
});

it('recognises every spelling of the local mail server', function (): void {
    foreach (['localhost', '127.0.0.1', '::1', 'LOCALHOST', ''] as $host) {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => $host]);

        expect(MailRelay::sendsLocally())->toBeTrue("host: {$host}");
    }
});

it('says the database is behind the code rather than failing on save', function (): void {
    /*
     * A `git pull` without `php artisan migrate` leaves code writing a column
     * the table does not have. The only symptom is a 500 on save — an SQL
     * error the browser never shows and nobody connects to a migration.
     */
    Schema::table('mail_settings', fn (Blueprint $table) => $table->dropColumn('credentials_cc'));

    $this->artisan('mail:doctor')
        ->expectsOutputToContain('credentials_cc')
        ->expectsOutputToContain('migrate')
        ->assertExitCode(1);
});
