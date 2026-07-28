<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Trinidad & Tobago public holidays, per docs/seed-notes.md C11.
     * All rows have site_id = null (site-wide — applies to every site).
     *
     * Fixed-date holidays are `is_recurring = true`: the generator matches
     * them on month + day every year, so they never need re-entering.
     *
     * @var list<array{date: string, name: string}>
     */
    private const FIXED = [
        ['date' => '2026-01-01', 'name' => "New Year's Day"],
        ['date' => '2026-03-30', 'name' => 'Spiritual Baptist Liberation Day'],
        ['date' => '2026-05-30', 'name' => 'Indian Arrival Day'],
        ['date' => '2026-06-19', 'name' => 'Labour Day'],
        ['date' => '2026-08-01', 'name' => 'Emancipation Day'],
        ['date' => '2026-08-31', 'name' => 'Independence Day'],
        ['date' => '2026-09-24', 'name' => 'Republic Day'],
        ['date' => '2026-12-25', 'name' => 'Christmas Day'],
        ['date' => '2026-12-26', 'name' => 'Boxing Day'],
    ];

    /**
     * Movable feasts — CURRENT YEAR (2026) ONLY, `is_recurring = false`.
     *
     * These fall on a different date every year and MUST be re-entered
     * annually on the Holidays admin screen (see seed-notes C11 and open
     * question E5: someone owns this each January). Eid-ul-Fitr and Divali
     * in particular are only confirmed by government announcement close to
     * the date — the dates below are the expected 2026 dates.
     *
     * @var list<array{date: string, name: string}>
     */
    private const MOVABLE_2026 = [
        ['date' => '2026-02-16', 'name' => 'Carnival Monday'],
        ['date' => '2026-02-17', 'name' => 'Carnival Tuesday'],
        ['date' => '2026-03-20', 'name' => 'Eid-ul-Fitr'],
        ['date' => '2026-04-03', 'name' => 'Good Friday'],
        ['date' => '2026-04-06', 'name' => 'Easter Monday'],
        ['date' => '2026-06-04', 'name' => 'Corpus Christi'],
        ['date' => '2026-11-08', 'name' => 'Divali'],
    ];

    public function run(): void
    {
        foreach (self::FIXED as $holiday) {
            Holiday::firstOrCreate(
                ['site_id' => null, 'date' => $holiday['date']],
                ['name' => $holiday['name'], 'is_recurring' => true],
            );
        }

        foreach (self::MOVABLE_2026 as $holiday) {
            Holiday::firstOrCreate(
                ['site_id' => null, 'date' => $holiday['date']],
                ['name' => $holiday['name'], 'is_recurring' => false],
            );
        }
    }
}
