<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Support\DeviceType;

/**
 * What an administrator says a kiosk device *is*, chosen from a list when the
 * device is created.
 *
 * Distinct from App\Support\DeviceType, and the difference matters:
 *
 *   - `KioskDeviceKind` is **declared** by a person and stored. It decides how
 *     the admin screen offers to enrol the thing, and what the fleet list
 *     calls it.
 *   - `DeviceType` is **guessed** from a User-Agent at request time. It only
 *     ever picks a noun in a sentence.
 *
 * Keeping them apart is what lets the admin screen notice that a device
 * recorded as a tablet was actually enrolled from a laptop — see
 * `matches()`.
 */
enum KioskDeviceKind: string
{
    use HasOptions;

    case Tablet = 'tablet';
    case Laptop = 'laptop';
    case Desktop = 'desktop';
    case Phone = 'phone';
    case Other = 'other';

    public function label(): string
    {
        return __('app.kiosk_devices.kind.'.$this->value);
    }

    /**
     * How this kind of device is enrolled.
     *
     * `browser` — the machine is very likely the one the administrator is
     * sitting at, so "Enrol this browser" is offered first. You cannot scan a
     * QR code with the screen displaying it.
     *
     * `scan` — a separate handheld device is carried to the screen, so the QR
     * code leads.
     *
     * Both methods stay available for every kind; this only decides which is
     * presented first, because guessing wrong should cost a scroll and not a
     * dead end.
     */
    public function enrolmentMethod(): string
    {
        return match ($this) {
            self::Laptop, self::Desktop => 'browser',
            self::Tablet, self::Phone => 'scan',
            // Unknown hardware: a shop-floor panel PC, a thin client. Offer
            // the browser route first, since something that cannot scan is
            // the more awkward case to be stuck in.
            self::Other => 'browser',
        };
    }

    /**
     * Is a User-Agent verdict consistent with what was declared?
     *
     * Used to flag a device recorded as a tablet that is being used from a
     * laptop, or the reverse — usually a sign that an enrolment link was
     * opened on the wrong machine, which is worth seeing in a fleet list.
     *
     * `Other` matches anything by definition, and an unrecognised User-Agent
     * accuses nobody.
     */
    public function matches(DeviceType $detected): bool
    {
        if ($this === self::Other || $detected === DeviceType::Unknown) {
            return true;
        }

        return match ($this) {
            self::Tablet => $detected === DeviceType::Tablet,
            self::Phone => $detected === DeviceType::Phone,
            self::Laptop, self::Desktop => $detected === DeviceType::Computer,
            self::Other => true,
        };
    }
}
