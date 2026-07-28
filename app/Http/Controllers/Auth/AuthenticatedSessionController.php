<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Email-or-employee-number + password login (routes `login`, `logout`).
 *
 * Floor operators without a password sign in at the kiosk with a PIN
 * instead — see Kiosk\KioskSessionController.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login view. Route: GET /login (name `login`).
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request. Route: POST /login.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy the authenticated session. Route: POST /logout (name `logout`).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('app.auth.logged_out'));
    }
}
