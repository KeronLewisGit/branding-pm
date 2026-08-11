<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Every permission in the build contract (§5).
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'run.view',
        'run.start',
        'run.complete',
        'run.submit',
        'run.approve',
        'run.reject',
        'run.amend',
        'run.verify',
        'issue.view',
        'issue.create',
        'issue.assign',
        'issue.resolve',
        'machine.view',
        'machine.view_all',
        'machine.manage',
        'template.view',
        'template.manage',
        'schedule.manage',
        'holiday.manage',
        'report.view',
        'export.data',
        'user.manage',
        'role.manage',
        'setting.manage',
        'kiosk.manage',

        /*
         * Deliberately separate from `kiosk.manage`.
         *
         * `kiosk.activate` is "turn the device in my hand into a kiosk, from
         * the sticker on the machine" — a shop-floor act, done standing at
         * the machine, and one a supervisor needs when a tablet is replaced
         * mid-shift and no administrator is on site.
         *
         * `kiosk.manage` is the fleet screen: renaming devices, rotating
         * tokens, revoking and deleting them. That stays with administrators.
         * Granting a supervisor the whole of `kiosk.manage` to let them set
         * up one tablet would hand them all of it.
         */
        'kiosk.activate',
    ];

    /**
     * Permissions granted at each role level. Grants are cumulative:
     * each role receives everything the roles above it in this list have,
     * plus its own additions. `admin` gets every permission.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'operator' => [
            'run.view',
            'run.start',
            'run.complete',
            'run.submit',
            'issue.view',
            'issue.create',
            'machine.view',
            'template.view',
        ],
        'supervisor' => [
            'run.approve',
            'run.reject',
            'issue.assign',
            'issue.resolve',
            'report.view',
            // Cumulative, so maintenance managers get this too — which is
            // right: they are the people most often at a machine with a
            // tablet in hand.
            'kiosk.activate',
        ],
        'maintenance_manager' => [
            'machine.manage',
            'template.manage',
            'schedule.manage',
            'holiday.manage',
            'export.data',
            'run.amend',
        ],
    ];

    /**
     * Roles that are NOT on the cumulative ladder.
     *
     * Quality Assurance is oversight, not seniority. A QA officer reads every
     * sheet in the plant and verifies that the work was done — but cannot
     * complete a check, cannot approve one, and cannot amend one. That
     * separation is the whole point of the role: an auditor asking "who
     * checked the checker?" must not be told "the same person".
     *
     * `machine.view_all` rather than a site scope: quality assurance is a
     * plant-wide function and a QA officer restricted to one site could not
     * do the job.
     *
     * @var array<string, list<string>>
     */
    private const STANDALONE = [
        'quality_assurance' => [
            'run.view',
            'run.verify',
            'issue.view',
            'machine.view',
            'machine.view_all',
            'template.view',
            'report.view',
            'export.data',
        ],
    ];

    public function run(): void
    {
        // Reset the spatie permission cache before touching roles/permissions.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Build the cumulative grant sets.
        $cumulative = [];
        $rolling = [];

        foreach (self::GRANTS as $role => $additions) {
            $rolling = array_values(array_merge($rolling, $additions));
            $cumulative[$role] = $rolling;
        }

        $cumulative['admin'] = self::PERMISSIONS;

        // Standalone roles sit outside the ladder and inherit nothing.
        foreach (self::STANDALONE as $roleName => $permissions) {
            $cumulative[$roleName] = $permissions;
        }

        foreach ($cumulative as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
