<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Enums\KioskRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureKioskDevice;
use App\Models\KioskEnrolmentRequest;
use App\Support\DeviceReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Ask for this browser to become a kiosk", for people who may not enrol one
 * themselves.
 *
 * Enrolling is a trust decision and stays with `kiosk.activate`. An operator
 * at an unenrolled tablet previously had no move at all except finding a
 * supervisor, and being stuck is the thing people work around — by borrowing
 * a login, or by going back to paper.
 *
 * The claim cookie is the mechanism worth understanding. Enrolment works by
 * setting a cookie, and a cookie can only be set on the browser making the
 * request, so an approval granted at a supervisor's desk cannot by itself
 * enrol the operator's tablet. The asking browser is given a claim token when
 * it requests; once a supervisor approves, that browser redeems the token and
 * receives the real device cookie. Nothing else can redeem it.
 */
class KioskEnrolmentRequestController extends Controller
{
    /**
     * Cookie holding this browser's claim token.
     *
     * Separate from EnsureKioskDevice::COOKIE and never a substitute for it:
     * holding a claim proves only that this browser asked, never that it was
     * approved. The `kiosk` middleware does not look at this cookie at all.
     */
    public const CLAIM_COOKIE = 'kiosk_enrolment_claim';

    /**
     * A claim outlives a shift but not a season. Long enough that a request
     * made on Friday still works on Monday; short enough that a tablet which
     * passed through three hands does not carry a live claim.
     */
    public const CLAIM_LIFETIME_MINUTES = 60 * 24 * 14;

    public function create(Request $request): View
    {
        return view('kiosk.request', [
            // An operator on an already-enrolled browser has nothing to ask
            // for; the nav does not offer this, but a bookmark might.
            'alreadyEnrolled' => EnsureKioskDevice::enrolledDevice($request) !== null,
            'pending' => $this->currentRequest($request),
            'suggestedName' => $this->suggestName($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'suggested_name' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], [
            'suggested_name' => __('app.kiosk_devices.name'),
            'note' => __('app.kiosk_requests.note'),
        ]);

        /*
         * One open request per browser. Asking twice is what an operator does
         * when nothing visibly happens the first time, and a queue with the
         * same tablet in it four times is a queue a supervisor stops reading.
         */
        $existing = $this->currentRequest($request);

        if ($existing !== null && $existing->status->isOpen()) {
            return redirect()->route('kiosk.request')
                ->with('status', __('app.kiosk_requests.already_pending'));
        }

        $claimToken = KioskEnrolmentRequest::newClaimToken();

        $enrolmentRequest = KioskEnrolmentRequest::create([
            'requested_by_id' => $request->user()->id,
            'claim_token' => $claimToken,
            'suggested_name' => $validated['suggested_name'],
            'note' => $validated['note'] ?? null,
            'device_info' => DeviceReport::from($request),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'requested_ip' => $request->ip(),
            'status' => KioskRequestStatus::Pending,
        ]);

        Cookie::queue(cookie(
            self::CLAIM_COOKIE,
            $claimToken,
            self::CLAIM_LIFETIME_MINUTES,
        ));

        activity('kiosk')
            ->causedBy($request->user())
            ->performedOn($enrolmentRequest)
            ->withProperties(['ip' => $request->ip()])
            ->log('kiosk.enrolment_requested');

        return redirect()->route('kiosk.request')
            ->with('status', __('app.kiosk_requests.submitted'));
    }

    /**
     * Redeem an approved request — the step that must run in the browser that
     * asked, because this is where the device cookie gets set.
     */
    public function claim(Request $request): RedirectResponse
    {
        $enrolmentRequest = $this->currentRequest($request);

        /*
         * Deliberately one message for "no claim", "not approved yet" and
         * "already redeemed". A browser holding somebody else's stale token
         * learns nothing about whether it was a real request.
         */
        if ($enrolmentRequest === null
            || $enrolmentRequest->status !== KioskRequestStatus::Approved
            || $enrolmentRequest->device === null
            || ! $enrolmentRequest->device->is_active) {
            return redirect()->route('kiosk.request')
                ->with('error', __('app.kiosk_requests.nothing_to_claim'));
        }

        $device = $enrolmentRequest->device;

        $device->forceFill([
            'device_info' => DeviceReport::from($request),
            'enrolled_at' => now(),
            // The reviewer authorised it; the operator only redeemed it. The
            // fleet list must name the person who made the trust decision.
            'enrolled_by_id' => $enrolmentRequest->reviewed_by_id,
            'enrolled_ip' => $request->ip(),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->save();

        $enrolmentRequest->forceFill([
            'status' => KioskRequestStatus::Claimed,
            'claimed_at' => now(),
        ])->save();

        Cookie::queue(cookie(
            EnsureKioskDevice::COOKIE,
            $device->token,
            EnsureKioskDevice::COOKIE_LIFETIME_MINUTES,
        ));

        // Spent. Leaving it would let a browser re-redeem after the device is
        // revoked and re-created under the same request.
        Cookie::queue(Cookie::forget(self::CLAIM_COOKIE));

        activity('kiosk')
            ->causedBy($request->user())
            ->performedOn($device)
            ->withProperties([
                'ip' => $request->ip(),
                'via' => 'operator_request',
                'requested_by' => $enrolmentRequest->requested_by_id,
                'approved_by' => $enrolmentRequest->reviewed_by_id,
            ])
            ->log('kiosk.device_enrolled');

        return redirect()->route('kiosk.home')
            ->with('status', __('app.kiosk.enrolled', ['name' => $device->name]));
    }

    /**
     * The request this browser is holding a claim for, if any.
     */
    public static function currentRequest(Request $request): ?KioskEnrolmentRequest
    {
        $token = $request->cookie(self::CLAIM_COOKIE);

        if (! is_string($token) || $token === '') {
            return null;
        }

        return KioskEnrolmentRequest::query()
            ->with('device')
            ->where('claim_token', $token)
            ->first();
    }

    /**
     * A name a supervisor can recognise, so the request does not arrive called
     * "Tablet". Overridable by the operator, and again by the reviewer.
     */
    private function suggestName(Request $request): string
    {
        $type = \App\Support\DeviceType::detect($request->userAgent());

        return __('app.kiosk_requests.suggested_name', [
            'type' => $type->label(),
            'user' => $request->user()?->full_name ?? '',
        ]);
    }
}
