<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * "Choose your own password" — the first thing somebody does after signing in
 * on one an administrator issued.
 *
 * Reachable at any time, not only when forced: an office user who suspects
 * their password is known should not have to log out and use the reset flow to
 * change it.
 */
class ChangePasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.change-password', [
            'forced' => (bool) $request->user()?->must_change_password,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            /*
             * The current password is required even when the change is forced.
             *
             * The account was reached with a password that arrived by email,
             * so the person at the keyboard may be whoever read that mailbox.
             * Asking for it again proves they are the one who was given it,
             * and costs the legitimate user a paste.
             */
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => __('app.auth.change_current_wrong'),
        ]);

        $user->forceFill([
            // The 'hashed' cast handles this on assignment.
            'password' => $validated['password'],
            'must_change_password' => false,
        ])->save();

        activity('auth')
            ->causedBy($user)
            ->withProperties(['ip' => $request->ip()])
            ->log('auth.password_changed');

        /*
         * Every other session for this account is invalidated. If the issued
         * password had been used by somebody else, this is the moment that
         * stops mattering — leaving their session alive would make the change
         * cosmetic.
         */
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('flash.success', __('app.auth.change_done'));
    }
}
