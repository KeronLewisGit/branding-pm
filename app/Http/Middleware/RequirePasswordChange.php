<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send somebody signing in on an issued password to change it first.
 *
 * The flag is set only when an administrator issues a password, so this never
 * catches a PIN-only operator: they have no password to change, and their
 * credential is cleared on the users screen rather than by them.
 *
 * Applied to the office `auth` group and NOT to the kiosk. A kiosk session
 * belongs to whoever tapped their name on a shared tablet; interrupting a
 * shop-floor sheet with a password form would stop the work this system
 * exists to record, and the tablet is not where somebody should be typing a
 * new password anyway.
 */
class RequirePasswordChange
{
    /**
     * Routes that must stay reachable, or the redirect has nowhere to land.
     *
     * The change screen itself and its POST, obviously. Logout too: somebody
     * who cannot get past this must still be able to leave, rather than being
     * held in the application by the thing meant to protect them.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'password.change',
        'password.change.store',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED)) {
            return $next($request);
        }

        // A kiosk session never reaches here (this is not on the kiosk group),
        // but a signed-in office user browsing the kiosk would — and being
        // bounced out of a machine screen mid-shift is worse than waiting.
        if ($request->session()->has('kiosk.authenticated_at')) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
