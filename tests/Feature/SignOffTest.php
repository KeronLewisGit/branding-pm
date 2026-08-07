<?php

declare(strict_types=1);

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Livewire\Runs\RunForm;
use App\Livewire\Runs\RunReview;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistTemplate;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use App\Support\SignatureImage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Milestone 5 — signatures and supervisor sign-off
|--------------------------------------------------------------------------
| These cover what a browser pass cannot check repeatably: that the server
| refuses an unsigned or wrongly-confirmed submission, that the signature is
| actually written, and that the two-person rule holds.
|
| The canvas itself — drawing, clearing, exporting a PNG — is JavaScript and
| is NOT covered here. It still needs a human with a tablet.
*/

/** A real 1×1 PNG, so SignatureImage's byte-level checks see a genuine image. */
function signatureDataUrl(): string
{
    $png = hex2bin(
        '89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de'
        .'0000000c4944415408d763f8ffff3f0005fe02fea735e8b20000000049454e44ae426082'
    );

    return 'data:image/png;base64,'.base64_encode((string) $png);
}

/**
 * A machine, a template and a run whose items are all answered — the state a
 * sheet is in when the operator reaches the Submit button.
 *
 * @return array{0: ChecklistRun, 1: Machine, 2: User}
 */
function completedRun(bool $requiresSignoff = true): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();

    $template = ChecklistTemplate::factory()->for($machine)->create([
        'requires_supervisor_signoff' => $requiresSignoff,
    ]);

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::InProgress,
    ]);

    ChecklistRunItem::factory()->count(3)->create([
        'checklist_run_id' => $run->id,
        'status' => RunItemStatus::Done,
        'completed_at' => now(),
    ]);

    $operator = User::factory()->operator()->withPin()->create([
        'default_site_id' => $site->id,
    ]);
    $operator->machines()->attach($machine->id);

    return [$run->fresh(), $machine, $operator];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Whatever disk signatures are configured to use. Hardcoding 'public'
    // made these pass only for the old, web-reachable default.
    Storage::fake(SignatureImage::diskName());
});

it('stores the signature and submits when the PIN is right', function (): void {
    [$run, , $operator] = completedRun();

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('submit', signatureDataUrl(), '1234')
        ->assertHasNoErrors();

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Submitted)
        ->and($run->operator_id)->toBe($operator->id)
        ->and($run->operator_signed_at)->not->toBeNull()
        ->and($run->submitted_at)->not->toBeNull()
        ->and($run->operator_signature_path)->toStartWith('signatures/runs/'.$run->id.'/operator-');

    Storage::disk(SignatureImage::diskName())->assertExists($run->operator_signature_path);
});

it('refuses to submit with the wrong PIN and leaves the run untouched', function (): void {
    [$run, , $operator] = completedRun();

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('submit', signatureDataUrl(), '9999')
        ->assertHasErrors('confirmation');

    $run->refresh();

    expect($run->status)->toBe(RunStatus::InProgress)
        ->and($run->operator_signature_path)->toBeNull();

    // Nothing is written on a failed confirmation — not even the image.
    expect(Storage::disk(SignatureImage::diskName())->allFiles())->toBeEmpty();
});

it('refuses to submit without a signature', function (): void {
    [$run, , $operator] = completedRun();

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('submit', '', '1234')
        ->assertHasErrors('signature');

    expect($run->refresh()->status)->toBe(RunStatus::InProgress);
});

it('rejects a payload that is not really a PNG', function (): void {
    [$run, , $operator] = completedRun();

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        // Correct header, arbitrary bytes behind it.
        ->call('submit', 'data:image/png;base64,'.base64_encode('<?php echo "not an image";'), '1234')
        ->assertHasErrors('signature');

    expect($run->refresh()->status)->toBe(RunStatus::InProgress);
});

it('completes a run outright when its template needs no sign-off', function (): void {
    [$run, , $operator] = completedRun(requiresSignoff: false);

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('submit', signatureDataUrl(), '1234')
        ->assertHasNoErrors();

    // Nobody is entitled to sign it off, so it does not join the queue.
    expect($run->refresh()->status)->toBe(RunStatus::Approved);
});

it('lets a supervisor approve a submitted run with a signature', function (): void {
    [$run, , $operator] = completedRun();

    $run->update([
        'status' => RunStatus::Submitted,
        'operator_id' => $operator->id,
        'submitted_at' => now(),
    ]);

    $supervisor = User::factory()->supervisor()->create([
        'default_site_id' => $run->machine->location->site_id,
    ]);

    Livewire::actingAs($supervisor)
        ->test(RunReview::class, ['run' => $run])
        ->set('comment', 'Looks right.')
        ->call('approve', signatureDataUrl())
        ->assertHasNoErrors();

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Approved)
        ->and($run->supervisor_id)->toBe($supervisor->id)
        ->and($run->supervisor_signed_at)->not->toBeNull()
        ->and($run->supervisor_comment)->toBe('Looks right.');

    Storage::disk(SignatureImage::diskName())->assertExists($run->supervisor_signature_path);
});

it('requires a comment to send a run back, and takes no signature for it', function (): void {
    [$run, , $operator] = completedRun();

    $run->update([
        'status' => RunStatus::Submitted,
        'operator_id' => $operator->id,
        'submitted_at' => now(),
    ]);

    $supervisor = User::factory()->supervisor()->create([
        'default_site_id' => $run->machine->location->site_id,
    ]);

    $component = Livewire::actingAs($supervisor)->test(RunReview::class, ['run' => $run]);

    $component->set('comment', '')->call('reject')->assertHasErrors('comment');

    expect($run->refresh()->status)->toBe(RunStatus::Submitted);

    $component->set('comment', 'Platen still dirty — clean and resubmit.')
        ->call('reject')
        ->assertHasNoErrors();

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Rejected)
        ->and($run->supervisor_comment)->toBe('Platen still dirty — clean and resubmit.')
        // A rejection is not a sign-off.
        ->and($run->supervisor_signature_path)->toBeNull()
        ->and($run->supervisor_signed_at)->toBeNull()
        // The run is editable again, so the operator can fix and resubmit.
        ->and($run->status->isEditable())->toBeTrue();
});

it('will not let a supervisor sign off a run they operated themselves', function (): void {
    [$run, $machine] = completedRun();

    // A supervisor covering a shift: they hold run.approve AND did the work.
    $supervisor = User::factory()->supervisor()->withPin()->create([
        'default_site_id' => $machine->location->site_id,
    ]);

    $run->update([
        'status' => RunStatus::Submitted,
        'operator_id' => $supervisor->id,
        'submitted_at' => now(),
    ]);

    Livewire::actingAs($supervisor)
        ->test(RunReview::class, ['run' => $run])
        ->call('approve', signatureDataUrl())
        ->assertForbidden();

    expect($run->refresh()->status)->toBe(RunStatus::Submitted);
});

it('replaces and deletes the earlier signature when a rejected run is signed again', function (): void {
    [$run, , $operator] = completedRun();

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run])
        ->call('submit', signatureDataUrl(), '1234');

    $first = $run->refresh()->operator_signature_path;

    $run->update(['status' => RunStatus::Rejected, 'supervisor_comment' => 'Do it again.']);

    Livewire::actingAs($operator)
        ->test(RunForm::class, ['run' => $run->fresh()])
        ->call('submit', signatureDataUrl(), '1234')
        ->assertHasNoErrors();

    $second = $run->refresh()->operator_signature_path;

    expect($second)->not->toBe($first);

    Storage::disk(SignatureImage::diskName())->assertMissing($first);
    Storage::disk(SignatureImage::diskName())->assertExists($second);
});
