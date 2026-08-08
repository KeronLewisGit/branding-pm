<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Session;

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
     * Session key for an administrator previewing somebody else's
     * walkthrough.
     */
    public const PREVIEW_KEY = 'walkthrough.preview_role';

    public static function previewRole(): ?string
    {
        $role = Session::get(self::PREVIEW_KEY);

        return is_string($role) && array_key_exists($role, self::STEP_COUNTS) ? $role : null;
    }

    public static function isPreviewing(): bool
    {
        return self::previewRole() !== null;
    }

    public static function startPreview(string $role): void
    {
        if (array_key_exists($role, self::STEP_COUNTS)) {
            Session::put(self::PREVIEW_KEY, $role);
        }
    }

    public static function stopPreview(): void
    {
        Session::forget(self::PREVIEW_KEY);
    }

    /**
     * Every role that has a walkthrough, in the order they are offered.
     *
     * @return list<string>
     */
    public static function availableRoles(): array
    {
        return array_reverse(self::ROLE_PRIORITY);
    }

    /**
     * The walkthrough actually on screen: the one being previewed if an
     * administrator asked for it, otherwise the viewer's own.
     */
    public static function displayRoleFor(User $user): ?string
    {
        return self::previewRole() ?? self::roleFor($user);
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
        if ($user === null) {
            return false;
        }

        // An administrator asked to see a particular role's walkthrough. That
        // is an explicit request, so it overrides everything below — including
        // their own "already seen".
        if (self::isPreviewing()) {
            return true;
        }

        return $user->walkthrough_seen_at === null
            && ! ViewAs::active()
            && self::roleFor($user) !== null;
    }
}
