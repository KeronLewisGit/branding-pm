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
    // Credentials included: a host on its own is not a working relay, and
    // leaving them out made this pass for the wrong reason.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.sendgrid.net',
        'mail.mailers.smtp.username' => 'apikey',
    ]);
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

it('catches a real relay offered no credentials', function (): void {
    /*
     * The other way to earn "554 Client host rejected", and the one that does
     * not look wrong at a glance: a genuine mail server, the right port, a
     * connection that succeeds — and no username, so the server refuses to
     * carry mail for a stranger. Found in the wild on the pilot server, where
     * MAIL_HOST was smtp.hostinger.com with MAIL_USERNAME unset.
     */
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.hostinger.com',
        'mail.mailers.smtp.username' => null,
    ]);

    expect(MailRelay::sendsUnauthenticated())->toBeTrue();

    $this->artisan('mail:doctor')
        ->expectsOutputToContain('without a username')
        ->assertExitCode(1);
});

it('does not complain when the relay has credentials', function (): void {
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'smtp.sendgrid.net',
        'mail.mailers.smtp.username' => 'apikey',
    ]);

    expect(MailRelay::sendsUnauthenticated())->toBeFalse();
});

it('leaves the local-server case its own message', function (): void {
    // Both are true of an unauthenticated localhost, and two errors for one
    // fault is how a report stops being read.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'localhost', 'mail.mailers.smtp.username' => null]);

    expect(MailRelay::sendsUnauthenticated())->toBeFalse()
        ->and(MailRelay::sendsLocally())->toBeTrue();
});

it('opens the settings screen for an API relay that has no host', function (): void {
    /*
     * Regression. Making host and port nullable — correct, an API relay has
     * neither — meant mount() assigned null to a typed string property. The
     * result was a 500 on the one screen that exists to repair a broken relay,
     * reachable only via the configuration the change was meant to allow.
     */
    MailSetting::query()->create([
        'transport' => MailRelay::TRANSPORT_SENDGRID_API,
        'host' => null,
        'port' => null,
        'username' => null,
        'password' => 'SG.the-key',
        'from_address' => 'pm@labelhouse.com',
        'from_name' => 'Branding PM',
        'is_active' => true,
    ]);
    MailSetting::forget();

    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
    $admin = App\Models\User::factory()->create();
    $admin->assignRole('admin');

    Livewire\Livewire::actingAs($admin)
        ->test(App\Livewire\Admin\MailSettings::class)
        ->assertOk();
});

it('keeps the EHLO domain when falling back to SendGrid SMTP', function (): void {
    // config/mail.php sets local_domain, and relays do reject a session that
    // greets them with the wrong name. Replacing the array would drop it.
    config(['mail.mailers.smtp.local_domain' => 'branding-pm.example']);

    MailRelay::fakeBridge(false);

    storedRelay(['transport' => MailRelay::TRANSPORT_SENDGRID_API, 'host' => null, 'port' => null]);
    MailSetting::forget();
    MailRelay::apply();

    expect(config('mail.mailers.smtp.local_domain'))->toBe('branding-pm.example')
        ->and(config('mail.mailers.smtp.host'))->toBe(MailRelay::SENDGRID_SMTP_HOST);
});

/*
|--------------------------------------------------------------------------
| Saying why, where it is read
|--------------------------------------------------------------------------
| A relay problem is discovered on the user form far more often than on the
| screen built for it, because that is where somebody is standing when it
| bites. The relay's own words say what happened; they never say what to do.
*/

it('names the switched-off relay as the reason', function (): void {
    // The pilot server's actual state: a SendGrid relay saved, never switched
    // on, .env quietly sending through an unauthenticated host instead.
    storedRelay(['transport' => MailRelay::TRANSPORT_SENDGRID_API, 'is_active' => false]);
    MailSetting::forget();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.hostinger.com', 'mail.mailers.smtp.username' => null]);

    expect(MailRelay::diagnosis())->toBe('app.mail.diag.not_active');
});

it('reports the upstream fault, not the ones it causes', function (): void {
    /*
     * A relay saved but not switched on explains every symptom below it.
     * Reporting those as well would send somebody to fix settings that are
     * not in use.
     */
    storedRelay(['is_active' => false]);
    MailSetting::forget();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'localhost', 'mail.mailers.smtp.username' => null]);

    expect(MailRelay::diagnosis())->toBe('app.mail.diag.not_active');
});

it('says nothing when there is nothing to say', function (): void {
    storedRelay();
    MailSetting::forget();
    MailRelay::apply();

    expect(MailRelay::diagnosis())->toBeNull();
});

it('every diagnosis has wording', function (): void {
    // A key with no translation renders as the key itself — a failure that
    // looks like a message and tells nobody anything.
    foreach (['not_active', 'bridge_missing', 'local', 'unauthenticated'] as $key) {
        expect(__("app.mail.diag.{$key}"))->not->toBe("app.mail.diag.{$key}");
    }
});

it('follows the relay refusal with what to do about it', function (): void {
    /*
     * Tested here rather than through the component: the message lands in a
     * flash on a Livewire action, and the test harness ages flash data before
     * an assertion can reach it — a known-good flash reads back null too. A
     * pure function of the two halves is observable, and leaves a call site
     * too small to be subtly wrong.
     */
    storedRelay(['is_active' => false]);
    MailSetting::forget();

    $explained = MailRelay::explain('Expected response code "250" but got code "554"');

    // The provider's words kept, and kept first — they are the evidence, and
    // the thing somebody will search for.
    expect($explained)->toStartWith('Expected response code')
        ->and($explained)->toContain('554')
        ->and($explained)->toContain(__('app.mail.diag.not_active'));
});

it('adds nothing when the relay is healthy and the failure is elsewhere', function (): void {
    // A rejected recipient, a full mailbox, a momentary outage: real failures
    // with nothing to advise. Inventing advice would send somebody to change
    // settings that are correct.
    storedRelay();
    MailSetting::forget();
    MailRelay::apply();

    expect(MailRelay::explain('550 mailbox unavailable'))->toBe('550 mailbox unavailable');
});
