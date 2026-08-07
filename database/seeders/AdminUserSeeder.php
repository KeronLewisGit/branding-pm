<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Exactly ONE admin account — this is all a production install gets.
     * Demo users live in DemoSeeder, which DatabaseSeeder never calls.
     *
     * The password comes from `ADMIN_PASSWORD`. When that is not set, a
     * random one is generated and printed **once**.
     *
     * It used to fall back to a constant in this file. That is a published
     * credential: this repository states the password of every install where
     * nobody changed it, and "we printed a warning" is not a control. A
     * random password cannot be looked up, and an operator who misses it can
     * reset it with `php artisan tinker` — an inconvenience, where the old
     * behaviour was a standing vulnerability.
     */
    public function run(): void
    {
        $configured = (string) env('ADMIN_PASSWORD', '');
        $generated = $configured === '';

        // 24 URL-safe characters ≈ 143 bits. Str::password() would include
        // symbols that get mangled when pasted through a terminal.
        $password = $generated ? Str::random(24) : $configured;

        $admin = User::firstOrCreate(
            ['employee_number' => 'ADMIN-0001'],
            [
                'full_name' => 'System Administrator',
                'email' => env('ADMIN_EMAIL', 'admin@example.com'),
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

        if (! $admin->wasRecentlyCreated) {
            return;
        }

        if (! $generated) {
            $this->command?->info('AdminUserSeeder: admin created with the password from ADMIN_PASSWORD.');

            return;
        }

        // Printed to the console only — never logged, never stored in the
        // clear, and unrecoverable once this scrollback is gone.
        $this->command?->warn(str_repeat('=', 72));
        $this->command?->warn('ADMIN PASSWORD — SHOWN ONCE, NOT RECOVERABLE');
        $this->command?->warn(str_repeat('=', 72));
        $this->command?->line('  Sign in with: '.$admin->email.'  (or '.$admin->employee_number.')');
        $this->command?->line('  Password:     '.$password);
        $this->command?->warn(str_repeat('=', 72));
        $this->command?->warn('Write it down now, then change it after signing in.');
        $this->command?->warn('Set ADMIN_PASSWORD in .env to choose your own instead.');
    }
}
