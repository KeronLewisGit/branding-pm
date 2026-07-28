<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'ChangeMe!2026';

    /**
     * Exactly ONE admin account — this is all a production install gets.
     * Demo users live in DemoSeeder, which DatabaseSeeder never calls.
     *
     * Password comes from ADMIN_PASSWORD in .env; a loud warning is printed
     * when the shipped default is used. No PIN — the admin is an office
     * user, not a floor operator.
     */
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD', self::DEFAULT_PASSWORD);

        $admin = User::firstOrCreate(
            ['employee_number' => 'ADMIN-0001'],
            [
                'full_name' => 'System Administrator',
                'email' => 'admin@example.com',
                // The User model's 'hashed' cast hashes this on assignment.
                'password' => $password,
                'pin' => null,
                'is_active' => true,
                'default_site_id' => Site::where('code', 'BR23')->value('id'),
            ],
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        if ($admin->wasRecentlyCreated && $password === self::DEFAULT_PASSWORD) {
            $this->command?->warn(
                'AdminUserSeeder: ADMIN_PASSWORD is not set — the admin account '
                .'(ADMIN-0001 / admin@example.com) was created with the DEFAULT '
                .'password "'.self::DEFAULT_PASSWORD.'". Change it immediately, '
                .'or set ADMIN_PASSWORD in .env and re-seed on a fresh database.'
            );
        }
    }
}
