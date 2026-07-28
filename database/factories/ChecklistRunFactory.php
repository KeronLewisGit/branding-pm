<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Enums\Shift;
use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistRun>
 */
class ChecklistRunFactory extends Factory
{
    protected $model = ChecklistRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_template_id' => ChecklistTemplate::factory(),
            // Keep machine_id consistent with the template's machine.
            'machine_id' => fn (array $attributes): int => ChecklistTemplate::query()
                ->findOrFail($attributes['checklist_template_id'])
                ->machine_id,
            'template_version' => 1,
            'scheduled_for' => now()->toDateString(),
            'shift' => Shift::All,
            'status' => RunStatus::Pending,
            'started_at' => null,
            'submitted_at' => null,
            'operator_id' => null,
            'operator_signature_path' => null,
            'operator_signed_at' => null,
            'supervisor_id' => null,
            'supervisor_signature_path' => null,
            'supervisor_signed_at' => null,
            'supervisor_comment' => null,
            'notes' => null,
            'downtime_minutes' => null,
        ];
    }

    // ── Status states ────────────────────────────────────────────────

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::Pending,
            'started_at' => null,
            'submitted_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::InProgress,
            'started_at' => now()->subMinutes(30),
            'operator_id' => User::factory(),
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::Submitted,
            'started_at' => now()->subMinutes(45),
            'submitted_at' => now()->subMinutes(5),
            'operator_id' => User::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::Approved,
            'started_at' => now()->subHours(3),
            'submitted_at' => now()->subHours(2),
            'operator_id' => User::factory(),
            'supervisor_id' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::Rejected,
            'started_at' => now()->subHours(3),
            'submitted_at' => now()->subHours(2),
            'operator_id' => User::factory(),
            'supervisor_id' => User::factory(),
            'supervisor_comment' => fake()->sentence(),
        ]);
    }

    public function missed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::Missed,
            'scheduled_for' => now()->subDays(3)->toDateString(),
            'started_at' => null,
            'submitted_at' => null,
            'operator_id' => null,
        ]);
    }

    // ── Shift states ─────────────────────────────────────────────────

    public function dayShift(): static
    {
        return $this->state(fn (array $attributes): array => ['shift' => Shift::Day]);
    }

    public function nightShift(): static
    {
        return $this->state(fn (array $attributes): array => ['shift' => Shift::Night]);
    }
}
