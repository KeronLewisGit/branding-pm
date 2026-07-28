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
        'issue.view',
        'issue.create',
        'issue.assign',
        'issue.resolve',
        'machine.view',
        'machine.manage',
        'template.view',
        'template.manage',
        'part.manage',
        'schedule.manage',
        'holiday.manage',
        'report.view',
        'export.data',
        'user.manage',
        'role.manage',
        'setting.manage',
        'kiosk.manage',
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
        ],
        'maintenance_manager' => [
            'machine.manage',
            'template.manage',
            'part.manage',
            'schedule.manage',
            'holiday.manage',
            'export.data',
            'run.amend',
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

        foreach ($cumulative as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
