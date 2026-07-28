<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ResponseType;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistTemplateItem>
 */
class ChecklistTemplateItemFactory extends Factory
{
    protected $model = ChecklistTemplateItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_template_id' => ChecklistTemplate::factory(),
            'sort_order' => fake()->numberBetween(0, 50),
            'description' => 'Clean '.ucwords(fake()->words(2, true)),
            'response_type' => ResponseType::Check,
            'is_required' => true,
            'guidance' => null,
            'requires_photo_on_fail' => false,
            'is_active' => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
