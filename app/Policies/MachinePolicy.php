<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Machine;
use App\Models\User;
use App\Support\MachineScope;

class MachinePolicy
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
        return $user->can('machine.view');
    }

    /**
     * Operators only see machines in their scope (BUILD-CONTRACT §5).
     */
    public function view(User $user, Machine $machine): bool
    {
        return $user->can('machine.view')
            && MachineScope::allows($user, $machine);
    }

    public function create(User $user): bool
    {
        return $user->can('machine.manage');
    }

    public function update(User $user, Machine $machine): bool
    {
        return $user->can('machine.manage');
    }

    public function delete(User $user, Machine $machine): bool
    {
        return $user->can('machine.manage');
    }

    public function restore(User $user, Machine $machine): bool
    {
        return $user->can('machine.manage');
    }
}
