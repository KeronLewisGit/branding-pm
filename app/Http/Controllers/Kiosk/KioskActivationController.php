<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Enums\KioskDeviceKind;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureKioskDevice;
use App\Models\KioskDevice;
use App\Models\Machine;
use App\Support\DeviceReport;
use App\Support\DeviceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Turn the phone or tablet in your hand into a kiosk, from the sticker on the
 * machine (routes `kiosk.activate`, `kiosk.activate.store`).
 *
 * ## The journey this completes
 *
 * Every machine already carries a printed QR sticker pointing at
 * `/m/{code}`. On an enrolled tablet that opens the machine's checklists. On
 * anything else it hit a 403 that said "this device is not set up as a kiosk"
 * and stopped there — correct, and a dead end. Setting the device up meant
 * an administrator at a desk generating a separate enrolment link.
 *
 * Now the same sticker completes the loop:
 *
 *   1. Scan it on a new tablet — the not-enrolled screen offers **Activate**.
 *   2. `auth` sends whoever tapped it to the login screen, remembering where
 *      they were going.
 *   3. They sign in; Laravel returns them here.
 *   4. They name the device and confirm.
 *   5. They land on the checklists **for the machine whose sticker they
 *      scanned** — and from then on that sticker goes straight there.
 *
 * ## Who may do it
 *
 * `kiosk.activate` — held by supervisors, maintenance managers and
 * administrators. Deliberately **not** `kiosk.manage`, which governs the
 * fleet screen (renaming, rotating tokens, revoking, deleting) and stays with
 * administrators. Setting up the tablet in your hand is a shop-floor act: a
 * tablet gets dropped mid-shift, a supervisor fetches the spare, and waiting
 * for an administrator to come to the floor is how a shift ends up back on
 * paper. Granting the whole of `kiosk.manage` to allow that would hand a
 * supervisor the power to delete the fleet.
 *
 * It is still a real gate. Enrolment permanently grants a browser the machine
 * grid and the PIN pad, so operators and QA officers cannot do this: without
 * that line, anybody who could photograph a sticker could make their own
 * phone a kiosk.
 *
 * A tablet still cannot be enrolled by someone merely holding it. That is the
 * point of the signed-link route in KioskEnrolmentController, which remains
 * the way to enrol without typing a password on a shared device in front of
 * the shop floor. This route is for when the person holding the tablet is
 * entitled to set it up.
 */
class KioskActivationController extends Controller
{
    /**
     * The activation screen. Route: GET /kiosk/activate?machine={code}.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('kiosk.activate') === true, 403);

        $machine = $this->machineFrom($request);
        $userAgent = (string) $request->userAgent();

        return view('kiosk.activate', [
            'machine' => $machine,
            'detectedType' => DeviceType::detect($userAgent),
            // Offered so a replaced tablet can take over the identity of the
            // one it replaces, keeping its name, location and history rather
            // than leaving a dead row behind.
            'existingDevices' => KioskDevice::query()
                ->orderBy('name')
                ->get(['id', 'name', 'kind', 'last_seen_at']),
            'suggestedName' => $this->suggestName($machine, $userAgent),
        ]);
    }

    /**
     * Enrol this browser and go to the machine. Route: POST /kiosk/activate.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('kiosk.activate') === true, 403);

        /*
         * Resolved before validation, and with `filled()` rather than a
         * validation rule, because of how the form actually posts.
         *
         * The "replacing a device?" control is a <select> with an empty first
         * option, so a browser ALWAYS sends `device_id` — as `""` when the
         * answer is "no, this is a new device". Rules that test presence
         * therefore see it every time.
         *
         * This was `'name' => ['exclude_with:device_id', 'required', …]`,
         * which excluded the name from the validated data on every real
         * submission and produced a 500 one line later. It passed its tests
         * because those posted no `device_id` key at all — which no browser
         * does. `filled()` is false for both `""` and null, so it does not
         * depend on ConvertEmptyStringsToNull being in the stack either.
         */
        $deviceId = $request->filled('device_id') ? (int) $request->input('device_id') : null;

