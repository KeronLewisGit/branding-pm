<?php

declare(strict_types=1);

namespace App\Livewire\Machines;

use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunPart;
use App\Models\ChecklistTemplate;
use App\Models\Issue;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Machine profile (route `machines.show`, URI `/machines/{machine}`) —
 * SPEC screen 5, and the last screen the specification asked for.
 *
 * Everything here was already reachable, but only by going to the runs list
 * and filtering, then the issues register and filtering again, then a report.
 * The question "what is going on with the MATAN?" had no single answer.
 *
 * Gated on `MachinePolicy::view`, so it follows the machine scope: a
 * supervisor or operator sees a machine at their own site, and nobody else's.
 * It is **not** admin-only — the people who need a machine's history are the
 * ones standing next to it.
 *
 * Unlike the kiosk's `/m/{code}`, an unknown code here is a plain 404. This
 * is an office screen reached from a list, not a peeling sticker.
 */
#[Layout('layouts::app')]
class MachineProfile extends Component
{
    use AuthorizesRequests;

    public Machine $machine;

    /** Days of history the run and parts panels cover. */
    #[Url(as: 'days')]
    public int $days = 30;

    /** Allowed windows — anything else is a hand-edited query string. */
    private const WINDOWS = [30, 90, 365];

    public function mount(Machine $machine): void
    {
        $this->authorize('view', $machine);

        $this->machine = $machine;

        if (! in_array($this->days, self::WINDOWS, true)) {
            $this->days = 30;
        }
    }

    public function setWindow(int $days): void
    {
        $this->days = in_array($days, self::WINDOWS, true) ? $days : 30;

        unset($this->runStats, $this->recentRuns, $this->partsUsed);
    }

    /**
     * @return list<int>
     */
    public function windows(): array
    {
        return self::WINDOWS;
    }

    // ── Panels ───────────────────────────────────────────────────────

    /**
     * Checklists defined for this machine. Inactive ones are shown too, and
     * marked — a template that quietly stopped generating runs is exactly
     * what somebody comes here to find.
     *
     * @return Collection<int, ChecklistTemplate>
     */
    #[Computed]
    public function templates(): Collection
    {
        return $this->machine->templates()
            ->withCount(['items' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Completed / missed / outstanding over the window.
     *
     * Counted with one grouped aggregate rather than a query per status.
     *
     * @return array{completed: int, missed: int, outstanding: int, total: int}
     */
    #[Computed]
    public function runStats(): array
    {
        $counts = ChecklistRun::query()
            ->where('machine_id', $this->machine->id)
            ->whereDate('scheduled_for', '>=', now()->subDays($this->days)->toDateString())
            ->toBase()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $completed = (int) ($counts[RunStatus::Approved->value] ?? 0)
            + (int) ($counts[RunStatus::Submitted->value] ?? 0);

        $missed = (int) ($counts[RunStatus::Missed->value] ?? 0);

        $outstanding = (int) ($counts[RunStatus::Pending->value] ?? 0)
            + (int) ($counts[RunStatus::InProgress->value] ?? 0)
            + (int) ($counts[RunStatus::Rejected->value] ?? 0);

        return [
            'completed' => $completed,
            'missed' => $missed,
            'outstanding' => $outstanding,
            'total' => $completed + $missed + $outstanding,
        ];
    }

    /**
     * @return Collection<int, ChecklistRun>
     */
    #[Computed]
    public function recentRuns(): Collection
    {
        return ChecklistRun::query()
            ->where('machine_id', $this->machine->id)
            ->whereDate('scheduled_for', '>=', now()->subDays($this->days)->toDateString())
            ->with(['template:id,name', 'operator:id,full_name', 'supervisor:id,full_name'])
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->limit(25)
            ->get();
    }

    /**
     * Faults on this machine, open first, then most recent.
     *
     * @return Collection<int, Issue>
     */
    #[Computed]
    public function issues(): Collection
    {
        return Issue::query()
            ->where('machine_id', $this->machine->id)
            ->with(['raisedBy:id,full_name', 'assignedTo:id,full_name'])
            ->orderByRaw("FIELD(status, 'open', 'acknowledged', 'in_progress', 'resolved', 'closed')")
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    /**
     * What this machine has consumed over the window.
     *
     * Grouped on the **snapshot** name and code, not the catalogue: a part
     * renamed last month still reports under the name it was consumed as,
     * matching the parts usage report.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function partsUsed(): Collection
    {
        return ChecklistRunPart::query()
            ->join('checklist_runs', 'checklist_runs.id', '=', 'checklist_run_parts.checklist_run_id')
            ->where('checklist_runs.machine_id', $this->machine->id)
            ->whereDate('checklist_runs.scheduled_for', '>=', now()->subDays($this->days)->toDateString())
            ->where('checklist_run_parts.qty_used', '>', 0)
            ->groupBy('checklist_run_parts.part_code_snapshot', 'checklist_run_parts.part_name_snapshot')
            ->orderByDesc(DB::raw('SUM(checklist_run_parts.qty_used)'))
            ->get([
                'checklist_run_parts.part_code_snapshot as part_code',
                'checklist_run_parts.part_name_snapshot as part_name',
                DB::raw('SUM(checklist_run_parts.qty_used) as qty'),
            ]);
    }

    /**
     * People assigned to this machine. Not a permission — see MachineScope.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function operators(): Collection
    {
        return $this->machine->operators()->orderBy('full_name')->get(['users.id', 'users.full_name', 'users.employee_number']);
    }

    /**
     * The machine's own QR, inline SVG so the page prints with no network.
     */
    public function qrSvg(): string
    {
        return (string) QrCode::format('svg')
            ->size(150)
            ->margin(0)
            ->errorCorrection('M')
            ->generate(route('kiosk.machine', ['code' => $this->machine->code]));
    }

    public function render(): View
    {
        return view('livewire.machines.machine-profile')
            ->title($this->machine->name);
    }
}
