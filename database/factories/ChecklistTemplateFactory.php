<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Frequency;
use App\Enums\WorkCategory;
use App\Models\ChecklistTemplate;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistTemplate>
 */
class ChecklistTemplateFactory extends Factory
{
    protected $model = ChecklistTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'machine_id' => Machine::factory(),
            'name' => ucwords(fake()->unique()->words(3, true)).' Maintenance',
            'work_category' => WorkCategory::Daily,
            'work_description' => fake()->sentence(),
            'frequency' => Frequency::Daily,
            'per_shift' => true,
            'weekly_weekday' => 1,
            'monthly_day' => 1,
            'requires_supervisor_signoff' => true,
            'grace_period_hours' => 24,
            'version' => 1,
            'is_active' => true,
        ];
    }

    // ── Category states (mirror the seed rules in seed-notes C1/C4) ──

    public function daily(): static
    {
        return $this->state(fn (array $attributes): array => [
            'work_category' => WorkCategory::Daily,
            'frequency' => Frequency::Daily,
            'per_shift' => true,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'work_category' => WorkCategory::Weekly,
            'frequency' => Frequency::Weekly,
            'per_shift' => false,
            'weekly_weekday' => 1,
        ]);
    }

    public function general(): static
    {
        return $this->state(fn (array $attributes): array => [
            'work_category' => WorkCategory::General,
            'frequency' => Frequency::Weekly, // general sheets run weekly (C1)
            'per_shift' => false,
            'weekly_weekday' => 1,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
