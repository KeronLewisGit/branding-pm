<?php

declare(strict_types=1);

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistTemplate;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use App\Support\Reporting\ChecksCompletedReport;
use App\Support\Reporting\Compliance;
use App\Support\Reporting\ComplianceReport;
use App\Support\Reporting\ReportFilters;
use App\Support\RunVerification;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Milestone 7 — dashboard, reports and exports
|--------------------------------------------------------------------------
| The things that must not drift: the compliance definition, the machine
| scope on an export, and the permission split between reading numbers on
| screen (report.view) and taking them out of the building (export.data).
*/

/**
 * @return array{0: Machine, 1: Site}
 */
function machineWithRuns(array $statuses): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();
    $template = ChecklistTemplate::factory()->for($machine)->create();

    foreach ($statuses as $index => $status) {
        ChecklistRun::factory()->create([
            'checklist_template_id' => $template->id,
            'machine_id' => $machine->id,
            'status' => $status,
            'scheduled_for' => now()->subDays($index + 1)->toDateString(),
        ]);
    }

    return [$machine, $site];
}

function filtersFor(User $user): ReportFilters
{
    return ReportFilters::make(
        user: $user,
        from: now()->subDays(29)->toDateString(),
        to: now()->toDateString(),
    );
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('computes compliance as completed over everything owed', function (): void {
    // 3 completed, 1 missed, 1 still open = 3/5 = 60%
    [, $site] = machineWithRuns([
        RunStatus::Approved,
        RunStatus::Approved,
        RunStatus::Submitted,
        RunStatus::Missed,
        RunStatus::Pending,
    ]);

    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);

    $summary = Compliance::summarise(Compliance::query(filtersFor($manager)));

    expect($summary['completed'])->toBe(3)
        ->and($summary['missed'])->toBe(1)
        ->and($summary['outstanding'])->toBe(1)
        ->and($summary['due'])->toBe(5)
        ->and($summary['percentage'])->toBe(60.0);
});

it('reports no percentage at all when nothing was due', function (): void {
    $site = Site::factory()->create();
    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);

    $summary = Compliance::summarise(Compliance::query(filtersFor($manager)));

    // Not 0%, not 100% — both would be a claim the data does not support.
    expect($summary['percentage'])->toBeNull()
        ->and(Compliance::format($summary['percentage']))->toBe('—');
});

it('ignores runs scheduled beyond the window', function (): void {
    [$machine, $site] = machineWithRuns([RunStatus::Approved]);

    ChecklistRun::factory()->create([
        'checklist_template_id' => ChecklistTemplate::factory()->for($machine)->create()->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::Pending,
        'scheduled_for' => now()->addWeek()->toDateString(),
    ]);

    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);
    $summary = Compliance::summarise(Compliance::query(filtersFor($manager)));

    // Work that is not yet due is neither compliant nor non-compliant.
    expect($summary['due'])->toBe(1)
        ->and($summary['percentage'])->toBe(100.0);
});

it('keeps a report inside the requester machine scope', function (): void {
    [$mine, $site] = machineWithRuns([RunStatus::Approved, RunStatus::Missed]);
    [$theirs] = machineWithRuns([RunStatus::Approved]);

    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);
    $supervisor->machines()->attach($mine->id);

    $rows = (new ComplianceReport)->rows(filtersFor($supervisor));

    expect($rows->pluck('machine')->all())->toBe([$mine->name])
        ->and($rows->pluck('machine'))->not->toContain($theirs->name);
});

