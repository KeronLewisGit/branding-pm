<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use App\Models\User;
use App\Support\MachineScope;

class ChecklistRunPolicy
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
        return $user->can('run.view');
    }

    public function view(User $user, ChecklistRun $run): bool
    {
        return $user->can('run.view')
            && $this->machineInScope($user, $run);
    }

    public function start(User $user, ChecklistRun $run): bool
    {
        return $user->can('run.start')
            && $run->status->isEditable()
            && $this->machineInScope($user, $run);
    }

    public function update(User $user, ChecklistRun $run): bool
    {
        return $user->can('run.complete')
            && $run->status->isEditable()
            && $this->machineInScope($user, $run);
    }

    public function complete(User $user, ChecklistRun $run): bool
    {
        return $user->can('run.complete')
            && $run->status->isEditable()
            && $this->machineInScope($user, $run);
    }

    public function submit(User $user, ChecklistRun $run): bool
    {
        return $user->can('run.submit')
            && $run->status->isEditable()
            && $run->is_complete
            && $this->machineInScope($user, $run);
    }

    public function approve(User $user, ChecklistRun $run): bool
    {
        // No self sign-off: the supervisor approving a run must not be the
        // operator who completed it. This is deliberate — the paper process
        // requires two different signatures, and so does the digital one.
        return $user->can('run.approve')
            && $run->status === RunStatus::Submitted
            && $user->id !== $run->operator_id;
    }

    public function reject(User $user, ChecklistRun $run): bool
    {
        // Same two-person rule as approve() — see the comment there.
        return $user->can('run.reject')
            && $run->status === RunStatus::Submitted
            && $user->id !== $run->operator_id;
    }

    /**
     * Approved runs are immutable; corrections happen only through a logged
     * amendment by a holder of `run.amend`.
     */
    public function amend(User $user, ChecklistRun $run): bool
    {
        return $user->can('run.amend')
            && $run->status === RunStatus::Approved;
    }

    private function machineInScope(User $user, ChecklistRun $run): bool
    {
        $machine = $run->loadMissing('machine')->machine;

        return $machine !== null && MachineScope::allows($user, $machine);
    }
}
