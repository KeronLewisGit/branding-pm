<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Livewire\Admin\MachineManager;
use App\Livewire\Issues\IssueRegister;
use App\Livewire\Runs\RunForm;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use App\Support\MachineScope;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Who does a check, and how they come to be doing it
|--------------------------------------------------------------------------
| Two routes, and they are not alternatives — both are always available:
|
|   - self-assignment: an operator walks up, PINs in, and the first tap on
|     the sheet makes it theirs
|   - machine assignment: `user_machine` marks a machine as somebody's usual
|     work, which surfaces it to them but never gates it
*/

/**
 * @return array{0: Machine, 1: Site}
 */
function machineAtNewSite(string $name = 'MATAN'): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();

    return [Machine::factory()->for($location)->create(['name' => $name]), $site];
}

function runFor(Machine $machine, array $attributes = []): ChecklistRun
{
    $template = ChecklistTemplate::factory()->for($machine)->create();

    $run = ChecklistRun::factory()->create(array_merge([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::Pending,
        'operator_id' => null,
    ], $attributes));

    $templateItem = ChecklistTemplateItem::factory()->for($template, 'template')->create();

    ChecklistRunItem::factory()->create([
        'checklist_run_id' => $run->id,
        'checklist_template_item_id' => $templateItem->id,
        'sort_order' => 1,
        'description' => 'Cleaning the Vacuum Table',
    ]);

    return $run->fresh(['items']);
}

/*
|--------------------------------------------------------------------------
| Assignment is a convenience, not a fence
|--------------------------------------------------------------------------
*/

it('does not narrow what an operator sees when they are assigned a machine', function (): void {
    [$mine, $site] = machineAtNewSite('MATAN');

    $location = Location::factory()->for($site)->create();
    $alsoMine = Machine::factory()->for($location)->create(['name' => 'HP 570 Latex']);

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($mine->id);

    $visible = MachineScope::for($operator)->pluck('name')->all();

    // Assigned to one machine, still able to cover the other. Under the old
    // rule the assignment removed HP 570 Latex from view — covering a shift
    // meant an administrator editing a pivot table with no screen.
    expect($visible)->toContain('MATAN')
        ->and($visible)->toContain('HP 570 Latex')
        ->and(MachineScope::allows($operator, $alsoMine))->toBeTrue();
});

it('still keeps an operator out of another site', function (): void {
    [$mine, $site] = machineAtNewSite('MATAN');
    [$theirs] = machineAtNewSite('Someone else’s press');

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($mine->id);

    // The site is still the boundary — only the assignment stopped being one.
    expect(MachineScope::allows($operator, $mine))->toBeTrue()
        ->and(MachineScope::allows($operator, $theirs))->toBeFalse();
});

it('falls back to the sites of assigned machines when no default site is set', function (): void {
    [$machine, $site] = machineAtNewSite();

    // `default_site_id` is nullable and no screen sets it, so this is the
    // operator most likely to have been created in a hurry.
    $operator = User::factory()->operator()->create(['default_site_id' => null]);
    $operator->machines()->attach($machine->id);

    expect(MachineScope::allows($operator, $machine))->toBeTrue()
        ->and($site)->not->toBeNull();
});

it('shows nothing to a user with neither a site nor an assignment', function (): void {
    [$machine] = machineAtNewSite();

    $operator = User::factory()->operator()->create(['default_site_id' => null]);

    expect(MachineScope::for($operator)->count())->toBe(0)
        ->and(MachineScope::allows($operator, $machine))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Assigning someone to a machine, from a screen
|--------------------------------------------------------------------------
*/

it('assigns and unassigns an operator from the machine admin', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    [$machine, $site] = machineAtNewSite();

    $manager = User::factory()->create(['default_site_id' => $site->id]);
    $manager->assignRole('maintenance_manager');

    $operator = User::factory()->create(['full_name' => 'Darnell Joseph']);
    $operator->assignRole('operator');

    $component = Livewire::actingAs($manager)
        ->test(MachineManager::class)
        ->call('openOperatorsModal', $machine->id)
        ->set('attachOperatorId', (string) $operator->id)
        ->call('attachOperator')
        ->assertHasNoErrors();

    expect($machine->fresh()->operators->pluck('id')->all())->toBe([$operator->id]);

    $component->call('detachOperator', $operator->id);

    expect($machine->fresh()->operators)->toBeEmpty();
});

it('will not assign somebody who is not an active user', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    [$machine, $site] = machineAtNewSite();

    $manager = User::factory()->create(['default_site_id' => $site->id]);
    $manager->assignRole('maintenance_manager');

    $inactive = User::factory()->create(['is_active' => false]);

    // The id comes from the browser, so it is checked rather than trusted.
    Livewire::actingAs($manager)
        ->test(MachineManager::class)
        ->call('openOperatorsModal', $machine->id)
        ->set('attachOperatorId', (string) $inactive->id)
        ->call('attachOperator')
        ->assertHasErrors('attachOperatorId');
});

