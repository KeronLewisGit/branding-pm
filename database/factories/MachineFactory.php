<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    protected $model = Machine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'code' => fake()->unique()->slug(2), // the QR sticker slug
            'name' => ucwords(fake()->words(2, true)).' '.fake()->numberBetween(100, 999),
            'manufacturer' => null,
            'model' => null,
            'asset_tag' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
