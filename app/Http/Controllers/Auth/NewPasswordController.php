<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Set a new password" — routes `password.reset`, `password.update`.
 *
 * The token in the link is the credential. It is single-use, tied to one
 * email address, and expires after `auth.passwords.users.expire` minutes.
 *
 * Two things happen on success that Laravel's default does not do:
 *
 *   - the change is written to the activity log, because "who changed this
 *     account's password, and when" is an audit question this system is
 *     expected to answer;
 *   - the session is **not** logged in afterwards. Whoever set the password
 *     proves they hold it by using it, and that keeps a forwarded reset email
 *     from being a session.
 */
class NewPasswordController extends Controller
{
    /**
     * Show the form. Route: GET /reset-password/{token}.
     */
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    /**
     * Set the new password. Route: POST /reset-password.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:190'],
            // `min:8` matches the users screen; `confirmed` is the second
            // field. Nothing more prescriptive: a rule nobody can satisfy on
            // a first try gets written on a sticky note.
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [], [
            'email' => __('app.auth.email'),
            'password' => __('app.auth.new_password'),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                /*
                 * A token issued while the account was in good standing must
                 * not still work after it was deactivated or had its password
                 * cleared. The eligibility check is re-run here, at the point
                 * of use, rather than trusted from when the link was sent.
                 */
                if (! $user->canResetPasswordByEmail()) {
                    throw ValidationException::withMessages([
                        'email' => __('app.auth.reset_not_allowed'),
                    ]);
                }

                // The 'hashed' cast hashes on assignment.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                activity()
                    ->causedBy($user)
                    ->performedOn($user)
                    ->withProperties(['via' => 'password_reset_email'])
                    ->log('password.reset');

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            // Wrong, used or expired token — one message for all three, since
            // the remedy is the same: ask for a new link.
            throw ValidationException::withMessages([
                'email' => __('app.auth.reset_failed'),
            ]);
        }

        return redirect()->route('login')->with('status', __('app.auth.reset_done'));
    }
}
