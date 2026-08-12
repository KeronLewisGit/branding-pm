<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Config;
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
 * a config override that can throw is a config override that can take the
 * whole site down — which is precisely how a stale package manifest stranded
 * this application once already.
 */
final class MailRelay
{
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

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $relay->host);
        Config::set('mail.mailers.smtp.port', $relay->port);
        Config::set('mail.mailers.smtp.username', $relay->username);
        Config::set('mail.mailers.smtp.password', $relay->password);

        // config/mail.php reads `encryption`. It is deliberately NOT
        // `MAIL_SCHEME`, which .env.example mentions and nothing consumes.
        Config::set('mail.mailers.smtp.encryption', $relay->encryption);

        Config::set('mail.from.address', $relay->from_address);
        Config::set('mail.from.name', $relay->from_name);
    }
}
