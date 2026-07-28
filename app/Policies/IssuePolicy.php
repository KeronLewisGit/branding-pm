<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use App\Support\MachineScope;

class IssuePolicy
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
        return $user->can('issue.view');
    }

    /**
     * Visibility follows the machine scope, like runs.
     */
    public function view(User $user, Issue $issue): bool
    {
        if (! $user->can('issue.view')) {
            return false;
        }

        $machine = $issue->loadMissing('machine')->machine;

        return $machine !== null && MachineScope::allows($user, $machine);
    }

    public function create(User $user): bool
    {
        return $user->can('issue.create');
    }

    public function assign(User $user, Issue $issue): bool
    {
        return $user->can('issue.assign');
    }

    public function resolve(User $user, Issue $issue): bool
    {
        return $user->can('issue.resolve');
    }

    public function update(User $user, Issue $issue): bool
    {
        return $user->can('issue.assign');
    }
}
