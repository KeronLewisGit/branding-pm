<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Site;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * The four locations from the spec, all on the Ground Floor.
     */
    public function run(): void
    {
        $site = Site::where('code', 'BR23')->firstOrFail();

        $locations = [
            'Digital Print',
            'Digital Finishing',
            'Production Floor',
            'ESKO Router Room',
        ];

        foreach ($locations as $name) {
            Location::firstOrCreate(
                ['site_id' => $site->id, 'name' => $name],
                ['floor' => 'Ground Floor'],
            );
        }
    }
}
