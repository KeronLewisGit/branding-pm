<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // part_code is a STRING (production data includes 'XXX') —
            // the letter prefix keeps tests honest about that.
            'part_code' => 'P'.fake()->unique()->numerify('####'),
            'name' => ucwords(fake()->words(3, true)),
            'unit' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
