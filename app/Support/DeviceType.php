<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\EnsureKioskDevice;

/**
 * What kind of thing is this request coming from — for wording, and nothing
 * else.
 *
 * Used by the "not set up as a kiosk" screen so it does not tell somebody at
 * a laptop that "this tablet" needs enrolling. It must never gate access or
 * change behaviour: a User-Agent is a self-reported string and anyone can
 * send any value they like. The only cost of getting it wrong here is a noun.
 *
 * Deliberately not a package. A full UA database exists to tell 3,000 phone
 * models apart; this needs four buckets, and a dependency that ships a
 * regularly-updated device list is a maintenance burden the plant IT team
 * would inherit for no gain.
 *
 * @see EnsureKioskDevice
 */
enum DeviceType: string
{
    case Tablet = 'tablet';
    case Phone = 'phone';
    case Computer = 'computer';

    /** Unrecognised — the copy falls back to the neutral "device". */
    case Unknown = 'unknown';

    /**
     * Best guess from a User-Agent string.
     *
     * Order matters: tablets are checked before phones because an Android
     * tablet's UA contains "Android" and omits "Mobile", while an Android
     * phone contains both — so the phone test would otherwise swallow both.
     */
    public static function detect(?string $userAgent): self
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return self::Unknown;
        }

        // iPad, Android tablets, Kindle, Surface in tablet-mode browsers,
        // and anything self-describing as a tablet.
        if (preg_match('/\b(ipad|tablet|kindle|silk|playbook)\b/i', $ua) === 1) {
            return self::Tablet;
        }

        // Android without "Mobile" is conventionally a tablet.
        if (stripos($ua, 'android') !== false && stripos($ua, 'mobile') === false) {
            return self::Tablet;
        }

        if (preg_match('/\b(iphone|ipod|android|windows phone|blackberry|bb10|opera mini|iemobile)\b/i', $ua) === 1) {
            return self::Phone;
        }

        if (preg_match('/\b(windows nt|macintosh|mac os x|cros|x11|linux)\b/i', $ua) === 1) {
            return self::Computer;
        }

        return self::Unknown;
    }

    /**
     * Whether a touch-capable device could be hiding behind this verdict.
     *
     * Since iPadOS 13, Safari on iPad requests desktop sites **by default**
     * and sends a Macintosh User-Agent — byte-for-byte what a MacBook sends.
     * No amount of server-side pattern matching separates them, so the
     * not-enrolled screen ships a small script that corrects `computer` to
     * `tablet` when the browser reports multiple touch points. This flag
     * says when that correction is worth wiring up at all.
     *
     * Checked for Macintosh specifically: a Windows touchscreen laptop also
     * reports touch points, and calling that a tablet would be a worse
     * answer than calling it a computer.
     */
    public function mayBeATabletInDisguise(?string $userAgent): bool
    {
        return $this === self::Computer
            && stripos((string) $userAgent, 'macintosh') !== false;
    }

    /**
     * The noun used in the copy — "tablet", "phone", "computer", "device".
     */
    public function label(): string
    {
        return __('app.device_type.'.$this->value);
    }
}
