<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * "I have forgotten my password" — routes `password.request`, `password.email`.
 *
 * Two properties this screen holds on to, both of which the obvious
 * implementation gives away:
 *
 * **It never says whether an address is known.** Success, unknown address,
 * deactivated account and PIN-only operator all produce the same message and
 * the same redirect. Anything else turns this form into a way to test whether
 * a given person works here, which on a small site is a real disclosure.
 *
 * **It never says why somebody is ineligible.** `canResetPasswordByEmail()`
 * decides silently; the reason is a matter for whoever administers the
 * system, and the page says who that is instead.
 *
 * Floor operators are not the audience: they sign in by PIN on a shared
 * tablet, and an administrator clears a PIN from the users screen.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Show the request form. Route: GET /forgot-password.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Email a reset link, if the address belongs to somebody who can use one.
     * Route: POST /forgot-password.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['email' => ['required', 'string', 'email', 'max:190']],
            [],
            ['email' => __('app.auth.email')],
        );

        $user = User::query()->where('email', $validated['email'])->first();

        /*
         * Laravel's broker would send to any matching user. The eligibility
         * check happens here instead, because the broker's own statuses
         * distinguish "no such user" from "sent" — and the caller below
         * deliberately does not.
         */
        if ($user !== null && $user->canResetPasswordByEmail()) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', __('app.auth.reset_link_sent'));
    }
}
