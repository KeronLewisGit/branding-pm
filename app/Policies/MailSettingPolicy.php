<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MailSetting;
use App\Models\User;

/**
 * Who may change the mail relay.
 *
 * `setting.manage` already existed in the permission list — a leftover from
 * the settings screen this project removed — and is granted to administrators
 * only. Reusing it keeps the relay where the rest of system configuration
 * lives rather than inventing a parallel notion of "admin".
 */
class MailSettingPolicy
{
    public function manageSettings(User $user, ?MailSetting $setting = null): bool
    {
        return $user->can('setting.manage');
    }
}
