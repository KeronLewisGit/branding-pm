<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe seed. Everything called here is idempotent and contains
     * only master data — safe to run (and re-run) on a live install.
     *
     * DemoSeeder is deliberately NOT called from here. It creates demo users
     * and 30 days of fabricated historical runs, which must never land in a
     * production database. Run it explicitly when you want demo data:
     *
     *     php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SiteSeeder::class,
            LocationSeeder::class,
            PartSeeder::class,
            MachineSeeder::class,
            ChecklistTemplateSeeder::class,
            HolidaySeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
