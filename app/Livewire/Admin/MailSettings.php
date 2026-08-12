<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\MailSetting;
use App\Support\MailRelay;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Admin → Mail. The relay, editable without SSH.
 *
 * This exists rather than living only in `.env` because the relay is the one
 * setting that changes for reasons outside the plant — an expired API key, a
 * switched provider — and needing shell access to fix it is how a locked-out
 * supervisor stays locked out for a day.
 */
#[Layout('layouts::app')]
class MailSettings extends Component
{
    use AuthorizesRequests;

    /**
     * 'smtp' | 'sendgrid_api'.
     *
     * Both reach SendGrid; they differ in how they leave the building. SMTP
     * opens a socket on 587, which shared hosts block often enough that it is
     * the first thing to suspect when mail stops. The API is ordinary HTTPS on
     * 443, which anything able to browse the web can do.
     */
    public string $transport = MailRelay::TRANSPORT_SMTP;

    public string $host = '';

    public string $port = '587';

    public string $username = '';

    /**
     * Blank means "leave the stored one alone".
     *
     * The saved key is never sent to the browser — not even masked at its real
     * length — so this is empty on every load, and somebody editing the
     * from-address does not have to retype a credential they may not have to
     * hand.
     */
    public string $password = '';

    public string $encryption = 'tls';

    public string $fromAddress = '';

    public string $fromName = '';

    /**
     * Copied on every account-credentials email, if set.
     *
     * A setting rather than an address in the source: a name in a repository
     * is a published personal address, and one that becomes wrong the moment
     * somebody changes role — quietly, because nothing fails when mail keeps
     * going to a person who has left.
     */
    public string $credentialsCc = '';

    public bool $isActive = false;

    /** Result of the last test in THIS screen, so it reads as a reply. */
    public ?string $testResult = null;

    public bool $testPassed = false;

    public function mount(): void
    {
        $this->authorize('manageSettings', MailSetting::class);

        $existing = MailSetting::query()->first();

        if ($existing !== null) {
            $this->transport = $existing->transport;
            $this->host = $existing->host;
            $this->port = (string) $existing->port;
            $this->username = (string) $existing->username;
            $this->encryption = (string) ($existing->encryption ?? '');
            $this->fromAddress = $existing->from_address;
            $this->fromName = $existing->from_name;
            $this->credentialsCc = (string) $existing->credentials_cc;
            $this->isActive = $existing->is_active;

            return;
        }

        // Nothing saved yet: start from whatever .env is using, so the form
        // opens showing what is actually in force rather than empty boxes.
        $this->host = (string) config('mail.mailers.smtp.host');
        $this->port = (string) config('mail.mailers.smtp.port');
        $this->username = (string) config('mail.mailers.smtp.username');
        $this->encryption = (string) config('mail.mailers.smtp.encryption');
        $this->fromAddress = (string) config('mail.from.address');
        $this->fromName = (string) config('mail.from.name');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'transport' => [
                'required',
                Rule::in([MailRelay::TRANSPORT_SMTP, MailRelay::TRANSPORT_SENDGRID_API]),
                // Refused with a sentence rather than accepted and fatal later.
                function (string $attribute, mixed $value, callable $fail): void {
                    if ($value === MailRelay::TRANSPORT_SENDGRID_API && ! MailRelay::sendgridApiAvailable()) {
                        $fail(__('app.mail.api_unavailable'));
                    }
                },
            ],
            // The API needs neither: it is one HTTPS call to a fixed endpoint.
            'host' => [Rule::requiredIf($this->transport === MailRelay::TRANSPORT_SMTP), 'nullable', 'string', 'max:190'],
            'port' => [Rule::requiredIf($this->transport === MailRelay::TRANSPORT_SMTP), 'nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:500'],
            // '' is a real answer — an unencrypted relay on a plant LAN — so
            // the rule permits it rather than forcing a choice that is untrue.
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'fromAddress' => ['required', 'email', 'max:190'],
            'fromName' => ['required', 'string', 'max:190'],
            'credentialsCc' => ['nullable', 'email', 'max:190'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'host' => __('app.mail.host'),
            'port' => __('app.mail.port'),
            'username' => __('app.mail.username'),
            'password' => __('app.mail.password'),
            'fromAddress' => __('app.mail.from_address'),
            'fromName' => __('app.mail.from_name'),
            'credentialsCc' => __('app.mail.credentials_cc'),
        ];
    }

