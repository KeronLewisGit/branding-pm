<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\KioskDevice;
use App\Support\DeviceType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `kiosk` middleware alias (registered in bootstrap/app.php).
 *
 * A tablet is enrolled as a device by KioskEnrolmentController, which sets
 * a long-lived, httpOnly `kiosk_device` cookie holding kiosk_devices.token.
 * The cookie goes through Laravel's EncryptCookies middleware, so its value
 * is encrypted AND authenticated (signed) — a forged or tampered cookie
 * decrypts to nothing and the request is rejected.
 */
class EnsureKioskDevice
{
    /**
     * Cookie carrying the kiosk device token.
     */
    public const COOKIE = 'kiosk_device';

    /**
     * Cookie lifetime — about five years. The enrolment is meant to
     * outlive every session on the tablet.
     */
    public const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365 * 5;

    public function handle(Request $request, Closure $next): Response
    {
        $device = self::resolveDevice($request);

        if ($device === null) {
            // Absent, unknown, or deactivated device — plain instructions,
            // no hint about which of those it was.
            //
            // The device type only picks the noun in the copy ("this tablet"
            // vs "this computer"). It is read from a User-Agent, which the
            // client controls, so it must never influence anything but
            // wording.
            $userAgent = $request->userAgent();
            $deviceType = DeviceType::detect($userAgent);

            return response()->view('kiosk.not-enrolled', [
                'deviceType' => $deviceType,
                'mayBeTablet' => $deviceType->mayBeATabletInDisguise($userAgent),
                // The machine whose sticker was scanned, so activation can
                // return the device to it afterwards. Passed on as the raw
                // route parameter and resolved against the machines table by
                // KioskActivationController — it is never trusted here.
                'machineCode' => $this->scannedMachineCode($request),
            ], Response::HTTP_FORBIDDEN);
        }

        $this->touchLastSeen($device, $request);

        $request->attributes->set('kioskDevice', $device);

        return $next($request);
    }

    /**
     * The device the `kiosk` middleware resolved for this request, if any.
     */
    public static function device(Request $request): ?KioskDevice
    {
        $device = $request->attributes->get('kioskDevice');

        return $device instanceof KioskDevice ? $device : null;
    }

    /**
     * The enrolled device for this browser, resolved from the cookie.
     *
     * `device()` reads what the `kiosk` middleware already put on the request,
     * which is right inside the kiosk and useless outside it: office pages run
     * the `auth` group, never `kiosk`, so that attribute is simply absent and
     * `device()` reports "not enrolled" on a browser that is. The office nav
     * needs the real answer to decide whether "Kiosk mode" enters the kiosk or
     * offers to set one up.
     *
     * Memoised on the request, so a layout can ask without costing a query per
     * call, and so this agrees with the middleware when both run.
     */
    public static function enrolledDevice(Request $request): ?KioskDevice
    {
        if ($request->attributes->has('kioskDevice')) {
            return self::device($request);
        }

        $device = self::resolveDevice($request);

        $request->attributes->set('kioskDevice', $device);

        return $device;
    }

    /**
     * The `{code}` from `/m/{code}`, when that is the route being blocked.
     *
     * Only read from the route parameter, never from the query string or a
     * header: this ends up in a link on the 403 page, and the one thing it
     * must not become is somewhere an attacker can put a value of their
     * choosing in front of an administrator about to tap it.
     */
    private function scannedMachineCode(Request $request): ?string
    {
        $code = $request->route('code');

        return is_string($code) && $code !== '' ? $code : null;
    }

    private static function resolveDevice(Request $request): ?KioskDevice
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return KioskDevice::query()
            ->active()
            ->where('token', $token)
            ->first();
    }

    /**
     * Record the device as seen, at most once per minute per device.
     * Cache::add only succeeds when the key is not already present, so the
     * write is skipped for the rest of the minute.
     */
    private function touchLastSeen(KioskDevice $device, Request $request): void
    {
        if (Cache::add('kiosk-device:'.$device->id.':last-seen', true, 60)) {
            $device->forceFill([
                'last_seen_at' => now(),
                // Recorded so the fleet list can show a device registered as
                // a tablet that is in fact being driven from a laptop.
                // Display only: client-supplied, and truncated to the column
                // width because some User-Agents run to hundreds of bytes.
                'last_user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ])->saveQuietly();
        }
    }
}
