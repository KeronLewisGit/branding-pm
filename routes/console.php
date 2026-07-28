<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| One cron line drives all of this (see README):
|
|     * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
|
| Both commands are idempotent by design, so a missed cron tick, a manual
| re-run, or two schedulers racing cannot double-create or double-flag
| anything. Re-running them is always safe.
*/

// Materialise today's due runs. 05:00 local is before the day shift starts,
// so the sheets exist before the first operator reaches a machine.
// The command resolves "today" in config('app.display_timezone'), not UTC —
// running at 05:00 local would otherwise generate for the wrong date.
Schedule::command('checklists:generate')
    ->dailyAt(config('checklists.generation_time', '05:00'))
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

// Flip untouched pending runs to `missed` once their grace period expires.
// Hourly rather than daily because grace periods are configurable per
// template, so the cutoff is not a single time of day.
// Missed runs are never deleted — they ARE the compliance record.
Schedule::command('checklists:mark-missed')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
