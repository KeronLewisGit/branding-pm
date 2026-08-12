<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MailSetting;
use App\Support\MailRelay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * `mail:doctor` — where is this site actually sending mail, and why?
 *
 * Mail has an unusually bad failure mode: the relay that is configured and the
 * relay that is used can differ, and nothing says so. A saved SendGrid relay
 * that was never switched on looks identical, on screen, to one that is in
 * force — right up until a message goes out through the local mail server and
 * comes back rejected by a Postfix that has never heard of this domain.
 *
 * That is not a theoretical failure. It cost this project three rounds of
 * guessing at a "554 Client host rejected", each one a plausible explanation
 * of a symptom nobody could see the cause of. This command exists so the
 * question is answered rather than reasoned about: it reports the route the
 * next message will take, and what decided it.
 *
 * `--send=` puts it beyond argument by actually sending one.
 */
class MailDoctor extends Command
{
    protected $signature = 'mail:doctor {--send= : Send a test message to this address}';

    protected $description = 'Report which relay this site will send through, and why';

    public function handle(): int
    {
        $this->components->info('Mail — where this site will send from');

        if (! $this->schema()) {
            return self::FAILURE;
        }

        $relay = $this->storedRelay();

        $this->line('');
        $this->route();
        $this->line('');

        $ok = $this->warnings($relay);

        if (($to = $this->option('send')) !== null) {
            $this->line('');
            $ok = $this->send((string) $to) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Does the database have the columns this code writes?
     *
     * A `git pull` without `php artisan migrate` leaves code that saves a
     * column the table does not have, and the only symptom is a 500 on save —
     * an SQL error the browser never shows and nobody thinks to connect to a
     * migration. Checked first, because every reading below it would be
     * describing a table that cannot be written to anyway.
     */
    private function schema(): bool
    {
        $required = [
            'transport', 'host', 'port', 'username', 'password', 'encryption',
            'from_address', 'from_name', 'credentials_cc', 'is_active',
            'last_tested_at', 'last_test_result', 'updated_by_id',
        ];

        try {
            if (! Schema::hasTable('mail_settings')) {
                $this->components->error('The mail_settings table does not exist. Run `php artisan migrate --force`.');

                return false;
            }

            $missing = array_values(array_diff($required, Schema::getColumnListing('mail_settings')));
        } catch (Throwable $e) {
            $this->components->error('Could not read the database: '.$e->getMessage());

            return false;
        }

        if ($missing !== []) {
            $this->components->error(
                'The database is behind the code. mail_settings is missing: '.implode(', ', $missing).'. '
                .'This is what makes saving the Mail screen fail.'
            );

            // Its own line, so the fix survives the console wrapping the
            // sentence above it.
            $this->line('  Run: php artisan migrate --force');

            return false;
        }

        return true;
    }

    /**
     * What is saved on the Mail settings screen, and is it switched on?
     *
     * The distinction is the whole point. "Saved" and "in use" are different
     * states, and the gap between them is where the confusion lives.
     */
    private function storedRelay(): ?MailSetting
    {
        try {
            $row = MailSetting::row();
        } catch (Throwable $e) {
            $this->components->error('Could not read mail_settings: '.$e->getMessage());
            $this->components->warn('Has `php artisan migrate` been run on this server?');

            return null;
        }

        if ($row === null) {
            $this->components->twoColumnDetail('Saved on the Mail screen', '<fg=yellow>nothing saved</>');
            $this->components->twoColumnDetail('', 'The .env values are in force.');

            return null;
        }

        $this->components->twoColumnDetail('Saved on the Mail screen', $row->transport);
        $this->components->twoColumnDetail('  host / port', ($row->host ?: '—').' : '.($row->port ?: '—'));
        $this->components->twoColumnDetail('  username', $row->username ?: '—');
        $this->components->twoColumnDetail('  API key / password', $row->password ? 'set' : '<fg=yellow>not set</>');
        $this->components->twoColumnDetail('  from', $row->from_address.' ('.$row->from_name.')');
        $this->components->twoColumnDetail('  copy account emails to', $row->credentials_cc ?: '—');

        $this->components->twoColumnDetail(
            '  “Use these settings”',
            $row->is_active
                ? '<fg=green>ticked — these override .env</>'
                : '<fg=red>NOT ticked — .env is in force, these are ignored</>'
        );

        return $row;
    }

    /**
     * The route the next message actually takes.
     *
     * Read from the live config after `MailRelay::apply()` has run, not from
     * the stored row — that is precisely the difference this command exists to
     * show.
     */
    private function route(): void
    {
        $mailer = (string) config('mail.default');

        $this->components->twoColumnDetail('<options=bold>The next message goes via</>', "<options=bold>{$mailer}</>");

        if ($mailer === MailRelay::TRANSPORT_SENDGRID_API) {
            $this->components->twoColumnDetail('  endpoint', 'https://api.sendgrid.com (no SMTP port)');
            $this->components->twoColumnDetail('  API key', config('mail.mailers.'.$mailer.'.api_key') ? 'set' : '<fg=red>not set</>');
        } else {
            $host = (string) config("mail.mailers.{$mailer}.host");
            $port = (string) config("mail.mailers.{$mailer}.port");

            $this->components->twoColumnDetail('  host / port', ($host ?: '—').' : '.($port ?: '—'));
            $this->components->twoColumnDetail('  username', config("mail.mailers.{$mailer}.username") ?: '—');
        }

        $this->components->twoColumnDetail(
            '  from',
            config('mail.from.address').' ('.config('mail.from.name').')'
        );
    }

    /**
     * The specific mistakes that produce a message nobody can explain.
     */
    private function warnings(?MailSetting $relay): bool
    {
        $ok = true;
        $mailer = (string) config('mail.default');

        if ($relay !== null && ! $relay->is_active) {
            $this->components->warn(
                'A relay is saved but not switched on, so it is doing nothing. '
                .'Tick “Use these settings” on Admin → Mail and save.'
            );
            $ok = false;
        }

        /*
         * The one that produced "554 Client host rejected". A shared host runs
         * a local mail server that accepts mail for its own domains and
         * refuses to relay anywhere else — so this looks configured, connects
         * fine, and is rejected on delivery.
         */
        if (MailRelay::sendsLocally()) {
            $host = (string) config("mail.mailers.{$mailer}.host");

            $this->components->error(
                'This site is sending through the local mail server ('.($host ?: 'no host set').'). '
                .'On shared hosting that relays nothing off-domain and is rejected as '
                .'“554 Client host rejected”. Configure a real relay on Admin → Mail.'
            );
            $ok = false;
        }

        if (MailRelay::sendsUnauthenticated()) {
            $this->components->error(
                'This site is connecting to '.config('mail.mailers.smtp.host').' without a username, so it is '
                .'asking a mail server to carry mail for a stranger. That is refused as '
                .'“554 Client host rejected: Access denied”.'
            );
            $this->line('  Set a username and password on Admin → Mail, or switch to the SendGrid API.');
            $ok = false;
        }

        if ($relay?->transport === MailRelay::TRANSPORT_SENDGRID_API && ! MailRelay::sendgridApiAvailable()) {
            $this->components->error(
                'The SendGrid API transport is selected but symfony/sendgrid-mailer is not installed, '
                .'so mail has fallen back to SMTP. Run `composer install --no-dev` on this server.'
            );
            $ok = false;
        }

        if ((string) config('mail.from.address') === '') {
            $this->components->warn('No from address is set. Most relays reject a message without one.');
            $ok = false;
        }

        if ($ok) {
            $this->components->info('No configuration problems found.');
        }

        return $ok;
    }

    /**
     * Prove it, rather than describe it.
     *
     * The provider's own refusal is the most useful line this command can
     * print — "554 Client host rejected" names the problem far better than any
     * check written in advance — so the exception message is reported verbatim
     * rather than being reduced to a tick or a cross.
     */
    private function send(string $to): bool
    {
        $this->components->info("Sending a test message to {$to} via ".config('mail.default').'…');

        try {
            Mail::raw(
                'Test from '.config('app.name').' via '.config('mail.default').'.',
                fn ($message) => $message->to($to)->subject('Test from '.config('app.name'))
            );
        } catch (Throwable $e) {
            $this->components->error('Not sent. The relay said:');
            $this->line('  '.$e->getMessage());

            return false;
        }

        $this->components->info('Accepted by the relay. If it does not arrive, the relay took it and dropped it — check the activity log at the provider.');

        return true;
    }
}
