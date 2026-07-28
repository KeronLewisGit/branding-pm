<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checklist Run Generation
    |--------------------------------------------------------------------------
    |
    | The time of day (site local) at which the scheduler runs the
    | `checklists:generate` command, and the default grace period applied to
    | new templates before an untouched pending run is marked missed.
    |
    */

    'generation_time' => env('CHECKLISTS_GENERATION_TIME', '05:00'),

    'default_grace_period_hours' => (int) env('CHECKLISTS_DEFAULT_GRACE_PERIOD_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Kiosk
    |--------------------------------------------------------------------------
    |
    | After this many seconds of inactivity the kiosk drops the operator's
    | session and returns to the machine picker. Enforced client-side in
    | Alpine and server-side against session('kiosk.authenticated_at').
    |
    */

    'kiosk_idle_seconds' => (int) env('CHECKLISTS_KIOSK_IDLE_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | Operator PIN
    |--------------------------------------------------------------------------
    |
    | PIN length constraints and brute-force lockout for the shop-floor
    | kiosk PIN login.
    |
    */

    'pin_min_length' => (int) env('CHECKLISTS_PIN_MIN_LENGTH', 4),

    'pin_max_length' => (int) env('CHECKLISTS_PIN_MAX_LENGTH', 6),

    'pin_max_attempts' => (int) env('CHECKLISTS_PIN_MAX_ATTEMPTS', 5),

    'pin_lockout_minutes' => (int) env('CHECKLISTS_PIN_LOCKOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Signatures
    |--------------------------------------------------------------------------
    |
    | Where captured signature images are stored (milestone 5).
    |
    */

    'signature_disk' => env('CHECKLISTS_SIGNATURE_DISK', 'public'),

    'signature_path' => env('CHECKLISTS_SIGNATURE_PATH', 'signatures'),

];