    public function save(): void
    {
        $this->authorize('manageSettings', MailSetting::class);

        $this->validate();

        $setting = MailSetting::query()->first() ?? new MailSetting;

        $setting->fill([
            'transport' => $this->transport,
            // Null rather than a placeholder hostname. The API does not use
            // one, and a stored `api.sendgrid.com` was a value that looked
            // like configuration, described nothing, and misled the fallback
            // that later had to read it.
            'host' => trim($this->host) ?: null,
            'port' => $this->port !== '' ? (int) $this->port : null,
            'username' => trim($this->username) ?: null,
            'encryption' => $this->encryption ?: null,
            'from_address' => trim($this->fromAddress),
            'from_name' => trim($this->fromName),
            'credentials_cc' => trim($this->credentialsCc) ?: null,
            'is_active' => $this->isActive,
            'updated_by_id' => auth()->id(),
        ]);

        // Overwritten only when something was actually typed, or saving any
        // other field would wipe the stored key.
        if (trim($this->password) !== '') {
            /*
             * Trimmed, because an API key is pasted rather than typed and a
             * trailing space or newline comes with it more often than not —
             * from a terminal, an email, or a double-click that took the line
             * break. It is invisible in a password field, and SendGrid rejects
             * it as `535 authentication failed`, which reads like a wrong key
             * rather than a whitespace problem.
             */
            $setting->password = trim($this->password);
        }

        $setting->save();

        $this->password = '';
        MailSetting::forget();

        session()->flash('flash.success', __('app.mail.saved'));
    }

    /**
     * Send one message through the settings CURRENTLY IN THE FORM.
     *
     * Deliberately not through the saved row: the point is to learn whether
     * what you are about to save works, before it becomes the relay every
     * password reset depends on.
     */
    public function sendTest(): void
    {
        $this->authorize('manageSettings', MailSetting::class);

        $this->validate();

        $recipient = (string) (auth()->user()?->email ?? '');

        if ($recipient === '') {
            $this->testPassed = false;
            $this->testResult = __('app.mail.test_no_recipient');

            return;
        }

        // The typed password when there is one, the stored one otherwise, so a
        // test after reopening the form does not need it retyped.
        $password = trim($this->password) !== ''
            ? trim($this->password)
            : (string) (MailSetting::query()->first()?->password ?? '');

        // Built from what is in the FORM, under a throwaway mailer name, so
        // the test exercises what you are about to save rather than what is
        // already saved and working.
        $probe = $this->transport === MailRelay::TRANSPORT_SENDGRID_API
            ? ['transport' => MailRelay::TRANSPORT_SENDGRID_API, 'api_key' => $password]
            : [
                'transport' => 'smtp',
                'host' => trim($this->host),
                'port' => (int) $this->port,
                'username' => trim($this->username) ?: null,
                'password' => $password ?: null,
                'encryption' => $this->encryption ?: null,
                'timeout' => 15,
            ];

        config([
            'mail.mailers.probe' => $probe,
            'mail.from.address' => trim($this->fromAddress),
            'mail.from.name' => trim($this->fromName),
        ]);

        try {
            Mail::mailer('probe')->raw(
                __('app.mail.test_body', ['app' => (string) config('app.name')]),
                fn ($message) => $message->to($recipient)->subject(__('app.mail.test_subject')),
            );

            $this->testPassed = true;
            $this->testResult = __('app.mail.test_sent', ['email' => $recipient]);
        } catch (Throwable $e) {
            // The provider's own words. "Connection could not be established"
            // and "535 authentication failed" need entirely different fixes,
            // and paraphrasing both into one friendly sentence would hide
            // which of the two it is.
            $this->testPassed = false;
            $this->testResult = mb_substr($e->getMessage(), 0, 400);
        }

        MailSetting::query()->first()?->forceFill([
            'last_tested_at' => now(),
            'last_test_result' => mb_substr(($this->testPassed ? 'OK: ' : 'FAILED: ').$this->testResult, 0, 500),
        ])->save();

        MailSetting::forget();
        MailRelay::apply();
    }

    public function render(): View
    {
        return view('livewire.admin.mail-settings', [
            'setting' => MailSetting::query()->with('updatedBy:id,full_name')->first(),
            'envHost' => (string) config('mail.mailers.smtp.host'),
            'sendsLocally' => MailRelay::sendsLocally(),
        ])->title(__('app.mail.title'));
    }
}
