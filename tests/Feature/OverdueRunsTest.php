<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Http\Middleware\EnsureKioskDevice;
use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\KioskDevice;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Overdue work on the kiosk, and the late stamp on a rescued sheet
|--------------------------------------------------------------------------
| The machine tile went red for open work from before today and the screen
| behind it listed only today, so a sheet a supervisor had sent back for
| rework was unreachable from the shop floor. These cover the two halves of
| the fix: which sheets come back, and what the record says when one is
| finally signed.
*/

function overdueMachine(array $attributes = []): Machine
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();

    return Machine::factory()->for($location)->create($attributes);
}

function overdueKioskCookie(): array
{
    $device = KioskDevice::create([
        'name' => 'Overdue test tablet',
        'token' => Str::random(64),
        'is_active' => true,
    ]);

    return [EnsureKioskDevice::COOKIE => $device->token];
}

/**
 * A run on `$machine`, `$days` before today, in `$status`.
 */
function runDaysAgo(Machine $machine, int $days, RunStatus $status): ChecklistRun
{
    $template = ChecklistTemplate::factory()->for($machine)->create([
        'name' => 'Daily Maintenance '.Str::random(4),
        'is_active' => true,
    ]);

    return ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'scheduled_for' => now()->subDays($days)->toDateString(),
        'status' => $status,
    ]);
}

/*
|--------------------------------------------------------------------------
| Which sheets come back
|--------------------------------------------------------------------------
*/

it('brings back rejected and in-progress sheets from before today', function (): void {
    $machine = overdueMachine(['code' => 'matan']);

    $rejected = runDaysAgo($machine, 26, RunStatus::Rejected);
    $inProgress = runDaysAgo($machine, 13, RunStatus::InProgress);

    $reachable = ChecklistRun::query()
        ->forMachine($machine)
        ->overdueOpenBefore(now()->toDateString())
        ->pluck('id');

    expect($reachable)->toContain($rejected->id)
        ->and($reachable)->toContain($inProgress->id);
});

it('leaves a missed sheet missed', function (): void {
    // A gap in the record IS the record. Re-opening a missed run weeks later
    // would rewrite a compliance figure that has already been reported.
    $machine = overdueMachine();
    $missed = runDaysAgo($machine, 9, RunStatus::Missed);

    expect(ChecklistRun::query()->forMachine($machine)->overdueOpenBefore(now()->toDateString())->pluck('id'))
        ->not->toContain($missed->id);
});

it('ignores an old pending sheet, which belongs to mark-missed', function (): void {
    // `checklists:mark-missed` flips untouched pending runs to missed once
    // the grace period expires. An old pending run is one the hourly command
    // has not reached yet, not work anybody is waiting on.
    $machine = overdueMachine();
    $pending = runDaysAgo($machine, 5, RunStatus::Pending);

    expect(ChecklistRun::query()->forMachine($machine)->overdueOpenBefore(now()->toDateString())->pluck('id'))
        ->not->toContain($pending->id);
});

it('never treats today as overdue', function (): void {
    $machine = overdueMachine();
    $today = runDaysAgo($machine, 0, RunStatus::InProgress);

    expect(ChecklistRun::query()->forMachine($machine)->overdueOpenBefore(now()->toDateString())->pluck('id'))
        ->not->toContain($today->id);
});

it('shows overdue work on the machine screen the red tile points at', function (): void {
    $machine = overdueMachine(['code' => 'matan', 'name' => 'MATAN']);
    runDaysAgo($machine, 26, RunStatus::Rejected);

    $this->withCookies(overdueKioskCookie())
        ->get('/m/'.$machine->code)
        ->assertOk()
        ->assertSee(__('app.kiosk.overdue_heading'))
        // Warned before they open it, not after they sign it.
        ->assertSee(__('app.kiosk.overdue_note'));
});

it('still says nothing is due today when only overdue work remains', function (): void {
    // Otherwise an operator seeing only the red band cannot tell whether
    // today's sheets are missing or simply not due.
    $machine = overdueMachine(['code' => 'matan']);
    runDaysAgo($machine, 26, RunStatus::Rejected);

    $this->withCookies(overdueKioskCookie())
        ->get('/m/'.$machine->code)
        ->assertOk()
        ->assertSee(__('app.kiosk.nothing_due'));
});

/*
|--------------------------------------------------------------------------
| What the record says afterwards
|--------------------------------------------------------------------------
*/

it('stamps a sheet signed after its due day as completed late', function (): void {
    $run = ChecklistRun::factory()->create([
        'scheduled_for' => '2026-07-15',
        'submitted_at' => '2026-08-10 14:00:00',
        'status' => RunStatus::Submitted,
    ]);

    expect($run->completedLateByDays())->toBe(26);
});

it('does not stamp a sheet signed on its own day', function (): void {
    $run = ChecklistRun::factory()->create([
        'scheduled_for' => '2026-07-15',
        'submitted_at' => '2026-07-15 14:00:00',
        'status' => RunStatus::Submitted,
    ]);

    expect($run->completedLateByDays())->toBeNull();
});

it('reads the signing time in plant time, not UTC', function (): void {
    // The plant is UTC-4, so 02:00 UTC on the 16th is 22:00 on the 15th —
    // still the due day. Comparing raw UTC here would stamp every late-shift
    // sheet as a day late, which is the whole night shift, every night.
    config()->set('app.display_timezone', 'America/Port_of_Spain');

    $run = ChecklistRun::factory()->create([
        'scheduled_for' => '2026-07-15',
        'submitted_at' => '2026-07-16 02:00:00',
        'status' => RunStatus::Submitted,
    ]);

    expect($run->completedLateByDays())->toBeNull();
});

it('has nothing to stamp on an unsigned sheet', function (): void {
    $run = ChecklistRun::factory()->create([
        'scheduled_for' => '2026-07-15',
        'submitted_at' => null,
        'status' => RunStatus::InProgress,
    ]);

    expect($run->completedLateByDays())->toBeNull();
});

it('derives the stamp rather than storing it, so there is nothing to edit', function (): void {
    // The guarantee is structural: an operator cannot edit the stamp away
    // because there is no column holding it. Both inputs are server-set.
    $run = ChecklistRun::factory()->create([
        'scheduled_for' => '2026-07-15',
        'submitted_at' => '2026-07-20 14:00:00',
        'status' => RunStatus::Submitted,
    ]);

    expect(array_keys($run->getAttributes()))
        ->not->toContain('completed_late_at')
        ->not->toContain('late_days')
        ->and($run->completedLateByDays())->toBe(5);
});
