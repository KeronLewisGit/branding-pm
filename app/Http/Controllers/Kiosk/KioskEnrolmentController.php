<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureKioskDevice;
use App\Models\KioskDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * One-time (per tablet) device enrolment, performed by an admin standing at
 * the tablet. Sets the long-lived `kiosk_device` cookie that the `kiosk`
 * middleware (EnsureKioskDevice) checks on every kiosk request.
 *
 * Both actions require the `kiosk.manage` permission. The routes should
 * additionally sit behind the `auth` middleware; the checks here are
 * defence in depth, not the only gate.
 */
class KioskEnrolmentController extends Controller
{
    /**
     * Enrol THIS browser as the given kiosk device.
     * Suggested route: POST /kiosk/enrol/{device} (name `kiosk.enrol`).
     */
    public function enrol(Request $request, KioskDevice $device): RedirectResponse
    {
        abort_unless($request->user()?->can('kiosk.manage') === true, 403);

        if (! $device->is_active) {
            return redirect()
                ->back(fallback: route('kiosk.home'))
                ->with('error', __('app.kiosk.device_inactive'));
        }

        /*
         * cookie() defaults: httpOnly = true; path/domain/secure/sameSite
         * fall back to config/session.php. The value is encrypted and
         * signed by the EncryptCookies middleware, so the raw token never
         * reaches the client in readable form.
         */
        Cookie::queue(cookie(
            EnsureKioskDevice::COOKIE,
            $device->token,
            EnsureKioskDevice::COOKIE_LIFETIME_MINUTES,
        ));

        activity('kiosk')
            ->causedBy($request->user())
            ->performedOn($device)
            ->withProperties(['ip' => $request->ip()])
            ->log('kiosk.device_enrolled');

        return redirect()
            ->route('kiosk.home')
            ->with('status', __('app.kiosk.enrolled', ['name' => $device->name]));
    }

    /**
     * Enrol THIS browser from a temporary signed link (route
     * `kiosk.enrol.link`, `signed` middleware, NO auth).
     *
     * The reason this exists: `enrol()` above requires an authenticated
     * holder of `kiosk.manage`, which means typing an admin password into
     * the tablet — on a shop floor, in front of whoever is standing there,
     * on a device that is about to be left unattended all shift. The admin
     * generates a link on their own machine instead and scans it with the
     * tablet.
     *
     * What the link is worth if it leaks: the bearer can make a browser look
     * like this kiosk. That grants the machine grid and the PIN pad, nothing
     * more — every action beyond browsing still needs an operator's PIN, and
     * the kiosk session drops after two minutes idle. It is deliberately
     * short-lived (KioskDeviceManager::LINK_TTL_MINUTES) and the device's
     * token can be rotated from the admin screen, which invalidates every
     * browser previously enrolled as it.
     */
    public function enrolViaLink(Request $request, KioskDevice $device): RedirectResponse
    {
        // `signed` has already rejected a tampered or expired URL.
        if (! $device->is_active) {
            return redirect()
                ->route('login')
                ->with('error', __('app.kiosk.device_inactive'));
        }

        Cookie::queue(cookie(
            EnsureKioskDevice::COOKIE,
            $device->token,
            EnsureKioskDevice::COOKIE_LIFETIME_MINUTES,
        ));

        // No causer: nobody is authenticated on the tablet. The IP and the
        // device are what there is to record.
        activity('kiosk')
            ->performedOn($device)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'via' => 'signed_link',
            ])
            ->log('kiosk.device_enrolled');

        return redirect()
            ->route('kiosk.home')
            ->with('status', __('app.kiosk.enrolled', ['name' => $device->name]));
    }

    /**
     * Remove the device cookie from THIS browser. The kiosk_devices row is
     * untouched — the tablet can be re-enrolled at any time.
     * Suggested route: POST /kiosk/unenrol (name `kiosk.unenrol`).
     */
    public function unenrol(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('kiosk.manage') === true, 403);

        Cookie::queue(Cookie::forget(EnsureKioskDevice::COOKIE));

        activity('kiosk')
            ->causedBy($request->user())
            ->withProperties(['ip' => $request->ip()])
            ->log('kiosk.device_unenrolled');

        // Not kiosk.home — this browser would now correctly get the
        // not-enrolled 403 there.
        return redirect()
            ->route('login')
            ->with('status', __('app.kiosk.unenrolled'));
    }
}
