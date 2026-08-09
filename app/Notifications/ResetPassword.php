<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The "set a new password" email.
 *
 * Laravel ships one of these. This replaces it for two reasons: every other
 * string a person reads in this system comes from `lang/en/app.php`, and the
 * stock copy is written for a consumer web app ("You are receiving this email
 * because we received a password reset request for your account"). The
 * audience here is a supervisor on a plant floor who has locked themselves
 * out before a shift.
 *
 * The link is a plain signed-token URL rather than a signed route: the token
 * is the credential, it is single-use, and it expires with
 * `config('auth.passwords.users.expire')`.
 */
class ResetPassword extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('app.auth.reset_email_subject'))
            ->greeting(__('app.auth.reset_email_greeting', ['name' => $notifiable->full_name]))
            ->line(__('app.auth.reset_email_intro'))
            ->action(__('app.auth.reset_email_button'), $this->resetUrl($notifiable))
            ->line(__('app.auth.reset_email_expiry', ['minutes' => $minutes]))
            // Said plainly, because the person who did not ask for this is the
            // one who most needs to know what to do about it.
            ->line(__('app.auth.reset_email_ignore'))
            ->salutation(__('app.auth.reset_email_salutation'));
    }

    private function resetUrl(object $notifiable): string
    {
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));
    }
}
