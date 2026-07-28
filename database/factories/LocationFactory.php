<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            // (site_id, name) is unique — keep names unique across the run.
            'name' => ucwords(fake()->unique()->words(2, true)),
            'floor' => 'Ground Floor',
        ];
    }
}
