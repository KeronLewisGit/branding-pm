<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Machine;
use App\Models\Part;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MachineSeeder extends Seeder
{
    /**
     * The 11 machines from the spec.
     *
     * - `code` values are the QR-sticker slugs fixed in docs/seed-notes.md C8.
     *   They are printed on physical stickers — do NOT change them.
     * - `manufacturer` follows seed-notes C10: filled only where unambiguous
     *   from the machine name (HP, ESKO, Miller, Monti Antonio, RollsRoller);
     *   MATAN, Hiker and Mistral left null. `model` and `asset_tag` are always
     *   null — inventing asset tags would put fiction into an audit record.
     * - Machine names are verbatim from the spec: `HP R2000 [FB]` keeps its
     *   brackets (B3) and `Rolls Roller` stays two words (B6) even though the
     *   manufacturer spells it `RollsRoller`.
     * - `parts` is the machine_part pivot per seed-notes C9: the union of the
     *   parts on that machine's templates, in first-seen order across those
     *   templates, `sort_order` starting at 0. Monti Antonio and Rolls Roller
     *   have none (their templates list no parts — B12).
     *
     * Part codes are STRINGS ('XXX' is real — B1) and are kept as list
     * values, never array keys, so PHP cannot coerce or reorder them.
     *
     * @var list<array{code: string, name: string, location: string, manufacturer: ?string, parts: list<string>}>
     */
    private const MACHINES = [
        [
            'code' => 'matan',
            'name' => 'MATAN',
            'location' => 'Digital Print',
            'manufacturer' => null,
            'parts' => ['22', '23', '24', '21', '7', 'XXX'],
        ],
        [
            'code' => 'hp-stitch-1000',
            'name' => 'HP Stitch 1000',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
            'parts' => ['22', '23', '24'],
        ],
        [
            'code' => 'hp-r2000-fb',
            'name' => 'HP R2000 [FB]',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
            'parts' => ['22', '23', '21'],
        ],
        [
            'code' => 'hp-570-latex',
            'name' => 'HP 570 Latex',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
            'parts' => ['22', '23', '24'],
        ],
        [
            'code' => 'hp-800w',
            'name' => 'HP 800W',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
            'parts' => ['22', '23', '24'],
        ],
        [
            'code' => 'esko-c64-kongsberg',
            'name' => 'ESKO C64 Kongsberg',
            'location' => 'ESKO Router Room',
            'manufacturer' => 'ESKO',
            'parts' => ['24', '22', '23'],
        ],
        [
            'code' => 'hiker-grommet-machine',
            'name' => 'Hiker Grommet Machine',
            'location' => 'Production Floor',
            'manufacturer' => null,
            'parts' => ['22', '23', '24'],
        ],
        [
            'code' => 'mistral-1650-65-laminator',
            'name' => 'Mistral 1650-65 Laminator',
            'location' => 'Digital Finishing',
            'manufacturer' => null,
            'parts' => ['22', '23', '24'],
        ],
        [
            'code' => 'miller-112-cross-seamer',
            'name' => 'Miller 112 Cross Seamer',
            'location' => 'Production Floor',
            'manufacturer' => 'Miller',
            'parts' => ['26', '27', '25', '24', '23', '22', '28'],
        ],
        [
            'code' => 'monti-antonio',
            'name' => 'Monti Antonio',
            'location' => 'Digital Finishing',
            'manufacturer' => 'Monti Antonio',
            'parts' => [],
        ],
        [
            'code' => 'rolls-roller',
            'name' => 'Rolls Roller',
            'location' => 'Digital Finishing',
            'manufacturer' => 'RollsRoller',
            'parts' => [],
        ],
    ];

    public function run(): void
    {
        $locationIds = Location::pluck('id', 'name');

        foreach (self::MACHINES as $definition) {
            $machine = Machine::firstOrCreate(
                ['code' => $definition['code']],
                [
                    'location_id' => $locationIds[$definition['location']],
                    'name' => $definition['name'],
                    'manufacturer' => $definition['manufacturer'],
                    'model' => null,
                    'asset_tag' => null,
                    'is_active' => true,
                    'notes' => null,
                ],
            );

            foreach ($definition['parts'] as $sortOrder => $partCode) {
                $part = Part::where('part_code', $partCode)->firstOrFail();

                DB::table('machine_part')->updateOrInsert(
                    ['machine_id' => $machine->id, 'part_id' => $part->id],
                    [
                        'sort_order' => $sortOrder,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
