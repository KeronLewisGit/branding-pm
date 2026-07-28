<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Default rows for the `settings` table. `value` is always stored as a
     * string; `type` tells the reader how to cast it (string|int|bool).
     *
     * firstOrCreate keys on `key` only, so values an admin has changed on
     * the settings screen survive a re-seed.
     *
     * @var list<array{key: string, value: string, type: string}>
     */
    private const SETTINGS = [
        // Shift boundaries (local time, America/Port_of_Spain). Clock times
        // are NOT confirmed by the client — see seed-notes E1. Night shift
        // crossing midnight is booked against the date it starts.
        ['key' => 'shift.day_start', 'value' => '07:00', 'type' => 'string'],
        ['key' => 'shift.day_end', 'value' => '19:00', 'type' => 'string'],
        ['key' => 'shift.night_start', 'value' => '19:00', 'type' => 'string'],
        ['key' => 'shift.night_end', 'value' => '07:00', 'type' => 'string'],

        // Kiosk behaviour (spec: 2-minute inactivity release, rate-limited
        // PIN attempts with lockout).
        ['key' => 'kiosk.idle_timeout_seconds', 'value' => '120', 'type' => 'int'],
        ['key' => 'kiosk.pin_max_attempts', 'value' => '5', 'type' => 'int'],
        ['key' => 'kiosk.pin_lockout_minutes', 'value' => '15', 'type' => 'int'],

        // Scheduling defaults.
        ['key' => 'runs.default_grace_period_hours', 'value' => '24', 'type' => 'int'],

        // Reporting.
        ['key' => 'reports.compliance_target_percent', 'value' => '95', 'type' => 'int'],

        // Display timezone fallback (env APP_DISPLAY_TIMEZONE wins).
        ['key' => 'display.timezone', 'value' => 'America/Port_of_Spain', 'type' => 'string'],
    ];

    public function run(): void
    {
        foreach (self::SETTINGS as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type']],
            );
        }
    }
}
