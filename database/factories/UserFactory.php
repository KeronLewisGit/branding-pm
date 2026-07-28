<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => 'EMP-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // The model's 'hashed' cast hashes these on assignment.
            'password' => 'password',
            'pin' => null,
            'pin_set_at' => null,
            'is_active' => true,
            'default_site_id' => null,
        ];
    }

    // ── Role states ──────────────────────────────────────────────────
    // Roles are created on demand so a factory user is valid even when
    // RolesAndPermissionsSeeder has not run in the test.

    public function operator(): static
    {
        return $this->withRole('operator');
    }

    public function supervisor(): static
    {
        return $this->withRole('supervisor');
    }

    public function manager(): static
    {
        return $this->withRole('maintenance_manager');
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    private function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user->assignRole($role);
        });
    }

    // ── Attribute states ─────────────────────────────────────────────

    public function withPin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pin' => '1234', // hashed by the model cast
            'pin_set_at' => now(),
        ]);
    }

    /**
     * A PIN-only floor operator: no email means no password either.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
