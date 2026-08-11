<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Create (or reset) a named administrator from `.env`.
 *
 * `AdminUserSeeder` creates exactly one account, `ADMIN-0001`, and only on
 * first run — `firstOrCreate` means a second run cannot change the password of
 * an account that already exists. That is right for the install seeder and
 * useless when somebody has lost the password, or wants their own account
 * rather than "System Administrator".
 *
 * This one is idempotent on the EMAIL and will reset the password every time,
 * so it doubles as the recovery path.
 *
 *     php artisan db:seed --class=PilotAdminSeeder
 *
 * Reads four values, all from `.env`:
 *
 *     ADMIN_EMAIL=keron.lewis@labelhouse.com
 *     ADMIN_NAME="Keron Lewis"
 *     ADMIN_PASSWORD=...
 *     ADMIN_EMPLOYEE_NUMBER=ADMIN-0002       # optional, derived if absent
 *
 * The password is NOT in this file and must never be. The repository once
 * carried a constant here, which meant it stated the admin password of every
 * install where nobody changed it — a printed warning is not a control. `.env`
 * is gitignored; a class in `database/seeders` is not.
 */
class PilotAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');
        $name = trim((string) env('ADMIN_NAME', 'Administrator'));

        if ($email === '' || $password === '') {
            // Refused rather than defaulted. A seeder that invents a password
            // when told nothing is how installs end up sharing one.
            $this->command?->error('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env first — this seeder will not invent either.');

            return;
        }

        /*
         * Keyed on email, because that is what somebody signing in types and
         * what they will have told you. `employee_number` is unique too, so a
         * default is derived rather than demanded: ADMIN-0002 onwards, leaving
         * ADMIN-0001 to the install seeder.
         */
        $employeeNumber = trim((string) env('ADMIN_EMPLOYEE_NUMBER', ''));

        if ($employeeNumber === '') {
            $existing = User::query()->where('email', $email)->value('employee_number');
            $employeeNumber = $existing ?? sprintf('ADMIN-%04d', User::query()->count() + 1);
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'employee_number' => $employeeNumber,
                'full_name' => $name,
                // The model's 'hashed' cast hashes this on assignment; the
                // plaintext never reaches the database.
                'password' => $password,
                'is_active' => true,
                // Without a site this account sees no machines at all —
                // MachineScope returns nothing for a user with neither a
                // default site nor an assignment.
                'default_site_id' => User::query()->where('email', $email)->value('default_site_id')
                    ?? Site::query()->value('id'),
            ],
        );

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        $this->command?->info(sprintf(
            '%s %s (%s / %s) — password set from ADMIN_PASSWORD.',
            $user->wasRecentlyCreated ? 'Created' : 'Updated',
            $user->full_name,
            $user->email,
            $user->employee_number,
        ));
    }
}
