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
        $device = $this->resolveDevice($request);

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

    private function resolveDevice(Request $request): ?KioskDevice
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
