<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Throwable;

/**
 * Put the stored relay in front of the one in `.env`.
 *
 * `.env` remains the floor. A relay saved but not enabled changes nothing, and
 * if this table is empty, unreachable or the database is down, the application
 * boots on its `.env` values exactly as before.
 *
 * That last part is not defensive habit. This runs on EVERY boot, including
 * `php artisan migrate` on an install where the table does not exist yet, and
 * a config override that can throw is one that can take the whole site down —
 * which is precisely how a stale package manifest stranded this application
 * once already.
 */
final class MailRelay
{
    private static ?bool $bridgeOverride = null;

    public const TRANSPORT_SMTP = 'smtp';

    public const TRANSPORT_SENDGRID_API = 'sendgrid_api';

    /**
     * SendGrid's SMTP settings are fixed and documented, not per-account: the
     * username is the literal string `apikey` and the password is the API key.
     * One secret, two ways out of the building — which is what makes falling
     * back from the API to SMTP possible without asking anybody anything.
     */
    public const SENDGRID_SMTP_HOST = 'smtp.sendgrid.net';

    public const SENDGRID_SMTP_PORT = 587;

    public const SENDGRID_SMTP_USERNAME = 'apikey';

    /**
     * Hosts that mean "hand it to the machine's own mail server".
     *
     * Worth naming, because this is the single most expensive value in the
     * configuration and it does not look like a mistake. A shared host runs a
     * mail server that accepts mail for its own domains and relays nothing
     * else, so the site connects happily and is refused on delivery with
     * "554 Client host rejected". It is also the default: `config/mail.php`
     * falls back to 127.0.0.1 when MAIL_HOST is unset, so an incomplete .env
     * lands here rather than failing outright.
     */
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', 'sendmail', ''];

    /**
     * Is the site about to hand its mail to the local mail server?
     *
     * Read from live config rather than the stored row: what is saved and what
     * is in force are different things, and this has to answer for the message
     * that is actually about to be sent.
     */
    public static function sendsLocally(): bool
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, [self::TRANSPORT_SENDGRID_API, 'log', 'array'], true)) {
            return false;
        }

        return in_array(
            mb_strtolower((string) config("mail.mailers.{$mailer}.host")),
            self::LOCAL_HOSTS,
            true,
        );
    }

    /**
     * Is the SendGrid bridge actually installed?
     *
     * `symfony/sendgrid-mailer` is a composer dependency, and a server that
     * has pulled the code but not yet run `composer install` has the option in
     * the UI and not the class behind it. Asking before offering turns a fatal
     * into a sentence.
     */
    public static function sendgridApiAvailable(): bool
    {
        return self::$bridgeOverride ?? class_exists(SendgridTransportFactory::class);
    }

    /**
     * Test seam: pretend the bridge is or is not installed.
     *
     * A class cannot be unloaded, so without this the missing-package path is
     * unreachable from a test suite running on a machine where composer has
     * done its job — which is every machine except the one that matters. That
     * path only executes on a server that pulled code without installing
     * dependencies, so leaving it unexercised means the code written for a
     * broken deploy is itself only ever tested by a broken deploy.
     *
     * Cleared between tests in the base TestCase; a static that outlives a
     * test is a fault this project has already paid for once.
     */
    public static function fakeBridge(?bool $available): void
    {
        self::$bridgeOverride = $available;
    }

    /**
     * Laravel ships transports for SES, Postmark, Resend and Mailgun, but not
     * SendGrid. Symfony's bridge provides one; this is what makes
     * `mail.default = 'sendgrid_api'` mean anything.
     *
     * Registered at boot whether or not it is in use, so the transport exists
     * before any configuration can name it — but only when the bridge is
     * actually present, since a deploy that pulled the code without running
     * composer has the option and not the class.
     */
    public static function registerTransports(): void
    {
        if (! self::sendgridApiAvailable()) {
            return;
        }

        Mail::extend(self::TRANSPORT_SENDGRID_API, function (array $config) {
            $key = (string) ($config['api_key'] ?? '');

            // `sendgrid+api` is HTTPS to api.sendgrid.com. The other scheme,
            // `sendgrid+smtp`, would put us back on port 587 — which is the
            // thing choosing this transport is meant to get away from.
            return (new SendgridTransportFactory)->create(
                new Dsn('sendgrid+api', 'default', $key)
            );
        });
    }

    public static function apply(): void
    {
        try {
            $relay = MailSetting::active();
        } catch (Throwable) {
            // No table yet (mid-migration), or no database. Neither is a
            // reason to fail to boot.
            return;
        }

        if ($relay === null) {
            return;
        }

        Config::set('mail.from.address', $relay->from_address);
        Config::set('mail.from.name', $relay->from_name);

        if ($relay->transport === self::TRANSPORT_SENDGRID_API && ! self::sendgridApiAvailable()) {
            /*
             * The API was chosen and its package is not installed — a deploy
             * that pulled the code without running composer.
             *
             * This used to fall through to the SMTP branch below, using
             * whatever host the row happened to hold. For an API row that host
             * was a placeholder and the username SendGrid requires was never
             * asked for, so the fallback authenticated as nobody and failed
             * with `535` — a wrong-key error for a problem that was not the
             * key.
             *
             * SendGrid's SMTP settings are fixed and the API key doubles as
             * the SMTP password, so the fallback can be exact rather than
             * approximate. Same provider, same credential, different door.
             */
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp', [
                'transport' => 'smtp',
                'host' => self::SENDGRID_SMTP_HOST,
                'port' => self::SENDGRID_SMTP_PORT,
                'username' => self::SENDGRID_SMTP_USERNAME,
                'password' => $relay->password,
                'encryption' => 'tls',
            ]);

            return;
        }

        if ($relay->transport === self::TRANSPORT_SENDGRID_API) {
            Config::set('mail.mailers.'.self::TRANSPORT_SENDGRID_API, [
                'transport' => self::TRANSPORT_SENDGRID_API,
                // The same encrypted column SMTP uses: SendGrid's SMTP
                // password IS the API key, so there is only one secret.
                'api_key' => $relay->password,
            ]);

            Config::set('mail.default', self::TRANSPORT_SENDGRID_API);

            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $relay->host);
        Config::set('mail.mailers.smtp.port', $relay->port);
        Config::set('mail.mailers.smtp.username', $relay->username);
        Config::set('mail.mailers.smtp.password', $relay->password);

        // config/mail.php reads `encryption`, from MAIL_ENCRYPTION. Not
        // MAIL_SCHEME, which .env.example named by mistake for a while and
        // nothing has ever consumed.
        Config::set('mail.mailers.smtp.encryption', $relay->encryption);
    }
}
