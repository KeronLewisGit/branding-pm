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
 * - Everyone else sees every machine at their **site**.
 *
 * `user_machine` assignment deliberately does **not** narrow this. It marks
 * which machines are somebody's usual work — the kiosk surfaces those first —
 * and nothing more.
 *
 * That is a change from the original behaviour, where an assignment was a
 * fence: assigned users saw only their machines, and unassigned users saw
 * their whole site. The result was backwards. Assigning somebody to the two
 * machines they normally run *removed* their ability to cover a third when a
 * shift was short, and the fix was for an administrator to edit a pivot table
 * that had no screen. Nobody was going to do that at 6am, so in practice the
 * assignment table stayed empty and the fence never existed.
 *
 * The site remains the boundary. An operator cannot see another site's work.
 *
 * @see Machine::operators()
 */
class MachineScope
{
    public static function for(User $user): Builder
    {
        // `machine.view_all` is the plant-wide grant. A maintenance manager
        // gets it through `machine.manage`; a Quality Assurance officer holds
        // it on its own, because QA restricted to a single site could not do
        // the job it exists for.
        if ($user->can('machine.manage') || $user->can('machine.view_all')) {
            return Machine::query();
        }

        $siteIds = static::siteIdsFor($user);

        if ($siteIds === []) {
            // Neither a default site nor an assignment to infer one from —
            // there is nothing this user can be shown.
            return Machine::query()->whereRaw('1 = 0');
        }

        return Machine::query()->whereHas(
            'location',
            fn (Builder $query) => $query->whereIn('site_id', $siteIds),
        );
    }

    /**
     * The sites a user belongs to: their default site, plus the sites of any
     * machines they are assigned to.
     *
     * The second half matters because `default_site_id` is nullable and no
     * screen sets it. A user assigned to machines but with no default site
     * would otherwise see nothing at all — the exact operator most likely to
     * have been set up in a hurry.
     *
     * @return list<int>
     */
    private static function siteIdsFor(User $user): array
    {
        $siteIds = $user->default_site_id !== null ? [$user->default_site_id] : [];

        $assignedSiteIds = Machine::query()
            ->whereIn('machines.id', $user->machines()->select('machines.id'))
            ->join('locations', 'locations.id', '=', 'machines.location_id')
            ->distinct()
            ->pluck('locations.site_id')
            ->all();

        return array_values(array_unique([...$siteIds, ...$assignedSiteIds]));
    }

    /**
     * The **issue** scope, which is narrower than the run scope on purpose.
     *
     * Runs are site-wide because anybody may have to cover any machine at
     * short notice, and a checklist nobody can open is a checklist that does
     * not get done. The issues register is the opposite problem: it is a
     * standing worklist, and a plant-wide one buries the three faults on the
     * machines somebody actually runs. So an assignment narrows it.
     *
     * A user with **no** assignments still sees their whole site. Most users
     * have none — `user_machine` was unreachable from the UI until recently —
     * and an empty register looks broken rather than tidy.
     *
     * Reporting a fault is deliberately NOT narrowed by this; see
     * `IssueRegister::creatableMachines()`.
     */
    public static function forIssues(User $user): Builder
    {
        if ($user->can('machine.manage') || $user->can('machine.view_all')) {
            return Machine::query();
        }

        $assignedIds = static::assignedIds($user);

        if ($assignedIds !== []) {
            return Machine::query()->whereIn('machines.id', $assignedIds);
        }

        return static::for($user);
    }

    public static function allowsIssue(User $user, Machine $machine): bool
    {
        return static::forIssues($user)->whereKey($machine->getKey())->exists();
    }

    /**
     * Machine ids this user is explicitly assigned to — "mine", for ordering
     * and highlighting. Never a permission check.
     *
     * @return list<int>
     */
    public static function assignedIds(User $user): array
    {
        return $user->machines()->pluck('machines.id')->all();
    }

    public static function allows(User $user, Machine $machine): bool
    {
        return static::for($user)->whereKey($machine->getKey())->exists();
    }
}
