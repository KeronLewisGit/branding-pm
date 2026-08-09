<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Where the dumps land
    |--------------------------------------------------------------------------
    |
    | The `backup` container writes here through a bind mount. It is on the
    | host filesystem rather than in a named Docker volume on purpose:
    | `docker compose down -v` destroys named volumes, and a backup kept in
    | one would be destroyed by the same command that destroys the database.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('backups')),

    /*
    |--------------------------------------------------------------------------
    | Schedule and retention
    |--------------------------------------------------------------------------
    |
    | Read by the backup container (as environment variables, via
    | docker-compose.yml) and repeated here so `backup:status` can say what it
    | expected to find. Change them in .env, not in one of the two.
    |
    */

    'time' => env('BACKUP_TIME', '02:30'),

    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | When to call it stale
    |--------------------------------------------------------------------------
    |
    | Nightly means a healthy backup is under 24 hours old. 36 allows for a
    | reboot or a clock change across the scheduled time without crying wolf,
    | while still catching a service that has genuinely stopped — which is how
    | backups usually fail: not loudly, but by quietly not happening.
    |
    */

    'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 36),

];
