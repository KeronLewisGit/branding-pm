<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Machine;
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
     *
     * @var list<array{code: string, name: string, location: string, manufacturer: ?string}>
     */
    private const MACHINES = [
        [
            'code' => 'matan',
            'name' => 'MATAN',
            'location' => 'Digital Print',
            'manufacturer' => null,
        ],
        [
            'code' => 'hp-stitch-1000',
            'name' => 'HP Stitch 1000',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
        ],
        [
            'code' => 'hp-r2000-fb',
            'name' => 'HP R2000 [FB]',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
        ],
        [
            'code' => 'hp-570-latex',
            'name' => 'HP 570 Latex',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
        ],
        [
            'code' => 'hp-800w',
            'name' => 'HP 800W',
            'location' => 'Digital Print',
            'manufacturer' => 'HP',
        ],
        [
            'code' => 'esko-c64-kongsberg',
            'name' => 'ESKO C64 Kongsberg',
            'location' => 'ESKO Router Room',
            'manufacturer' => 'ESKO',
        ],
        [
            'code' => 'hiker-grommet-machine',
            'name' => 'Hiker Grommet Machine',
            'location' => 'Production Floor',
            'manufacturer' => null,
        ],
        [
            'code' => 'mistral-1650-65-laminator',
            'name' => 'Mistral 1650-65 Laminator',
            'location' => 'Digital Finishing',
            'manufacturer' => null,
        ],
        [
            'code' => 'miller-112-cross-seamer',
            'name' => 'Miller 112 Cross Seamer',
            'location' => 'Production Floor',
            'manufacturer' => 'Miller',
        ],
        [
            'code' => 'monti-antonio',
            'name' => 'Monti Antonio',
            'location' => 'Digital Finishing',
            'manufacturer' => 'Monti Antonio',
        ],
        [
            'code' => 'rolls-roller',
            'name' => 'Rolls Roller',
            'location' => 'Digital Finishing',
            'manufacturer' => 'RollsRoller',
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

        }
    }
}
