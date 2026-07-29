<?php

declare(strict_types=1);

use App\Enums\Frequency;
use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Enums\Shift;
use App\Livewire\Runs\RunForm;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Holiday;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Milestone 8 — the generator, the missed-run job, and the submit gate
|--------------------------------------------------------------------------
| SPEC §Tests asks for coverage of run generation, completion, required-item
| enforcement, sign-off, rejection, role restrictions and the missed-run job.
| Sign-off and rejection live in SignOffTest; the rest is here.
*/

/** A Monday, so weekday-sensitive assertions are not calendar-dependent. */
function aMonday(): Carbon
{
    return Carbon::parse('2026-03-02', config('app.display_timezone'));
}

/**
 * @return array{0: ChecklistTemplate, 1: Machine, 2: Site}
 */
function dailyTemplate(array $attributes = []): array
{
    $site = Site::factory()->create(['working_days' => [1, 2, 3, 4, 5, 6]]);
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();

    $template = ChecklistTemplate::factory()->for($machine)->create(array_merge([
        'frequency' => Frequency::Daily,
        'per_shift' => true,
        'is_active' => true,
        'grace_period_hours' => 24,
    ], $attributes));

    ChecklistTemplateItem::factory()->count(2)->create([
        'checklist_template_id' => $template->id,
        'is_active' => true,
    ]);

    return [$template, $machine, $site];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('generates one run per shift and copies the template items', function (): void {
    [$template, $machine] = dailyTemplate();

    $this->artisan('checklists:generate', ['--date' => aMonday()->toDateString()])
        ->assertSuccessful();

    $runs = ChecklistRun::query()->where('machine_id', $machine->id)->get();

    expect($runs)->toHaveCount(2)
        ->and($runs->pluck('shift')->map->value->sort()->values()->all())
        ->toBe([Shift::Day->value, Shift::Night->value]);

    // Items are copied as snapshots, with the template version recorded.
    // (Loaded explicitly — preventLazyLoading() is on outside production.)
    $run = $runs->first()->load('items');

    expect($run->items)->toHaveCount(2)
        ->and($run->template_version)->toBe($template->version);
});

it('is idempotent — running the generator twice creates nothing new', function (): void {
    [, $machine] = dailyTemplate();

    $this->artisan('checklists:generate', ['--date' => aMonday()->toDateString()]);
    $this->artisan('checklists:generate', ['--date' => aMonday()->toDateString()]);

    expect(ChecklistRun::query()->where('machine_id', $machine->id)->count())->toBe(2);
});

it('skips Sundays, holidays and inactive templates', function (): void {
    [, $machine, $site] = dailyTemplate();

    // Sunday — outside working_days.
    $this->artisan('checklists:generate', ['--date' => aMonday()->copy()->subDay()->toDateString()]);
    expect(ChecklistRun::query()->where('machine_id', $machine->id)->count())->toBe(0);

    // A holiday on the Monday.
    Holiday::factory()->create([
        'site_id' => $site->id,
        'date' => aMonday()->toDateString(),
        'is_recurring' => false,
    ]);

    $this->artisan('checklists:generate', ['--date' => aMonday()->toDateString()]);
    expect(ChecklistRun::query()->where('machine_id', $machine->id)->count())->toBe(0);

    // Inactive template, an ordinary working day.
    [$inactive, $otherMachine] = dailyTemplate(['is_active' => false]);

    $this->artisan('checklists:generate', ['--date' => aMonday()->copy()->addDay()->toDateString()]);
    expect(ChecklistRun::query()->where('machine_id', $otherMachine->id)->count())->toBe(0)
        ->and($inactive->is_active)->toBeFalse();
});

it('marks a pending run missed only after its grace period', function (): void {
    [$template, $machine] = dailyTemplate(['grace_period_hours' => 24, 'per_shift' => false]);

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'scheduled_for' => aMonday()->toDateString(),
        'status' => RunStatus::Pending,
        'started_at' => null,
    ]);

    // End of the scheduled day plus 23 hours — still inside grace.
    $this->artisan('checklists:mark-missed', ['--now' => aMonday()->copy()->addDay()->setTime(22, 0)->toDateTimeString()]);
    expect($run->fresh()->status)->toBe(RunStatus::Pending);

    // Past the grace period.
    $this->artisan('checklists:mark-missed', ['--now' => aMonday()->copy()->addDays(2)->setTime(2, 0)->toDateTimeString()]);
    expect($run->fresh()->status)->toBe(RunStatus::Missed);
});

it('never marks a run missed once someone has started it', function (): void {
    [$template, $machine] = dailyTemplate(['per_shift' => false]);

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'scheduled_for' => aMonday()->toDateString(),
        'status' => RunStatus::InProgress,
        'started_at' => aMonday()->copy()->setTime(8, 0),
    ]);

    $this->artisan('checklists:mark-missed', ['--now' => aMonday()->copy()->addDays(5)->toDateTimeString()]);

    expect($run->fresh()->status)->toBe(RunStatus::InProgress);
});

it('blocks submission while a required item is unanswered, and names it', function (): void {
    [$template, $machine, $site] = dailyTemplate(['per_shift' => false]);

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::InProgress,
    ]);

    ChecklistRunItem::factory()->create([
        'checklist_run_id' => $run->id,
        'status' => RunItemStatus::Done,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $unanswered = ChecklistRunItem::factory()->create([
        'checklist_run_id' => $run->id,
        'status' => RunItemStatus::Pending,
        'is_required' => true,
        'sort_order' => 2,
        'description' => 'Clean the platen thoroughly',
    ]);

    $operator = User::factory()->operator()->withPin()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($machine->id);

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('attemptSubmit')
        ->assertSet('showMissing', true)
        // Named, not merely counted.
        ->assertSee($unanswered->description);

    expect($run->fresh()->status)->toBe(RunStatus::InProgress);

    // An optional item left pending does not block anything.
    $unanswered->update(['status' => RunItemStatus::Done, 'completed_at' => now()]);
    ChecklistRunItem::factory()->create([
        'checklist_run_id' => $run->id,
        'status' => RunItemStatus::Pending,
        'is_required' => false,
        'sort_order' => 3,
    ]);

    expect($run->fresh()->is_complete)->toBeTrue();
});

it('keeps each role inside its own screens', function (): void {
    $site = Site::factory()->create();

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);
    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);

    // Operator: their work only.
    $this->actingAs($operator)->get(route('runs.index'))->assertOk();
    $this->actingAs($operator)->get(route('runs.approvals'))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.machines'))->assertForbidden();
    $this->actingAs($operator)->get(route('admin.templates'))->assertForbidden();

    // Supervisor: adds sign-off and reports, but no master data.
    $this->actingAs($supervisor)->get(route('runs.approvals'))->assertOk();
    $this->actingAs($supervisor)->get(route('reports.index'))->assertOk();
    $this->actingAs($supervisor)->get(route('admin.machines'))->assertForbidden();

    // Maintenance manager: adds master data and the QR sheet.
    $this->actingAs($manager)->get(route('admin.machines'))->assertOk();
    $this->actingAs($manager)->get(route('admin.machines.qr'))->assertOk();
    $this->actingAs($manager)->get(route('admin.templates'))->assertOk();
});
