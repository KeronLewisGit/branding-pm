<?php

declare(strict_types=1);

namespace App\Livewire\Kiosk;

use App\Enums\RunItemStatus;
use App\Models\ChecklistRun;
use App\Models\Machine;
use App\Support\MachineScope;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Kiosk machine page (route `kiosk.machine`, URI `/m/{code}`) — the QR
 * deep-link target. Shows the paper form's header block and today's due
 * checklist(s) as large tappable cards.
 *
 * The {code} segment is accepted as a STRING, not an implicit model binding,
 * on purpose: an implicit binding would turn an unknown or mistyped QR slug
 * into a bare 404. A sticker that peels, smudges or outlives its machine must
 * produce a clear on-screen message on the kiosk, so the lookup is done here
 * and the failure modes (unknown / inactive / out of scope) each render
 * friendly kiosk copy instead.
 *
 * The route parameter must stay named `code`. Livewire's ImplicitRouteBinding
 * intersects route parameters with public property NAMES, so a parameter
 * named `machine` would bind to $machine below (typed ?Machine) and resolve
 * the model before mount() ran, defeating all of the above. `code` matches
 * the public string $code and is passed through untouched.
 */
#[Layout('layouts::kiosk')]
class MachineRuns extends Component
{
    public ?Machine $machine = null;

    /** The raw scanned code, echoed back on the "unknown machine" screen. */
    public string $code = '';

    /** ok | unknown | inactive | forbidden */
    public string $state = 'ok';

    public function mount(string $code): void
    {
        $this->code = $code;

        $found = Machine::query()
            ->where('code', $code)
            ->with(['location:id,name,floor,site_id', 'location.site:id,name'])
            ->first();

        if ($found === null) {
            $this->state = 'unknown';

            return;
        }

        if (! $found->is_active) {
            $this->machine = $found;
            $this->state = 'inactive';

            return;
        }

        // Authorisation in mount, not only in Blade: a pre-PIN kiosk session
        // has no user (the registered device is the principal — the kiosk
        // middleware already vetted it); a signed-in user must additionally
        // pass MachineScope.
        $user = Auth::user();

        if ($user !== null && ! MachineScope::allows($user, $found)) {
            $this->state = 'forbidden';

            return;
        }

        $this->machine = $found;
    }

    public function render(): View
    {
        $displayTz = (string) config('app.display_timezone', 'UTC');
        $today = now($displayTz)->toDateString();

        $runsByShift = collect();
        $overdueRuns = collect();
        $lastCompleted = null;

        if ($this->state === 'ok' && $this->machine !== null) {
            /*
             * Open work from before today, oldest first.
             *
             * The machine tile on the picker already goes red for these, and
             * before this it led nowhere: the tile said "Overdue", the screen
             * behind it listed only today, and a sheet a supervisor had sent
             * back for rework was unreachable from the shop floor entirely.
             * A badge that points at nothing is worse than no badge.
             *
             * Oldest first because the oldest is the one most at risk of
             * never being done at all.
             */
            $overdueRuns = ChecklistRun::query()
                ->forMachine($this->machine)
                ->overdueOpenBefore($today)
                ->with('template:id,name,work_category,work_description')
                ->withCount([
                    'items as items_total_count',
                    'items as items_done_count' => fn ($q) => $q->where('status', '!=', RunItemStatus::Pending->value),
                ])
                ->orderBy('scheduled_for')
                ->orderBy('shift')
                ->get();

            $runs = ChecklistRun::query()
                ->forMachine($this->machine)
                ->dueOn($today)
                ->with('template:id,name,work_category,work_description')
                ->withCount([
                    'items as items_total_count',
                    'items as items_done_count' => fn ($q) => $q->where('status', '!=', RunItemStatus::Pending->value),
                ])
                // MySQL sorts enums by definition order: day, night, all.
                ->orderBy('shift')
                ->orderBy('id')
                ->get();

            // Grouped by shift so day and night render as visually separate
            // sections — a night operator must never open the day sheet by
            // accident.
            $runsByShift = $runs->groupBy(fn (ChecklistRun $run): string => $run->shift->value);

            if ($runs->isEmpty() && $overdueRuns->isEmpty()) {
                // Nothing due → show when this machine was last done, not an
                // empty screen.
                $lastCompleted = ChecklistRun::query()
                    ->forMachine($this->machine)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->orderByDesc('scheduled_for')
                    ->orderByDesc('submitted_at')
                    ->first();
            }
        }

        return view('livewire.kiosk.machine-runs', [
            'runsByShift' => $runsByShift,
            'overdueRuns' => $overdueRuns,
            'hasBothShifts' => $runsByShift->has('day') && $runsByShift->has('night'),
            'lastCompleted' => $lastCompleted,
            'displayTz' => $displayTz,
        ]);
    }
}
