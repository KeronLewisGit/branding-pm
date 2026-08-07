<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Livewire\Runs\RunReview;
use App\Livewire\Runs\VerificationQueue;
use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
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
| Quality Assurance — the third sign-off
|--------------------------------------------------------------------------
| Operator signs, supervisor approves, QA verifies. Three people, three acts,
| and the last one performed by somebody who did neither of the first two.
|
| The role is standalone, not a rung on the ladder: a QA officer reads every
| sheet in the plant but cannot complete a check, approve one, or amend one.
| An auditor asking "who checked the checker?" must not be told "the same
| person".
*/

function qaOfficer(?Site $site = null): User
{
    $user = User::factory()->create(['default_site_id' => $site?->id]);
    $user->assignRole('quality_assurance');

    return $user;
}

/**
 * @return array{0: ChecklistRun, 1: Site, 2: Machine}
 */
function verifiableRun(array $attributes = []): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();
    $template = ChecklistTemplate::factory()->for($machine)->create();

    $run = ChecklistRun::factory()->create(array_merge([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::Approved,
        'submitted_at' => now()->subDay(),
        'qa_verified_at' => null,
    ], $attributes));

    return [$run->fresh(), $site, $machine];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| What the role can and cannot do
|--------------------------------------------------------------------------
*/

it('can read and verify, but cannot complete, approve or amend', function (): void {
    $qa = qaOfficer();

    expect($qa->can('run.view'))->toBeTrue()
        ->and($qa->can('run.verify'))->toBeTrue()
        ->and($qa->can('report.view'))->toBeTrue()
        ->and($qa->can('export.data'))->toBeTrue()
        // The separation that makes the role worth having.
        ->and($qa->can('run.complete'))->toBeFalse()
        ->and($qa->can('run.approve'))->toBeFalse()
        ->and($qa->can('run.amend'))->toBeFalse()
        ->and($qa->can('machine.manage'))->toBeFalse()
        ->and($qa->can('user.manage'))->toBeFalse();
});

it('sees the whole plant, not one site', function (): void {
    [, , $mine] = verifiableRun();
    [, , $theirs] = verifiableRun();

    // Quality assurance restricted to a single site could not do the job.
    $qa = qaOfficer();

    expect(MachineScope::allows($qa, $mine))->toBeTrue()
        ->and(MachineScope::allows($qa, $theirs))->toBeTrue();
});

it('keeps everyone else out of the verification queue', function (string $role, bool $allowed): void {
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('runs.verifications'));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'operator' => ['operator', false],
    'supervisor' => ['supervisor', false],
    'maintenance manager' => ['maintenance_manager', false],
    'quality assurance' => ['quality_assurance', true],
    // Admin holds every permission, `run.verify` included.
    'admin' => ['admin', true],
]);

/*
|--------------------------------------------------------------------------
| Verifying
|--------------------------------------------------------------------------
*/

it('records who verified, when, and any finding', function (): void {
    [$run, $site] = verifiableRun();

    $qa = qaOfficer($site);

    Livewire::actingAs($qa)
        ->test(RunReview::class, ['run' => $run])
        ->set('qaComment', 'Photo for item 3 is blurred; asked for a re-shoot next cycle.')
        ->call('verify')
        ->assertHasNoErrors();

    $run->refresh();

    expect($run->qa_verified_by)->toBe($qa->id)
        ->and($run->qa_verified_at)->not->toBeNull()
        ->and($run->qa_comment)->toContain('blurred')
        // Verification is recorded alongside approval, not instead of it.
        // A fourth status would silently change every compliance figure.
        ->and($run->status)->toBe(RunStatus::Approved);

    expect(Activity::query()->where('description', 'run.qa_verified')->count())->toBe(1);
});

it('takes a verification with no finding at all', function (): void {
    [$run, $site] = verifiableRun();

    // Most verifications have nothing to say, and forcing a comment would
    // fill the record with "ok".
    Livewire::actingAs(qaOfficer($site))
        ->test(RunReview::class, ['run' => $run])
        ->call('verify')
        ->assertHasNoErrors();

    expect($run->fresh()->qa_verified_at)->not->toBeNull()
        ->and($run->fresh()->qa_comment)->toBeNull();
});

it('will not verify a sheet the supervisor has not signed off', function (): void {
    [$run, $site] = verifiableRun(['status' => RunStatus::Submitted]);

    Livewire::actingAs(qaOfficer($site))
        ->test(RunReview::class, ['run' => $run])
        ->call('verify')
        ->assertForbidden();

    expect($run->fresh()->qa_verified_at)->toBeNull();
});

it('will not verify the same sheet twice', function (): void {
    [$run, $site] = verifiableRun();

    $first = qaOfficer($site);

    Livewire::actingAs($first)->test(RunReview::class, ['run' => $run])->call('verify');

    $verifiedAt = $run->fresh()->qa_verified_at;

    // Verifying twice would make "who verified this" ambiguous.
    Livewire::actingAs(qaOfficer($site))
        ->test(RunReview::class, ['run' => $run->fresh()])
        ->call('verify')
        ->assertForbidden();

    expect($run->fresh()->qa_verified_by)->toBe($first->id)
        ->and($run->fresh()->qa_verified_at->timestamp)->toBe($verifiedAt->timestamp);
});

it('will not let somebody verify work they did or approved themselves', function (): void {
    [$run, $site] = verifiableRun();

    // Someone holding several roles — which is exactly when this matters,
    // since the QA role alone can neither complete nor approve.
    $both = User::factory()->create(['default_site_id' => $site->id]);
    $both->assignRole('quality_assurance');

    $run->update(['supervisor_id' => $both->id]);

    Livewire::actingAs($both)
        ->test(RunReview::class, ['run' => $run->fresh()])
        ->call('verify')
        ->assertForbidden();

    $run->update(['supervisor_id' => null, 'operator_id' => $both->id]);

    Livewire::actingAs($both)
        ->test(RunReview::class, ['run' => $run->fresh()])
        ->call('verify')
        ->assertForbidden();

    expect($run->fresh()->qa_verified_at)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The queue
|--------------------------------------------------------------------------
*/

it('lists approved sheets awaiting verification and drops them once done', function (): void {
    [$run, $site] = verifiableRun();

    $qa = qaOfficer($site);

    $queue = Livewire::actingAs($qa)->test(VerificationQueue::class);

    expect($queue->instance()->outstandingCount())->toBe(1);

    Livewire::actingAs($qa)->test(RunReview::class, ['run' => $run])->call('verify');

    // A worklist, not an archive.
    $after = Livewire::actingAs($qa)->test(VerificationQueue::class);

    expect($after->instance()->outstandingCount())->toBe(0);

    $after->set('showVerified', true)->assertSee($run->machine->name);
});

it('leaves unapproved sheets out of the queue', function (): void {
    verifiableRun(['status' => RunStatus::Submitted]);
    verifiableRun(['status' => RunStatus::Missed]);

    // Chasing a supervisor's backlog is the supervisor's queue, not QA's.
    expect(Livewire::actingAs(qaOfficer())->test(VerificationQueue::class)->instance()->outstandingCount())->toBe(0);
});

it('lets a QA officer open a sheet even though they cannot approve it', function (): void {
    [$run, $site] = verifiableRun();

    // The review screen used to demand `run.approve`, which QA does not hold.
    $this->actingAs(qaOfficer($site))
        ->get(route('runs.review', $run))
        ->assertOk();
});
