<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Machine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single implementation of operator machine scoping (BUILD-CONTRACT §5).
 *
 * - Users with `machine.manage` see every machine.
 * - Otherwise, the machines assigned in `user_machine`.
 * - If the user has NO assignments, every machine at their `default_site_id`
 *   (a user with neither assignments nor a default site sees nothing).
 */
class MachineScope
{
    public static function for(User $user): Builder
    {
        if ($user->can('machine.manage')) {
            return Machine::query();
        }

        $assignedIds = $user->machines()->pluck('machines.id');

        if ($assignedIds->isNotEmpty()) {
            return Machine::query()->whereIn('machines.id', $assignedIds);
        }

        return Machine::query()->whereHas(
            'location',
            fn (Builder $query) => $query->where('site_id', $user->default_site_id),
        );
    }

    public static function allows(User $user, Machine $machine): bool
    {
        return static::for($user)->whereKey($machine->getKey())->exists();
    }
}
