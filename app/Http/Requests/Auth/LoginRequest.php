<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Password login for supervisors and office users (BUILD-CONTRACT §6).
 *
 * The single `identifier` field accepts a company email address OR an
 * employee number: a value containing "@" is looked up against
 * `users.email`, anything else against `users.employee_number`.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'identifier' => __('app.auth.email_or_employee_number'),
            'password' => __('app.auth.password'),
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()
            ->where($this->identifierColumn(), $this->identifierValue())
            ->first();

        /*
         * One generic failure message for every failure mode — unknown
         * identifier, deactivated account, kiosk-PIN-only user (password
         * is null) and plain wrong password. Nothing here may reveal
         * which one it was, or whether the identifier exists at all.
         *
         * A user whose password is null NEVER authenticates through this
         * route: canLoginWithPassword() short-circuits before Hash::check.
         */
        if (
            $user === null
            || ! $user->is_active
            || ! $user->canLoginWithPassword()
            || ! Hash::check($this->string('password')->value(), (string) $user->password)
        ) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'identifier' => __('app.auth.failed'),
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited (5 attempts).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => __('app.auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * The rate-limiting throttle key: identifier|ip.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('identifier')->value()).'|'.$this->ip()
        );
    }

    /**
     * The users column the identifier is matched against.
     */
    public function identifierColumn(): string
    {
        return str_contains($this->identifierValue(), '@') ? 'email' : 'employee_number';
    }

    public function identifierValue(): string
    {
        return trim($this->string('identifier')->value());
    }
}
