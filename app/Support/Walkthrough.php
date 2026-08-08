<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * The first-run walkthrough: a few cards explaining the app to whoever just
 * signed in, in the terms of the job they actually do.
 *
 * Roles are cumulative, so somebody senior technically holds an operator's
 * permissions too. Showing a maintenance manager the operator introduction
 * would be wrong, so the role is resolved **most senior first** — the one
 * that describes what they are here to do.
 *
 * The copy lives in the language file like every other string. Each role has
 * its own set because a shared, generic tour is the kind nobody reads: an
 * operator needs to know that tapping a row saves it, and a QA officer needs
 * to know they verify after the supervisor, and neither cares about the
 * other.
 */
final class Walkthrough
{
    /**
     * Most senior first. `admin` leads because an administrator who is also
     * given the operator role should still be introduced as an administrator.
     *
     * @var list<string>
     */
    private const ROLE_PRIORITY = [
        'admin',
        'quality_assurance',
        'maintenance_manager',
        'supervisor',
        'operator',
    ];

    /** How many cards each role's walkthrough has. */
    private const STEP_COUNTS = [
        'admin' => 4,
        'quality_assurance' => 4,
        'maintenance_manager' => 4,
        'supervisor' => 4,
        'operator' => 5,
    ];

    /**
     * Which walkthrough this user gets, or null if no role matches.
     */
    public static function roleFor(User $user): ?string
    {
        foreach (self::ROLE_PRIORITY as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The cards, as `[title, body]` pairs.
     *
     * @return list<array{title: string, body: string}>
     */
    public static function stepsFor(string $role): array
    {
        $count = self::STEP_COUNTS[$role] ?? 0;
        $steps = [];

        for ($i = 1; $i <= $count; $i++) {
            $steps[] = [
                'title' => __("app.walkthrough.{$role}.{$i}.title"),
                'body' => __("app.walkthrough.{$role}.{$i}.body"),
            ];
        }

        return $steps;
    }

    /**
     * Should this user be shown it right now?
     *
     * Not while an administrator is previewing another role: they have been
     * introduced already, and a tour appearing over a preview would be noise
     * — and worse, dismissing it would mark the *administrator* as onboarded
     * for a role they were only looking at.
     */
    public static function shouldShow(?User $user): bool
    {
        return $user !== null
            && $user->walkthrough_seen_at === null
            && ! ViewAs::active()
            && self::roleFor($user) !== null;
    }
}
