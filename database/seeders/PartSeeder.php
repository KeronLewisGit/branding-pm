<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    /**
     * The parts catalogue from the spec, verbatim — braces and all
     * (docs/seed-notes.md B2). `part_code` is a STRING: one code is
     * literally `XXX` (B1). Never cast to int.
     *
     * Codes are kept as list values, never as array keys — PHP silently
     * turns the key '7' into the integer 7, which is exactly the kind of
     * coercion that would strand the `XXX` row.
     *
     * @var list<array{part_code: string, name: string}>
     */
    private const PARTS = [
        ['part_code' => '7', 'name' => 'Long-term Grease for Bearings'],
        ['part_code' => '21', 'name' => 'Tooth Brush {Soft}'],
        ['part_code' => '22', 'name' => 'General Cleaning Towels {Rags}'],
        ['part_code' => '23', 'name' => 'Isopropyl alcohol'],
        ['part_code' => '24', 'name' => 'Nitrile Gloves'],
        ['part_code' => '25', 'name' => 'Miller 112 - Air Filter'],
        ['part_code' => '26', 'name' => 'Miller 112 - Weld Roller Solenoid'],
        ['part_code' => '27', 'name' => 'Miller 112 - Micro Switch'],
        ['part_code' => '28', 'name' => 'Miller 112 - Standard Solenoid'],
        ['part_code' => 'XXX', 'name' => 'Simple Green'],
    ];

    public function run(): void
    {
        foreach (self::PARTS as $part) {
            Part::firstOrCreate(
                ['part_code' => $part['part_code']],
                [
                    'name' => $part['name'],
                    'unit' => null,
                    'is_active' => true,
                ],
            );
        }
    }
}
