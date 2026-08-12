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
        $message = (new MailMessage)
            ->subject(__('app.credentials_mail.subject', ['app' => (string) config('app.name')]))
            ->greeting(__('app.credentials_mail.greeting', ['name' => $notifiable->full_name]))
            ->line(__('app.credentials_mail.intro', ['app' => (string) config('app.name')]))
            ->line(__('app.credentials_mail.employee_number', ['number' => $notifiable->employee_number]));

        // Only what was actually issued. An operator set up with a PIN and no
        // password must not be told about a password they do not have.
        if ($this->password !== null && $this->password !== '') {
            $message->line(__('app.credentials_mail.password', ['password' => $this->password]));
        }

        if ($this->pin !== null && $this->pin !== '') {
            $message->line(__('app.credentials_mail.pin', ['pin' => $this->pin]));
        }

        if ($this->password !== null && $this->password !== '') {
            $message->action(__('app.credentials_mail.button'), route('login'));
        }

        return $message
            // Said last, where it is read, rather than buried above the
            // credentials it is about.
            ->line(__('app.credentials_mail.change_it'))
            ->salutation(__('app.auth.reset_email_salutation'));
    }
}
