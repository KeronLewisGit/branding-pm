<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What can honestly be learned about a device that enrols itself as a kiosk.
 *
 * ## On MAC addresses
 *
 * This class exists partly to answer a question that keeps being asked, so
 * the answer is written down here rather than rediscovered:
 *
 * **A web application cannot obtain a client's MAC address.** There is no
 * browser API that exposes one, by design — it is a permanent hardware
 * identifier, and handing it to any website would be a tracking vector no
 * vendor will allow. Nor does it travel in an HTTP request: a MAC is a
 * link-layer address, stripped and replaced at every hop, so by the time a
 * request reaches the server the original is long gone. Reading the ARP table
 * server-side only ever sees the same network segment, and under Docker the
 * source address is the bridge gateway.
 *
 * If a MAC is genuinely needed — for a network inventory, or to reserve a
 * DHCP lease — it comes from the DHCP server or the switch, not from here.
 *
 * What replaces it is better for this purpose anyway: the enrolment token in
 * the `kiosk_device` cookie is a durable per-device identity that can be
 * **revoked and rotated**, which a MAC cannot be, and which is spoofable in
 * about ten seconds on any laptop.
 *
 * ## What this does collect
 *
 * Two sources, and the difference matters:
 *
 *   - **Headers**, sent by the browser on every request: User-Agent, and the
 *     Client Hints some browsers add.
 *   - **Measurements**, taken by a small script on the activation screen and
 *     posted with the form: screen size, touch points, timezone.
 *
 * Both are **client-supplied and trivially forged**. Everything here is
 * descriptive — for a person reading the fleet list and deciding whether the
 * "iPad by the guillotine" is in fact an iPad. No permission, no access
 * decision and no authorisation may ever be based on any of it.
 */
final class DeviceReport
{
    /**
     * Fields the activation form is allowed to post, with a maximum length
     * each. An unbounded client-supplied string going into a JSON column is
     * how a row gets filled with a megabyte of nonsense.
     *
     * @var array<string, int>
     */
    private const CLIENT_FIELDS = [
        'screen' => 24,          // "2360 x 1640"
        'viewport' => 24,
        'pixel_ratio' => 8,
        'touch_points' => 4,
        'timezone' => 64,
        'language' => 32,
        'platform' => 64,
    ];

    /**
     * Build the report stored on the device row.
     *
     * @return array<string, string>
     */
    public static function from(Request $request): array
    {
        $report = [];

        $userAgent = (string) $request->userAgent();

        if ($userAgent !== '') {
            $report['user_agent'] = Str::limit($userAgent, 250, '');
        }

        // Detected from the User-Agent — the same value the kiosk screens use
        // to choose between "this tablet" and "this computer".
        $report['detected_type'] = DeviceType::detect($userAgent)->value;

        /*
         * User-Agent Client Hints. Chrome and Edge send the low-entropy ones
         * unasked; `Sec-CH-UA-Model` (the actual handset model) needs to be
         * requested via Accept-CH and is absent on Safari and Firefox
         * entirely. Recorded when offered, never relied upon.
         */
        foreach ([
            'Sec-CH-UA-Platform' => 'platform_hint',
            'Sec-CH-UA-Platform-Version' => 'platform_version',
            'Sec-CH-UA-Model' => 'model',
            'Sec-CH-UA-Mobile' => 'mobile_hint',
        ] as $header => $key) {
            $value = $request->header($header);

            if (is_string($value) && $value !== '') {
                $report[$key] = Str::limit(trim($value, '"'), 64, '');
            }
        }

        foreach (self::CLIENT_FIELDS as $field => $maxLength) {
            $value = $request->input('device.'.$field);

            if (is_scalar($value) && (string) $value !== '') {
                $report[$field] = Str::limit((string) $value, $maxLength, '');
            }
        }

        // Recorded with the same caveat that applies everywhere else in this
        // application: behind Docker's published ports the source address is
        // rewritten to the bridge gateway, so on the pilot this is the
        // gateway and not the tablet.
        $report['ip_at_enrolment'] = (string) $request->ip();

        return $report;
    }

    /**
     * A short human label for the fleet list — "iPad · 2360 × 1640 · touch".
     */
    public static function summarise(?array $report): string
    {
        if ($report === null || $report === []) {
            return '';
        }

        $parts = [];

        if (isset($report['model'])) {
            $parts[] = $report['model'];
        } elseif (isset($report['platform']) || isset($report['platform_hint'])) {
            $parts[] = $report['platform'] ?? $report['platform_hint'];
        }

        if (isset($report['screen'])) {
            $parts[] = $report['screen'];
        }

        if (isset($report['touch_points']) && (int) $report['touch_points'] > 0) {
            $parts[] = __('app.kiosk_devices.touchscreen');
        }

        return implode(' · ', $parts);
    }
}