        $validated = $request->validate([
            'device_id' => ['nullable', Rule::exists('kiosk_devices', 'id')],
            // Needed only when creating one. When an existing device is being
            // taken over, whatever is in the name box is ignored below rather
            // than renaming it.
            'name' => [Rule::requiredIf($deviceId === null), 'nullable', 'string', 'max:120'],
            'machine' => ['nullable', 'string', 'max:64'],
        ], [], [
            'name' => __('app.kiosk_devices.name'),
        ]);

        $device = $deviceId !== null
            ? KioskDevice::query()->findOrFail($deviceId)
            : $this->createDevice($request, (string) $validated['name']);

        if (! $device->is_active) {
            return back()->with('error', __('app.kiosk.device_inactive'));
        }

        $device->forceFill([
            'device_info' => DeviceReport::from($request),
            'enrolled_at' => now(),
            'enrolled_by_id' => $request->user()->id,
            'enrolled_ip' => $request->ip(),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ])->save();

        Cookie::queue(cookie(
            EnsureKioskDevice::COOKIE,
            $device->token,
            EnsureKioskDevice::COOKIE_LIFETIME_MINUTES,
        ));

        activity('kiosk')
            ->causedBy($request->user())
            ->performedOn($device)
            ->withProperties([
                'ip' => $request->ip(),
                'via' => 'machine_qr',
                'device_info' => $device->device_info,
            ])
            ->log('kiosk.device_enrolled');

        $machine = $this->machineFrom($request);

        /*
         * Straight to the machine whose sticker was scanned — that is the
         * whole point of coming in this way. The code is resolved to a real
         * machine first and the route rebuilt from it, so nothing
         * client-supplied reaches the redirect.
         */
        return redirect()
            ->route($machine !== null ? 'kiosk.machine' : 'kiosk.home',
                $machine !== null ? ['code' => $machine->code] : [])
            ->with('status', __('app.kiosk.enrolled', ['name' => $device->name]));
    }

    /**
     * The machine whose sticker was scanned, if the code names a real one.
     *
     * Silently ignored when it does not: arriving without a usable machine is
     * not an error worth blocking activation over — the device is still
     * enrolled and lands on the machine grid.
     */
    private function machineFrom(Request $request): ?Machine
    {
        $code = trim((string) $request->input('machine', ''));

        return $code === ''
            ? null
            : Machine::query()->where('code', $code)->first();
    }

    private function createDevice(Request $request, string $name): KioskDevice
    {
        $userAgent = (string) $request->userAgent();

        return KioskDevice::create([
            'name' => trim($name),
            // Recorded as what it looks like, which the administrator can
            // correct on the fleet screen. An iPad requesting desktop Safari
            // is indistinguishable from a MacBook here.
            'kind' => $this->kindFor(DeviceType::detect($userAgent)),
            'token' => Str::random(64),
            'location_id' => $this->machineFrom($request)?->location_id,
            'is_active' => true,
        ]);
    }

    private function kindFor(DeviceType $type): KioskDeviceKind
    {
        return match ($type) {
            DeviceType::Tablet => KioskDeviceKind::Tablet,
            DeviceType::Phone => KioskDeviceKind::Phone,
            // A User-Agent cannot separate a laptop from a desktop, and a
            // wheeled trolley on a shop floor is far likelier to be the
            // former. The fleet screen can correct it.
            DeviceType::Computer => KioskDeviceKind::Laptop,
            default => KioskDeviceKind::Other,
        };
    }

    /**
     * "iPad — Guillotine area". A name somebody can recognise in a list beats
     * an empty box, and the machine's own location is the best clue available
     * about where this device is about to live.
     */
    private function suggestName(?Machine $machine, string $userAgent): string
    {
        $noun = DeviceType::detect($userAgent)->label();

        return $machine === null
            ? $noun
            : __('app.kiosk_devices.suggested_name', ['device' => $noun, 'machine' => $machine->name]);
    }
}
