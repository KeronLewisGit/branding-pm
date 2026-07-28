<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Admins can do everything.
     */
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
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
