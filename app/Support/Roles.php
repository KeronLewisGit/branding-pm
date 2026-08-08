<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The system's roles, named once.
 *
 * The five roles from BUILD-CONTRACT §5 had been written out by hand in four
 * separate places — the user administration screen, the "view as" picker and
 * two constants in `Walkthrough`. Adding Quality Assurance meant editing all
 * four, and one was missed: the role never appeared in the administration
 * screen's dropdown, and because that screen posts whatever the dropdown
 * holds, opening a QA officer's record and saving it silently demoted them to
 * operator.
 *
 * That is the failure mode a hand-copied list produces. There is now one list,
 * and the places that need a different order or a subset derive it from here.
 *
 * The order is least privileged first. It is not merely presentational:
 * `mostSeniorFirst()` is what decides which walkthrough somebody with two
 * roles is shown, so a role inserted in the wrong place changes behaviour.
 *
 * The role names themselves are the permission-system's primary keys — they
 * are seeded by `RolesAndPermissionsSeeder`, which remains the authority on
 * what each role may *do*. This class is the authority on which roles exist.
 */
final class Roles
{
    public const OPERATOR = 'operator';

    public const SUPERVISOR = 'supervisor';

    public const MAINTENANCE_MANAGER = 'maintenance_manager';

    public const QUALITY_ASSURANCE = 'quality_assurance';

    public const ADMIN = 'admin';

    /**
     * Every role, least privileged last-but-one; `admin` last of all.
     *
     * @var list<string>
     */
    public const ALL = [
        self::OPERATOR,
        self::SUPERVISOR,
        self::MAINTENANCE_MANAGER,
        self::QUALITY_ASSURANCE,
        self::ADMIN,
    ];

    /**
     * Most senior first — for "which of this person's roles counts?".
     *
     * @return list<string>
     */
    public static function mostSeniorFirst(): array
    {
        return array_reverse(self::ALL);
    }

    /**
     * The roles an administrator can step into with "view as".
     *
     * Everything but `admin`: previewing the role you already hold shows you
     * the screen you are already looking at.
     *
     * @return list<string>
     */
    public static function previewable(): array
    {
        return array_values(array_filter(self::ALL, fn (string $role): bool => $role !== self::ADMIN));
    }

    public static function exists(string $role): bool
    {
        return in_array($role, self::ALL, true);
    }
}
