<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use App\Support\SignatureImage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Signature and photo access
|--------------------------------------------------------------------------
| Both used to be served straight off the public disk as guessable URLs
| (seed-notes §D11): no login needed to fetch an operator's signature or a
| photo of a fault. Everything now goes through MediaController, which checks
| the policy of the record the file belongs to.
*/

/** A real 1×1 PNG. */
function tinyPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
}

/**
 * @return array{0: ChecklistRun, 1: Site}
 */
function runWithSignature(): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create();
    $template = ChecklistTemplate::factory()->for($machine)->create();

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'operator_signature_path' => 'signatures/runs/1/operator-test.png',
    ]);

    Storage::disk(SignatureImage::diskName())->put($run->operator_signature_path, tinyPng());

    return [$run, $site];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake(SignatureImage::diskName());
});

it('refuses a signature to anyone who is not signed in', function (): void {
    [$run] = runWithSignature();

    // The whole point: this used to be a public file.
    $this->get(route('media.signature', ['run' => $run, 'role' => 'operator']))
        ->assertRedirect(route('login'));
});

it('serves a signature to someone who may read the run', function (): void {
    [$run, $site] = runWithSignature();

    $supervisor = User::factory()->create(['default_site_id' => $site->id]);
    $supervisor->assignRole('supervisor');

    $response = $this->actingAs($supervisor)
        ->get(route('media.signature', ['run' => $run, 'role' => 'operator']));

    $response->assertOk();

    // Never let a browser treat an uploaded or stored file as a document.
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private');
});

it('refuses a signature on another site’s run', function (): void {
    [$run] = runWithSignature();

    $otherSite = Site::factory()->create();
    $outsider = User::factory()->create(['default_site_id' => $otherSite->id]);
    $outsider->assignRole('supervisor');

    $this->actingAs($outsider)
        ->get(route('media.signature', ['run' => $run, 'role' => 'operator']))
        ->assertForbidden();
});

it('will not let the role segment name another column', function (): void {
    [$run, $site] = runWithSignature();

    $supervisor = User::factory()->create(['default_site_id' => $site->id]);
    $supervisor->assignRole('supervisor');

    // The route constrains `role` to the two signature columns.
    $this->actingAs($supervisor)
        ->get('/media/signatures/'.$run->id.'/password')
        ->assertNotFound();
});

it('refuses a run photo to anyone who may not read its run', function (): void {
    [$run, $site] = runWithSignature();

    $attachment = Attachment::create([
        'attachable_type' => $run->getMorphClass(),
        'attachable_id' => $run->id,
        'disk' => SignatureImage::diskName(),
        'path' => 'run-attachments/1/photo.png',
        'original_name' => 'photo.png',
        'mime' => 'image/png',
        'size' => 70,
    ]);

    Storage::disk(SignatureImage::diskName())->put($attachment->path, tinyPng());

    $this->get(route('media.attachment', ['attachment' => $attachment]))
        ->assertRedirect(route('login'));

    $otherSite = Site::factory()->create();
    $outsider = User::factory()->create(['default_site_id' => $otherSite->id]);
    $outsider->assignRole('supervisor');

    $this->actingAs($outsider)
        ->get(route('media.attachment', ['attachment' => $attachment]))
        ->assertForbidden();

    $insider = User::factory()->create(['default_site_id' => $site->id]);
    $insider->assignRole('supervisor');

    $this->actingAs($insider)
        ->get(route('media.attachment', ['attachment' => $attachment]))
        ->assertOk();
});

it('keeps signatures off the web-reachable disk', function (): void {
    // `local` is not served by the web server; `public` is. If this ever flips
    // back, every signature becomes a guessable URL again.
    expect(SignatureImage::diskName())->not->toBe('public')
        ->and(config('filesystems.default'))->not->toBe('public');
});
