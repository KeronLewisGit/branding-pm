<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ChecklistTemplate;
use App\Models\User;

class ChecklistTemplatePolicy
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
        return $user->can('template.view');
    }

    public function view(User $user, ChecklistTemplate $template): bool
    {
        return $user->can('template.view');
    }

    public function create(User $user): bool
    {
        return $user->can('template.manage');
    }

    public function update(User $user, ChecklistTemplate $template): bool
    {
        return $user->can('template.manage');
    }

    public function delete(User $user, ChecklistTemplate $template): bool
    {
        return $user->can('template.manage');
    }

    public function restore(User $user, ChecklistTemplate $template): bool
    {
        return $user->can('template.manage');
    }
}
