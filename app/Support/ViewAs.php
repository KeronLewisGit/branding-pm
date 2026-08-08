<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Session;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * "View as" — let an administrator see the application through another role's
 * permissions, without becoming another person.
 *
 * The point is answering "what does a supervisor actually see here?" without
 * keeping four test logins, and without the usual impersonation machinery.
 *
 * The safety property, and the reason this is worth having at all:
 *
 *   **It can only ever take permissions away.**
 *
 * The effective set is the administrator's own permissions intersected with
 * the viewed role's — and since an administrator holds everything, that is
 * exactly the viewed role's set. There is no path here that grants anything
 * to anybody. Starting and stopping both require the caller's *real* admin
 * role, checked before this class is consulted.
 *
 * It is a **preview, not a sandbox**: the administrator is still themselves,
 * every action is still logged against their own account, and anything they
 * do while previewing is genuinely done. It hides buttons; it does not
 * pretend to be somebody else.
 */
final class ViewAs
{
    public const SESSION_KEY = 'view_as.role';

    /**
     * Roles an administrator may preview. `admin` is absent on purpose —
     * that is what stopping does.
     *
     * @return list<string>
     */
    public static function selectableRoles(): array
    {
        return ['operator', 'supervisor', 'maintenance_manager', 'quality_assurance'];
    }

    public static function active(): bool
    {
        return self::role() !== null;
    }

    public static function role(): ?string
    {
        $role = Session::get(self::SESSION_KEY);

        // A role deleted while somebody was previewing it would otherwise
        // strand them with no permissions and no obvious way back.
        return is_string($role) && in_array($role, self::selectableRoles(), true)
            ? $role
            : null;
    }

    public static function start(string $role): void
    {
        if (in_array($role, self::selectableRoles(), true)) {
            Session::put(self::SESSION_KEY, $role);
        }
    }

    public static function stop(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Does the previewed role hold this permission?
     *
     * Unknown ability names return true — they are policy abilities like
     * `view` or `update`, not permission names, and those are decided by the
     * policies (which themselves consult `can()` and so land back here on a
     * real permission name).
     */
    public static function permits(string $ability): bool
    {
        $role = self::role();

        if ($role === null) {
            return true;
        }

        return in_array($ability, self::permissionsFor($role), true)
            || ! in_array($ability, self::allPermissionNames(), true);
    }

    /**
     * @return list<string>
     */
    private static function permissionsFor(string $role): array
    {
        /** @var array<string, list<string>> $cache */
        static $cache = [];

        if (! array_key_exists($role, $cache)) {
            $cache[$role] = Role::query()
                ->where('name', $role)
                ->with('permissions:id,name')
                ->first()
                ?->permissions
                ->pluck('name')
                ->all() ?? [];
        }

        return $cache[$role];
    }

    /**
     * @return list<string>
     */
    private static function allPermissionNames(): array
    {
        static $all = null;

        if ($all === null) {
            $all = Permission::query()->pluck('name')->all();
        }

        return $all;
    }
}
