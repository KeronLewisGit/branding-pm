<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureKioskDevice;
use App\Models\ChecklistRun;
use App\Models\KioskDevice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * PIN sign-in for a run on an enrolled shop-floor tablet.
 *
 * Routes (all behind the `kiosk` middleware, so a device is guaranteed):
 *   GET  /kiosk/pin/{user}  name `kiosk.pin.show` → create()   (PIN pad)
 *   POST /kiosk/pin         name `kiosk.pin`      → store()
 *   POST /kiosk/release     name `kiosk.release`  → destroy()
 *
 * Security (SPEC §Security): CSRF on every POST; attempts rate-limited per
 * user AND device with a lockout that survives reloads (RateLimiter, cache
 * backed — not session); the PIN is never logged, never in a URL, never
 * flashed, never echoed; timestamps are server-side now().
 */
class KioskSessionController extends Controller
{
    /**
     * Show the PIN pad for a chosen operator (reached from the
     * Kiosk\OperatorPicker "tap your name" grid). Optional ?run={id}
     * carries the run the operator is signing in for.
     */
    public function create(Request $request, User $user): View|RedirectResponse
    {
        if (! $user->is_active || ! $user->hasPin()) {
            return redirect()
                ->route('kiosk.home')
                ->with('error', __('app.kiosk.operator_unavailable'));
        }

        $run = null;

        if ($request->query('run') !== null) {
            $run = ChecklistRun::query()
                ->with(['template', 'machine'])
                ->find((int) $request->query('run'));
        }

        return view('kiosk.pin', [
            'operator' => $user,
            'run' => $run,
        ]);
    }

    /**
     * Attempt the PIN. Route: POST /kiosk/pin (name `kiosk.pin`).
     */
    public function store(Request $request): RedirectResponse
    {
        $device = EnsureKioskDevice::device($request);

        abort_if($device === null, 403);

        /*
         * Validated manually (not $request->validate()) so a failure never
         * triggers the automatic redirect()->withInput() — the PIN must not
         * be flashed into the session.
         */
        $validator = Validator::make(
            $request->only(['user_id', 'employee_number', 'run_id', 'pin']),
            [
                'user_id' => ['nullable', 'required_without:employee_number', 'integer'],
                'employee_number' => ['nullable', 'required_without:user_id', 'string', 'max:32'],
                'run_id' => ['nullable', 'integer'],
                'pin' => ['required', 'string', 'max:32'],
            ],
        );

        if ($validator->fails()) {
            return redirect()
                ->back(fallback: route('kiosk.home'))
                ->withErrors(['pin' => __('app.kiosk.pin_incorrect')]);
        }

        /** @var array{user_id?: int|string|null, employee_number?: string|null, run_id?: int|string|null, pin: string} $input */
        $input = $validator->validated();

        $identifier = $this->identifier($input);
        $key = self::throttleKey($identifier, $device);
        $maxAttempts = (int) config('checklists.pin_max_attempts');
        $lockoutSeconds = (int) config('checklists.pin_lockout_minutes') * 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->lockedResponse($key);
        }

        $user = $this->resolveUser($input);

        // Every failure mode — unknown user, inactive, no PIN set, wrong
        // PIN — costs an attempt and gets the same generic message.
        $authenticated = $user !== null
            && $user->is_active
            && $user->hasPin()
            && Hash::check($input['pin'], (string) $user->pin);

        if (! $authenticated) {
            RateLimiter::hit($key, $lockoutSeconds);

            activity('kiosk')
                ->withProperties([
                    'device_id' => $device->id,
                    'identifier' => $identifier, // the tapped name / number — never the PIN
                    'ip' => $request->ip(),
                ])
                ->log('kiosk.pin_failed');

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                return $this->lockedResponse($key);
            }

            return redirect()
                ->back(fallback: route('kiosk.home'))
                ->withErrors(['pin' => __('app.kiosk.pin_incorrect')])
                ->with('pin_attempts_remaining', RateLimiter::remaining($key, $maxAttempts));
        }

        RateLimiter::clear($key);

        // Deliberately no "remember" on a shared tablet.
        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->put([
            'kiosk.authenticated_at' => now()->timestamp,
            'kiosk.device_id' => $device->id,
        ]);

        activity('kiosk')
            ->causedBy($user)
            ->withProperties(['device_id' => $device->id, 'ip' => $request->ip()])
            ->log('kiosk.pin_succeeded');

        $runId = isset($input['run_id']) && $input['run_id'] !== null ? (int) $input['run_id'] : null;

        return $runId !== null
            ? redirect()->route('runs.show', ['run' => $runId])
            : redirect()->intended(route('kiosk.home', absolute: false));
    }

    /**
     * Release the operator session back to the kiosk. The device cookie is
     * untouched — the tablet stays enrolled.
     * Route: POST /kiosk/release (name `kiosk.release`).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Drops the kiosk.* session keys too. Cookies queued elsewhere are
        // unaffected; the `kiosk_device` cookie survives.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('kiosk.home')
            ->with('status', __('app.kiosk.released'));
    }

    /**
     * Lockout key, per user identifier AND per device. Public + static so
     * the PIN pad view can show remaining attempts / lockout state from the
     * same single source of truth.
     */
    public static function throttleKey(string $identifier, KioskDevice $device): string
    {
        return 'kiosk-pin:'.$device->id.':'.Str::transliterate(Str::lower(trim($identifier)));
    }

    /**
     * @param array{user_id?: int|string|null, employee_number?: string|null} $input
     */
    private function identifier(array $input): string
    {
        if (isset($input['user_id']) && $input['user_id'] !== null) {
            return (string) $input['user_id'];
        }

        return trim((string) ($input['employee_number'] ?? ''));
    }

    /**
     * @param array{user_id?: int|string|null, employee_number?: string|null} $input
     */
    private function resolveUser(array $input): ?User
    {
        if (isset($input['user_id']) && $input['user_id'] !== null) {
            return User::query()->find((int) $input['user_id']);
        }

        $employeeNumber = trim((string) ($input['employee_number'] ?? ''));

        if ($employeeNumber === '') {
            return null;
        }

        return User::query()->where('employee_number', $employeeNumber)->first();
    }

    private function lockedResponse(string $key): RedirectResponse
    {
        $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

        return redirect()
            ->back(fallback: route('kiosk.home'))
            ->withErrors(['pin' => __('app.kiosk.pin_locked', ['minutes' => $minutes])]);
    }
}
