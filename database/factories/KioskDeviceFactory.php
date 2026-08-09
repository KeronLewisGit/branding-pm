<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KioskDeviceKind;
use App\Models\KioskDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KioskDevice>
 */
class KioskDeviceFactory extends Factory
{
    protected $model = KioskDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Guillotine', 'Laminator', 'Wide format', 'Finishing'])
                .' '.fake()->randomElement(['tablet', 'terminal']),
            'kind' => KioskDeviceKind::Tablet,
            // The device's durable identity, and the value in the cookie the
            // `kiosk` middleware checks. Random per device, and rotatable.
            'token' => Str::random(64),
            'location_id' => null,
            'last_seen_at' => null,
            'last_user_agent' => null,
            'is_active' => true,
        ];
    }

    /**
     * A device that has been taken out of service. It keeps its row and its
     * history; it simply stops resolving.
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * A device that has actually been used, so the fleet list has something
     * to show for it.
     */
    public function seen(): static
    {
        return $this->state(fn (): array => [
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 240)),
            'last_user_agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Safari/604.1',
        ]);
    }
}
