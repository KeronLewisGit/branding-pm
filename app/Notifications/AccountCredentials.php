<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * "Here is how to sign in", sent when an administrator creates an account and
 * asks for it.
 *
 * It carries the credentials in the clear, which is a deliberate and bounded
 * trade rather than an oversight. Email is not a confidential channel: this
 * message will sit in a mailbox, and in that mailbox's backups, long after the
 * password has been changed. So it is sent ONLY when an administrator ticks
 * the box, it is never sent on an edit, and the copy tells the recipient to
 * change it.
 *
 * A set-your-own-password link would be safer, and this application already
 * has one — `sendPasswordResetNotification()`. It is not what this is for. A
 * reset link assumes the recipient can act on it before it expires, and here
 * the person being set up is often standing at a machine while somebody else
 * does the setting up. An administrator who prefers the safer route can create
 * the account without a password and send a reset link instead.
 *
 * Nothing is stored to make this possible. The plaintext exists only inside
 * the request that created the account, which is why the option lives on that
 * form and cannot be offered afterwards.
 */
class AccountCredentials extends Notification
{
    use Queueable;

    public function __construct(
        #[SensitiveParameter] private readonly ?string $password = null,
        #[SensitiveParameter] private readonly ?string $pin = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /*
         * `route()` rather than a stored address, so the link is whatever this
         * installation is actually reachable at — the request host in a web
         * request, APP_URL when queued or sent from the console. An address
         * typed into a setting would be one more thing to be wrong after a
         * move.
         */
        $url = route('login');

        $message = (new MailMessage)
            ->subject(__('app.credentials_mail.subject', ['app' => (string) config('app.name')]))
            ->greeting(__('app.credentials_mail.greeting', ['name' => $notifiable->full_name]))
            ->line(__('app.credentials_mail.intro', ['app' => (string) config('app.name')]));

        // The details together, so they are findable at a glance rather than
        // mixed into prose somebody has to read twice.
        $details = [__('app.credentials_mail.employee_number', ['number' => $notifiable->employee_number])];

        if ($notifiable->email !== null && $notifiable->email !== '') {
            $details[] = __('app.credentials_mail.email', ['email' => $notifiable->email]);
        }

        // Only what was actually issued. An operator set up with a PIN and no
        // password must not be told about a password they do not have.
        if ($this->password !== null && $this->password !== '') {
            $details[] = __('app.credentials_mail.password', ['password' => $this->password]);
        }

        if ($this->pin !== null && $this->pin !== '') {
            $details[] = __('app.credentials_mail.pin', ['pin' => $this->pin]);
        }

        foreach ($details as $detail) {
            $message->line($detail);
        }

        if ($this->password !== null && $this->password !== '') {
            $message
                ->line(__('app.credentials_mail.where'))
                // No plain-text copy of the URL here: Laravel's mail template
                // already appends "if you're having trouble clicking the
                // button…" with the address beneath it, and saying it twice in
                // near-identical words reads like a mistake.
                ->action(__('app.credentials_mail.button'), $url)
                ->line(__('app.credentials_mail.first_login'));
        }

        if ($this->pin !== null && $this->pin !== '') {
            $message->line(__('app.credentials_mail.kiosk_note'));
        }

        return $message
            ->line(__('app.credentials_mail.keep_safe'))
            ->salutation(__('app.credentials_mail.salutation', ['app' => (string) config('app.name')]));
    }
}
