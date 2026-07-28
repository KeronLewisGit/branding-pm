<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    /**
     * One site. Name is verbatim from the spec, braces included
     * (see docs/seed-notes.md B2). Code per seed-notes C7.
     */
    public function run(): void
    {
        Site::firstOrCreate(
            ['code' => 'BR23'],
            [
                'name' => 'Branding 23 — {Scarlet Ibis}',
                'working_days' => [1, 2, 3, 4, 5, 6], // Mon–Sat, ISO-8601 weekday numbers
                'timezone' => 'America/Port_of_Spain',
            ],
        );
    }
}
