<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    protected $model = Issue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_run_id' => null,
            'checklist_run_item_id' => null,
            'machine_id' => Machine::factory(),
            'raised_by' => User::factory(),
            'severity' => IssueSeverity::Medium,
            'description' => fake()->sentence(8),
            'status' => IssueStatus::Open,
            'assigned_to' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
        ];
    }

    public function breakdown(): static
    {
        return $this->state(fn (array $attributes): array => [
            'severity' => IssueSeverity::Breakdown,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => IssueStatus::Resolved,
            'assigned_to' => User::factory(),
            'resolved_at' => now(),
            'resolution_notes' => fake()->sentence(),
        ]);
    }
}
