<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ResponseType;
use App\Enums\RunItemStatus;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistRunItem>
 */
class ChecklistRunItemFactory extends Factory
{
    protected $model = ChecklistRunItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_run_id' => ChecklistRun::factory(),
            'checklist_template_item_id' => null, // snapshot rows survive template deletes
            'sort_order' => fake()->numberBetween(0, 50),
            'description' => 'Clean '.ucwords(fake()->words(2, true)),
            'response_type' => ResponseType::Check,
            'is_required' => true,
            'status' => RunItemStatus::Pending,
            'value_numeric' => null,
            'value_text' => null,
            'fail_reason' => null,
            'completed_at' => null,
            'completed_by' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunItemStatus::Done,
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }

    public function notApplicable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunItemStatus::NotApplicable,
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunItemStatus::Failed,
            'fail_reason' => fake()->sentence(),
            'completed_at' => now(),
            'completed_by' => User::factory(),
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => false,
        ]);
    }
}
