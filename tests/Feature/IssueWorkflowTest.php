<?php

declare(strict_types=1);

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Livewire\Issues\IssueDetail;
use App\Livewire\Issues\IssueRegister;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Milestone 6 — issue register and workflow
|--------------------------------------------------------------------------
| The rules worth protecting: visibility follows the machine scope, status
| only moves where the enum allows, resolving needs notes, and an issue can
| only be handed to somebody able to act on it.
*/

/**
 * @return array{0: Issue, 1: Machine, 2: Site}
 */
function issueOnNewMachine(array $attributes = []): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();

    $issue = Issue::factory()->create(array_merge([
        'machine_id' => $machine->id,
        'checklist_run_id' => null,
        'checklist_run_item_id' => null,
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Medium,
        'assigned_to' => null,
        'resolved_at' => null,
        'resolution_notes' => null,
    ], $attributes));

    return [$issue, $machine, $site];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('hides issues on machines outside the operator scope', function (): void {
    [$mine, $myMachine, $site] = issueOnNewMachine();
    [$theirs] = issueOnNewMachine();

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($myMachine->id);

    $this->actingAs($operator)->get(route('issues.show', $mine))->assertOk();
    $this->actingAs($operator)->get(route('issues.show', $theirs))->assertForbidden();
});

it('shows an operator their own machines only in the register', function (): void {
    [$mine, $myMachine, $site] = issueOnNewMachine(['description' => 'Belt slipping on the drive']);
    [$theirs] = issueOnNewMachine(['description' => 'Chiller leaking coolant']);

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($myMachine->id);

    Livewire::actingAs($operator)
        ->test(IssueRegister::class)
        ->assertSee('Belt slipping on the drive')
        ->assertDontSee('Chiller leaking coolant');
});

it('gives an operator no triage controls', function (): void {
    [$issue, $machine, $site] = issueOnNewMachine();

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($machine->id);

    // The buttons are absent, and the action refuses even if one is forged.
    Livewire::actingAs($operator)
        ->test(IssueDetail::class, ['issue' => $issue])
        ->assertSee('A supervisor or the maintenance manager handles')
        ->call('moveTo', IssueStatus::Acknowledged->value)
        ->assertForbidden();

    expect($issue->refresh()->status)->toBe(IssueStatus::Open);
});

it('moves an issue along the allowed path', function (): void {
    [$issue, , $site] = issueOnNewMachine();

    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);

    Livewire::actingAs($supervisor)
        ->test(IssueDetail::class, ['issue' => $issue])
        ->call('moveTo', IssueStatus::Acknowledged->value)
        ->assertHasNoErrors()
        ->call('moveTo', IssueStatus::InProgress->value)
        ->assertHasNoErrors();

    expect($issue->refresh()->status)->toBe(IssueStatus::InProgress);
});

it('refuses a transition the current status does not allow', function (): void {
    [$issue, , $site] = issueOnNewMachine(['status' => IssueStatus::Closed]);

    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);

    Livewire::actingAs($supervisor)
        ->test(IssueDetail::class, ['issue' => $issue])
        // Closed reopens to `open`; it cannot jump straight back to resolved.
        ->call('moveTo', IssueStatus::Resolved->value)
        ->assertHasErrors('status');

    expect($issue->refresh()->status)->toBe(IssueStatus::Closed);
});

it('requires notes to resolve, then records who and when', function (): void {
    [$issue, , $site] = issueOnNewMachine(['status' => IssueStatus::InProgress]);

    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);

    $component = Livewire::actingAs($supervisor)->test(IssueDetail::class, ['issue' => $issue]);

    $component->set('resolutionNotes', '')
        ->call('moveTo', IssueStatus::Resolved->value)
        ->assertHasErrors('resolutionNotes');

    expect($issue->refresh()->status)->toBe(IssueStatus::InProgress);

    $component->set('resolutionNotes', 'Replaced the drive belt and re-tensioned it.')
        ->call('moveTo', IssueStatus::Resolved->value)
        ->assertHasNoErrors();

    $issue->refresh();

    expect($issue->status)->toBe(IssueStatus::Resolved)
        ->and($issue->resolved_at)->not->toBeNull()
        ->and($issue->resolution_notes)->toBe('Replaced the drive belt and re-tensioned it.');
});

it('keeps the notes but clears the resolved timestamp when reopened', function (): void {
    [$issue, , $site] = issueOnNewMachine([
        'status' => IssueStatus::Resolved,
        'resolved_at' => now()->subDay(),
        'resolution_notes' => 'Tightened the fitting.',
    ]);

    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);

    Livewire::actingAs($supervisor)
        ->test(IssueDetail::class, ['issue' => $issue])
        ->call('moveTo', IssueStatus::Open->value)
        ->assertHasNoErrors();

    $issue->refresh();

    expect($issue->status)->toBe(IssueStatus::Open)
        ->and($issue->resolved_at)->toBeNull()
        // The previous attempt is part of the history of this fault.
        ->and($issue->resolution_notes)->toBe('Tightened the fitting.');
});

it('only assigns an issue to someone who can resolve one', function (): void {
    [$issue, $machine, $site] = issueOnNewMachine();

    $supervisor = User::factory()->supervisor()->create(['default_site_id' => $site->id]);
    $manager = User::factory()->manager()->create(['default_site_id' => $site->id]);
    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($machine->id);

    $component = Livewire::actingAs($supervisor)->test(IssueDetail::class, ['issue' => $issue]);

    $component->call('assign', (string) $manager->id)->assertHasNoErrors();

    expect($issue->refresh()->assigned_to)->toBe($manager->id);

    // An operator holds issue.view/create but not issue.resolve.
    $component->call('assign', (string) $operator->id)->assertForbidden();

    expect($issue->refresh()->assigned_to)->toBe($manager->id);
});

it('raises an issue that no checklist found', function (): void {
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($machine->id);

    Livewire::actingAs($operator)
        ->test(IssueRegister::class)
        ->call('openCreate')
        ->set('newMachineId', (string) $machine->id)
        ->set('newSeverity', IssueSeverity::High->value)
        ->set('newDescription', 'Guard rattling loose at speed.')
        ->call('createIssue')
        ->assertHasNoErrors();

    $issue = Issue::query()->where('description', 'Guard rattling loose at speed.')->firstOrFail();

    expect($issue->status)->toBe(IssueStatus::Open)
        ->and($issue->severity)->toBe(IssueSeverity::High)
        ->and($issue->raised_by)->toBe($operator->id)
        ->and($issue->machine_id)->toBe($machine->id)
        // Not found by a checklist, so it hangs off no run.
        ->and($issue->checklist_run_id)->toBeNull();
});

it('will not raise an issue against a machine outside the scope', function (): void {
    $site = Site::factory()->create();
    $mine = Machine::factory()->for(Location::factory()->for($site))->create();
    $theirs = Machine::factory()->for(Location::factory()->for(Site::factory()))->create();

    $operator = User::factory()->operator()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($mine->id);

    Livewire::actingAs($operator)
        ->test(IssueRegister::class)
        ->call('openCreate')
        ->set('newMachineId', (string) $theirs->id)
        ->set('newDescription', 'Should never be created.')
        ->call('createIssue')
        ->assertHasErrors('newMachineId');

    expect(Issue::query()->where('machine_id', $theirs->id)->exists())->toBeFalse();
});