it('lets a manager export but not a supervisor', function (): void {
    [, $site] = machineWithRuns([RunStatus::Approved]);

    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);
    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);

    // report.view shows the numbers on screen…
    $this->actingAs($supervisor)->get(route('reports.index'))->assertOk();
    // …export.data is what takes them out of the building.
    $this->actingAs($supervisor)->get(route('reports.csv', ['report' => 'compliance']))->assertForbidden();

    $response = $this->actingAs($manager)->get(route('reports.csv', ['report' => 'compliance']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('sends an operator who cannot read reports to their work instead of a 403', function (): void {
    $site = Site::factory()->create();
    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);

    $this->actingAs($operator)->get(route('dashboard'))->assertRedirect(route('runs.index'));
});

it('serves the run sheet PDF to someone who may see the run, and nobody else', function (): void {
    [$machine, $site] = machineWithRuns([RunStatus::Approved]);
    $run = ChecklistRun::query()->where('machine_id', $machine->id)->firstOrFail();

    $insider = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $insider->machines()->attach($machine->id);

    $outsider = User::factory()->operator()->create([
        'default_site_id' => Site::factory()->create()->id,
    ]);

    $response = $this->actingAs($insider)->get(route('runs.pdf', $run));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $this->actingAs($outsider)->get(route('runs.pdf', $run))->assertForbidden();
});

it('changes the verification hash when the record changes', function (): void {
    [$machine] = machineWithRuns([RunStatus::Approved]);
    $run = ChecklistRun::query()->where('machine_id', $machine->id)->firstOrFail();

    $item = ChecklistRunItem::factory()->create([
        'checklist_run_id' => $run->id,
        'status' => RunItemStatus::Done,
    ]);

    $before = RunVerification::hash($run->fresh());

    $item->update(['status' => RunItemStatus::Failed, 'fail_reason' => 'Altered after the fact.']);

    $after = RunVerification::hash($run->fresh());

    expect($after)->not->toBe($before)
        // A sheet printed before the change no longer verifies.
        ->and(RunVerification::matches($run->fresh(), $before))->toBeFalse()
        ->and(RunVerification::matches($run->fresh(), $after))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Checks completed — who did it, and when
|--------------------------------------------------------------------------
*/

it('names the operator and both dates for every completed check', function (): void {
    [$machine, $site] = machineWithRuns([RunStatus::Approved]);

    $operator = User::factory()->operator()->create([
        'full_name' => 'Darnell Joseph',
        'employee_number' => 'OP-1001',
        'default_site_id' => $site->id,
    ]);

    $run = ChecklistRun::query()->where('machine_id', $machine->id)->firstOrFail();
    $run->update([
        'operator_id' => $operator->id,
        'submitted_at' => now(),
    ]);

    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);
    $rows = (new ChecksCompletedReport)->rows(filtersFor($manager));

    expect($rows)->toHaveCount(1);

    $row = $rows->first();

    expect($row['operator'])->toBe('Darnell Joseph')
        ->and($row['employee_number'])->toBe('OP-1001')
        ->and($row['machine'])->toBe($machine->name)
        // The day it was due and the moment it was signed are different
        // facts; conflating them would hide lateness.
        ->and($row['scheduled_for'])->toBe($run->scheduled_for->format('d M Y'))
        ->and($row['completed_at'])->not->toBe('—');
});

it('dates a check by the day it was scheduled, not a timezone-shifted one', function (): void {
    [$machine, $site] = machineWithRuns([RunStatus::Approved]);

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);

    $run = ChecklistRun::query()->where('machine_id', $machine->id)->firstOrFail();
    $run->update(['operator_id' => $operator->id, 'submitted_at' => now()]);

    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);
    $row = (new ChecksCompletedReport)->rows(filtersFor($manager))->first();

    // `scheduled_for` is a calendar date cast to midnight UTC. Converting it
    // into America/Port_of_Spain (UTC-4) renders every run a day early.
    expect($row['scheduled_for'])->toBe($run->scheduled_for->format('d M Y'));
});

it('leaves unfinished work out of the completed-checks report', function (): void {
    [$machine, $site] = machineWithRuns([RunStatus::InProgress]);

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);

    // Claimed, so it has an operator — but never signed, so it is not a
    // completed check. Outstanding work is the missed-checks report's job.
    ChecklistRun::query()->where('machine_id', $machine->id)->update([
        'operator_id' => $operator->id,
        'submitted_at' => null,
    ]);

    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);

    expect((new ChecksCompletedReport)->rows(filtersFor($manager)))->toBeEmpty();
});
