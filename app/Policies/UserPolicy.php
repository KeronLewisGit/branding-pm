<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admins can do everything — except delete, which falls through to the
     * method below.
     *
     * A blanket `true` here let an administrator delete their own account,
     * because `before()` cannot see the target model and so cannot apply the
     * self-check. With one administrator that locks everybody out of user
     * management permanently, and there is no second screen to undo it from.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return null;
        }

        // `isActingAdmin()`, not `hasRole('admin')`: while an administrator
        // is previewing another role this must NOT wave them through, or
        // the preview shows that role's menu over an admin's permissions.
        return $user->isActingAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('user.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('user.manage');
    }

    /**
     * `user.manage` holders cannot delete their own account.
     * (Admins technically bypass this via before() — acceptable, since
     * before() cannot see the target model.)
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can('user.manage')
            && $user->id !== $model->id;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('user.manage');
    }
}
