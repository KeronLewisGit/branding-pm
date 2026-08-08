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
        // `isActingAdmin()`, not `hasRole('admin')`: while an administrator
        // is previewing another role this must NOT wave them through, or
        // the preview shows that role's menu over an admin's permissions.
        return $user->isActingAdmin() ? true : null;
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
