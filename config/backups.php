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

    /*
    |--------------------------------------------------------------------------
    | Off-site copies
    |--------------------------------------------------------------------------
    |
    | The `backup-offsite` container copies each dump to a network share and
    | writes its result to `.offsite-status.json` in the backup directory.
    | That file is the only thing the application knows about the share: the
    | app container deliberately does not mount it, because a mount that can
    | fail must not be able to stop the app from booting.
    |
    | Freshness is judged on when that file was last WRITTEN, never on what it
    | says. If the share is unreachable, Docker cannot mount the volume and
    | the container never starts — so the script never runs, never records a
    | failure, and yesterday's file sits there still saying "ok". An age check
    | catches that; reading the message would not.
    |
    | `share` is only used to decide whether off-site is expected at all. With
    | it unset, a missing status file is "not configured" rather than a fault.
    |
    */

    'offsite' => [
        'share' => env('BACKUP_OFFSITE_SHARE', ''),

        /*
         * Read only to tell three states apart, never used to authenticate —
         * the CIFS mount is Docker's job, not the application's.
         *
         *   share empty                  -> off-site is not set up
         *   share set, credentials empty -> set up but not finished
         *   both set                     -> expected to be working, and a
         *                                   stale status file is a fault
         *
         * Without the middle state, filling in the share days before the
         * service can start makes every health check fail in the meantime,
         * and a check that cries wolf gets ignored when it matters.
         */
        'username' => env('BACKUP_OFFSITE_USERNAME', ''),
        'status_file' => '.offsite-status.json',
        'retention_days' => (int) env('BACKUP_OFFSITE_RETENTION_DAYS', 30),
        'max_age_hours' => (int) env('BACKUP_OFFSITE_MAX_AGE_HOURS', 36),
    ],

];