/*
|--------------------------------------------------------------------------
| Self-assignment and hand-over
|--------------------------------------------------------------------------
*/

it('makes the sheet theirs the moment an operator touches it', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    [$machine, $site] = machineAtNewSite();
    $run = runFor($machine);

    $operator = User::factory()->create(['default_site_id' => $site->id]);
    $operator->assignRole('operator');

    expect($run->operator_id)->toBeNull();

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('toggleDone', $run->items->first()->id, 'pending');

    $run->refresh();

    // Nobody handed this out. No assignment was needed to pick it up.
    expect($run->operator_id)->toBe($operator->id)
        ->and($run->status)->toBe(RunStatus::InProgress)
        ->and($run->started_at)->not->toBeNull();
});

it('records a hand-over when someone else continues the sheet', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    [$machine, $site] = machineAtNewSite();

    $first = User::factory()->create(['full_name' => 'Darnell Joseph', 'default_site_id' => $site->id]);
    $first->assignRole('operator');

    $second = User::factory()->create(['full_name' => 'Rina Mohammed', 'default_site_id' => $site->id]);
    $second->assignRole('operator');

    $run = runFor($machine, ['status' => RunStatus::InProgress, 'operator_id' => $first->id]);

    Livewire::actingAs($second)
        ->test(RunForm::class, ['run' => $run])
        ->call('toggleDone', $run->items->first()->id, 'pending')
        // Said on screen: the person picking it up is now the one signing.
        ->assertSet('notice', __('app.runs.taken_over_from', ['name' => 'Darnell Joseph']));

    expect($run->fresh()->operator_id)->toBe($second->id);

    $handover = Activity::query()->where('description', 'run.taken_over')->latest('id')->first();

    expect($handover)->not->toBeNull()
        ->and($handover->causer_id)->toBe($second->id)
        ->and($handover->properties['previous_operator'])->toBe('Darnell Joseph');
});

it('does not log a hand-over when the same operator carries on', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    [$machine, $site] = machineAtNewSite();

    $operator = User::factory()->create(['default_site_id' => $site->id]);
    $operator->assignRole('operator');

    $run = runFor($machine, ['status' => RunStatus::InProgress, 'operator_id' => $operator->id]);

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('toggleDone', $run->items->first()->id, 'pending')
        ->assertSet('notice', null);

    expect(Activity::query()->where('description', 'run.taken_over')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Issues are scoped more tightly than runs
|--------------------------------------------------------------------------
| Runs stay site-wide so a shift can be covered. The issues register is a
| standing worklist, and a plant-wide one buries the faults on the machines
| somebody actually runs.
*/

it('narrows issues to assigned machines while runs stay site-wide', function (): void {
    [$mine, $site] = machineAtNewSite('MATAN');

    $location = Location::factory()->for($site)->create();
    $notMine = Machine::factory()->for($location)->create(['name' => 'HP 570 Latex']);

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($mine->id);

    // Same site, same user, two different answers — that is the point.
    expect(MachineScope::allows($operator, $notMine))->toBeTrue()
        ->and(MachineScope::allowsIssue($operator, $notMine))->toBeFalse()
        ->and(MachineScope::allowsIssue($operator, $mine))->toBeTrue();
});

it('shows a whole site of issues to somebody with no assignments', function (): void {
    [$machine, $site] = machineAtNewSite();

    // Most users are in this state — user_machine was unreachable from the
    // UI until recently, so an empty register would look broken, not tidy.
    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);

    expect(MachineScope::allowsIssue($operator, $machine))->toBeTrue();
});

it('hides an issue on an unassigned machine from the detail screen', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    [$mine, $site] = machineAtNewSite('MATAN');

    $location = Location::factory()->for($site)->create();
    $notMine = Machine::factory()->for($location)->create(['name' => 'HP 570 Latex']);

    $operator = User::factory()->create(['default_site_id' => $site->id]);
    $operator->assignRole('operator');
    $operator->machines()->attach($mine->id);

    $ours = Issue::factory()->create(['machine_id' => $mine->id]);
    $theirs = Issue::factory()->create(['machine_id' => $notMine->id]);

    $this->actingAs($operator)->get(route('issues.show', $ours))->assertOk();
    $this->actingAs($operator)->get(route('issues.show', $theirs))->assertForbidden();
});

it('still lets an operator report a fault on any machine at their site', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    [$mine, $site] = machineAtNewSite('MATAN');

    $location = Location::factory()->for($site)->create();
    $notMine = Machine::factory()->for($location)->create(['name' => 'HP 570 Latex']);

    $operator = User::factory()->create(['default_site_id' => $site->id]);
    $operator->assignRole('operator');
    $operator->machines()->attach($mine->id);

    $creatable = Livewire::actingAs($operator)
        ->test(IssueRegister::class)
        ->instance()
        ->creatableMachines()
        ->pluck('name')
        ->all();

    // Reporting a fault must never be harder than walking past one, even
    // though the resulting issue will not show in this operator's register.
    expect($creatable)->toContain('MATAN')
        ->and($creatable)->toContain('HP 570 Latex');
});
