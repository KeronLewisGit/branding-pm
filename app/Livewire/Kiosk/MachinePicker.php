<?php

declare(strict_types=1);

namespace App\Livewire\Kiosk;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Http\Middleware\EnsureKioskDevice;
use App\Models\ChecklistRun;
use App\Models\Issue;
use App\Models\Machine;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kiosk machine picker (route `kiosk.home`) — the shop-floor grid of large
 * machine tiles with a today-status dot and a breakdown flag.
 *
 * Access: the route sits behind the `kiosk` middleware (registered device
 * cookie), and before PIN entry there is NO authenticated user — the tablet
 * itself is the principal. What the grid shows follows the DEVICE for that
 * reason, and never the signed-in user; see machineQuery().
 */
#[Layout('layouts::kiosk')]
class MachinePicker extends Component
{
    #[Url(as: 'location')]
    public string $locationId = '';

    public function render(): View
    {
        $displayTz = (string) config('app.display_timezone', 'UTC');
        $today = now($displayTz)->toDateString();

        $machines = $this->machineQuery()
            ->where('machines.is_active', true)
            ->with('location:id,name')
            ->orderBy('machines.name')
            ->get();

        // Location tabs derive from the visible machines, so an operator's
        // scope never leaks other locations' names.
        $locations = $machines
            ->pluck('location')
            ->unique('id')
            ->sortBy('name')
            ->values();

        $visible = $this->locationId === ''
            ? $machines
            : $machines->where('location_id', (int) $this->locationId)->values();

        return view('livewire.kiosk.machine-picker', [
            'machines' => $visible,
            'locations' => $locations,
            'statuses' => $this->todayStatuses($machines->pluck('id'), $today),
            'breakdownIds' => $this->breakdownMachineIds($machines->pluck('id')),
        ]);
    }

    /**
     * Scoped to the DEVICE, not to whoever happens to be signed in on it.
     *
     * This screen is only ever reached through the `kiosk` middleware, so the
     * context is a tablet anybody on the floor may walk up to — not one
     * person's worklist. It used to apply `MachineScope::for(auth()->user())`
     * whenever a user was present, which was invisible while the only way
     * onto a kiosk was unauthenticated: no user, so every active machine.
     *
     * Once the office chrome grew a way into kiosk mode, people started
     * arriving here already signed in, and an operator with no default site
     * and no machine assignments — which is every account nobody has filled
     * in, because no screen sets `default_site_id` — was shown an empty
     * plant on a tablet bolted to a machine they could see in front of them.
     *
     * A device pinned to a location shows that site. An unpinned one shows
     * the plant, exactly as an unauthenticated kiosk always has.
     */
    private function machineQuery(): Builder
    {
        $siteId = EnsureKioskDevice::enrolledDevice(request())?->location?->site_id;

        if ($siteId === null) {
            return Machine::query();
        }

        return Machine::query()->whereHas(
            'location',
            fn (Builder $query) => $query->where('site_id', $siteId),
        );
    }

    /**
     * Today's status per machine, computed in ONE aggregate query — never a
     * query per tile.
     *
     * Status precedence: overdue (an open run from a previous day) beats
     * in_progress beats done (everything due today submitted/approved) beats
     * due (something today still pending). 'none' = nothing due, no dot.
     *
     * @param  Collection<int, int>  $machineIds
     * @return array<int, string> machine id => due|in_progress|done|overdue|none
     */
    private function todayStatuses(Collection $machineIds, string $today): array
    {
        if ($machineIds->isEmpty()) {
            return [];
        }

        $rows = ChecklistRun::query()
            ->whereIn('machine_id', $machineIds)
            ->where(function (Builder $query) use ($today) {
                $query->where('scheduled_for', $today)
                    ->orWhere(fn (Builder $q) => $q
                        ->where('scheduled_for', '<', $today)
                        ->whereIn('status', ['pending', 'in_progress', 'rejected']));
            })
            ->groupBy('machine_id')
            ->selectRaw(
                "machine_id,
                 SUM(CASE WHEN scheduled_for = ? THEN 1 ELSE 0 END) AS due_today,
                 SUM(CASE WHEN scheduled_for = ? AND status IN ('in_progress', 'rejected') THEN 1 ELSE 0 END) AS in_progress_today,
                 SUM(CASE WHEN scheduled_for = ? AND status IN ('submitted', 'approved') THEN 1 ELSE 0 END) AS done_today,
                 SUM(CASE WHEN scheduled_for < ? AND status IN ('pending', 'in_progress', 'rejected') THEN 1 ELSE 0 END) AS overdue_open",
                [$today, $today, $today, $today],
            )
            // Base rows, not hydrated models — these are aggregates, and the
            // model's enum casts must not run against them.
            ->toBase()
            ->get()
            ->keyBy('machine_id');

        $statuses = [];

        foreach ($machineIds as $id) {
            $row = $rows->get($id);

            $statuses[$id] = match (true) {
                $row === null,
                (int) ($row->due_today ?? 0) === 0 && (int) ($row->overdue_open ?? 0) === 0 => 'none',
                (int) $row->overdue_open > 0 => 'overdue',
                (int) $row->in_progress_today > 0 => 'in_progress',
                (int) $row->done_today >= (int) $row->due_today => 'done',
                default => 'due',
            };
        }

        return $statuses;
    }

    /**
     * Machines with an unresolved breakdown-severity issue — flagged on every
     * dashboard (SPEC §Issues). One query for the whole grid.
     *
     * @param  Collection<int, int>  $machineIds
     * @return list<int>
     */
    private function breakdownMachineIds(Collection $machineIds): array
    {
        if ($machineIds->isEmpty()) {
            return [];
        }

        return Issue::query()
            ->whereIn('machine_id', $machineIds)
            ->where('severity', IssueSeverity::Breakdown->value)
            ->whereNotIn('status', [IssueStatus::Resolved->value, IssueStatus::Closed->value])
            ->distinct()
            ->pluck('machine_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
