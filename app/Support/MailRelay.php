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
    public const TRANSPORT_SMTP = 'smtp';

    public const TRANSPORT_SENDGRID_API = 'sendgrid_api';

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
        return class_exists(SendgridTransportFactory::class);
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

        // Falls through to SMTP when the bridge is missing rather than
        // switching `mail.default` to a transport nothing can build. A relay
        // that quietly keeps working beats one that fatals on the next
        // password reset.
        if ($relay->transport === self::TRANSPORT_SENDGRID_API && self::sendgridApiAvailable()) {
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

        // config/mail.php reads `encryption`. It is deliberately NOT
        // `MAIL_SCHEME`, which .env.example mentions and nothing consumes.
        Config::set('mail.mailers.smtp.encryption', $relay->encryption);
    }
}
