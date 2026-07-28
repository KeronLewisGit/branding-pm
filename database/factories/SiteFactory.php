<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => 'ST'.fake()->unique()->numerify('####'),
            'working_days' => [1, 2, 3, 4, 5, 6], // Mon–Sat
            'timezone' => 'America/Port_of_Spain',
        ];
    }
}
