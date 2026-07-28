<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Registration — for the orchestrator (bootstrap/app.php)
 |--------------------------------------------------------------------------
 | Add the alias inside ->withMiddleware(function (Middleware $middleware)):
 |
 |     $middleware->alias([
 |         'kiosk.idle' => App\Http\Middleware\EnforceKioskIdleTimeout::class,
 |     ]);
 |
 | Apply it AFTER the device check on every kiosk route:
 |
 |     Route::middleware(['kiosk', 'kiosk.idle'])->group(function () { ... });
 |
 | Also apply it (alone) to `runs.show` and any other authenticated route a
 | kiosk session can land on, so a signed-in operator who walks away is
 | dropped there too. It is a no-op for ordinary password sessions, which
 | never carry session('kiosk.authenticated_at').
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side half of the 2-minute kiosk idle drop (BUILD-CONTRACT §6).
 *
 * THIS is the authoritative check. The Alpine `idleRelease` component in
 * resources/js/app.js merely posts kiosk.release as a convenience; a tablet
 * with JavaScript wedged, a dead battery on resume, or a mischievous user
 * still gets logged out here on the next request.
 */
class EnforceKioskIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedAt = $request->session()->get('kiosk.authenticated_at');

        if (Auth::check() && $authenticatedAt !== null) {
            $idleSeconds = (int) config('checklists.kiosk_idle_seconds');

            // Server clock only — never a client-supplied timestamp.
            if ((now()->timestamp - (int) $authenticatedAt) > $idleSeconds) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // The device cookie is untouched: the tablet stays enrolled
                // and lands back on the machine picker.
                return redirect()
                    ->route('kiosk.home')
                    ->with('status', __('app.kiosk.idle_released'));
            }

            // Any non-idle request restarts the server-side clock.
            $request->session()->put('kiosk.authenticated_at', now()->timestamp);
        }

        return $next($request);
    }
}
