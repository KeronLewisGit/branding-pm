<?php

declare(strict_types=1);

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Livewire\Runs\RunReview;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistTemplate;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Amending an approved run
|--------------------------------------------------------------------------
| SPEC: "Approved runs are immutable — corrections happen by an admin-only
| amendment that is logged, never by silent edit."
|
| The point of these tests is the "logged" half. Anyone can write an update
| statement; what makes this defensible is that the old value, the new value,
| the reason and the actor all survive.
*/

/**
 * @return array{0: ChecklistRun, 1: Site}
 */
function approvedRun(array $attributes = []): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();
    $template = ChecklistTemplate::factory()->for($machine)->create();

    $run = ChecklistRun::factory()->create(array_merge([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::Approved,
        'notes' => 'Ran clean all shift.',
        'submitted_at' => now()->subDay(),
    ], $attributes));

    ChecklistRunItem::factory()->create([
        'checklist_run_id' => $run->id,
        'sort_order' => 1,
        'description' => 'Cleaning the Vacuum Table',
        'status' => RunItemStatus::Done,
    ]);

    return [$run->fresh(['items']), $site];
}

function amender(Site $site, string $role = 'maintenance_manager'): User
{
    $user = User::factory()->create(['default_site_id' => $site->id]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Who may amend
|--------------------------------------------------------------------------
*/

it('offers amendment only to a holder of run.amend', function (string $role, bool $allowed): void {
    [$run, $site] = approvedRun();

    $user = amender($site, $role);

    $component = Livewire::actingAs($user)->test(RunReview::class, ['run' => $run]);

    expect($component->instance()->canAmend())->toBe($allowed);
})->with([
    'supervisor' => ['supervisor', false],
    'maintenance manager' => ['maintenance_manager', true],
    'admin' => ['admin', true],
]);

it('refuses to amend a run that is not approved', function (): void {
    // The whole justification is that the sheet is signed and therefore
    // immutable. A submitted or rejected run is still editable normally.
    [$run, $site] = approvedRun(['status' => RunStatus::Submitted]);

    $manager = amender($site);

    Livewire::actingAs($manager)
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendNotes')
        ->assertForbidden();
});

it('will not let a supervisor amend', function (): void {
    [$run, $site] = approvedRun();

    $supervisor = amender($site, 'supervisor');

    Livewire::actingAs($supervisor)
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendNotes')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| What an amendment records
|--------------------------------------------------------------------------
*/

it('corrects an item answer and records both values, the reason and the actor', function (): void {
    [$run, $site] = approvedRun();

    $manager = amender($site);
    $item = $run->items->first();

    Livewire::actingAs($manager)
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendItem', $item->id)
        ->set('amendItemStatus', RunItemStatus::Failed->value)
        ->set('amendFailReason', 'Table was never cleaned; wrong row ticked.')
        ->set('amendReason', 'Operator confirmed on 8 Aug that item 1 was ticked in error.')
        ->call('saveAmendment')
        ->assertHasNoErrors();

    expect($item->fresh()->status)->toBe(RunItemStatus::Failed);

    $entry = Activity::query()->where('description', 'run.amended')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe($manager->id)
        ->and($entry->properties['old'])->toContain('Done')
        ->and($entry->properties['new'])->toContain('Failed')
        ->and($entry->properties['reason'])->toContain('ticked in error');
});

it('corrects the notes', function (): void {
    [$run, $site] = approvedRun();

    Livewire::actingAs(amender($site))
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendNotes')
        ->set('amendNotes', 'Ran clean all shift except the last hour.')
        ->set('amendReason', 'Operator added detail the morning after.')
        ->call('saveAmendment')
        ->assertHasNoErrors();

    expect($run->fresh()->notes)->toBe('Ran clean all shift except the last hour.');
});

/*
|--------------------------------------------------------------------------
| The guards
|--------------------------------------------------------------------------
*/

it('requires a reason', function (): void {
    [$run, $site] = approvedRun();

    Livewire::actingAs(amender($site))
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendNotes')
        ->set('amendNotes', 'Something different.')
        ->set('amendReason', '')
        ->call('saveAmendment')
        ->assertHasErrors('amendReason');

    expect($run->fresh()->notes)->toBe('Ran clean all shift.');
});

it('records nothing when nothing actually changed', function (): void {
    [$run, $site] = approvedRun();

    // A reason attached to a change that never happened is noise in the
    // record, and worse, it reads as though something was corrected.
    Livewire::actingAs(amender($site))
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendNotes')
        ->set('amendReason', 'Checked against the paper copy.')
        ->call('saveAmendment')
        ->assertHasErrors('amendReason');

    expect(Activity::query()->where('description', 'run.amended')->count())->toBe(0);
});

it('leaves the status and both signatures alone', function (): void {
    [$run, $site] = approvedRun([
        'operator_signature_path' => 'signatures/op.png',
        'supervisor_signature_path' => 'signatures/sup.png',
    ]);

    Livewire::actingAs(amender($site))
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendNotes')
        ->set('amendNotes', 'Corrected note.')
        ->set('amendReason', 'Typo in the original note.')
        ->call('saveAmendment')
        ->assertHasNoErrors();

    $run->refresh();

    // A correction is not a re-approval. Re-signing on somebody else's
    // behalf is exactly what the two-person rule forbids.
    expect($run->status)->toBe(RunStatus::Approved)
        ->and($run->operator_signature_path)->toBe('signatures/op.png')
        ->and($run->supervisor_signature_path)->toBe('signatures/sup.png');
});

it('shows the amendment history to everyone who can read the sheet', function (): void {
    [$run, $site] = approvedRun();

    Livewire::actingAs(amender($site))
        ->test(RunReview::class, ['run' => $run])
        ->call('openAmendNotes')
        ->set('amendNotes', 'Corrected note.')
        ->set('amendReason', 'Typo in the original note.')
        ->call('saveAmendment');

    // A supervisor cannot amend, but must be able to see that somebody did —
    // a correction the reader cannot see is the silent edit the spec forbids.
    Livewire::actingAs(amender($site, 'supervisor'))
        ->test(RunReview::class, ['run' => $run->fresh()])
        ->assertSee(__('app.amend.history'))
        ->assertSee('Typo in the original note.');
});
