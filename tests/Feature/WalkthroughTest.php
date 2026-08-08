<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Roles;
use App\Support\ViewAs;
use App\Support\Walkthrough;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Lang;

/*
|--------------------------------------------------------------------------
| First-run walkthrough
|--------------------------------------------------------------------------
*/

function newUser(string $role): User
{
    $user = User::factory()->create(['walkthrough_seen_at' => null]);
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('shows each role its own walkthrough on first sight', function (string $role, int $steps, string $landing): void {
    $user = newUser($role);

    expect(Walkthrough::stepsFor($role))->toHaveCount($steps);

    $this->actingAs($user)->get($landing)
        ->assertOk()
        ->assertSee('walkthrough-title', escape: false)
        ->assertSee(Walkthrough::stepsFor($role)[0]['title']);
})->with([
    'operator' => ['operator', 5, '/runs'],
    'supervisor' => ['supervisor', 4, '/dashboard'],
    'maintenance manager' => ['maintenance_manager', 4, '/dashboard'],
    'quality assurance' => ['quality_assurance', 4, '/dashboard'],
    'admin' => ['admin', 4, '/dashboard'],
]);

it('shows every card written for a role, without a count to keep in step', function (string $role): void {
    // The cards used to be counted by a constant next to the class. Adding a
    // card to the language file and forgetting the number left it written and
    // never shown, with nothing to say so.
    $written = Lang::get("app.walkthrough.{$role}");

    expect(Walkthrough::stepsFor($role))->toHaveCount(count($written));
})->with(['operator', 'supervisor', 'maintenance_manager', 'quality_assurance', 'admin']);

it('has a walkthrough for every role the system defines', function (): void {
    // A role added without cards gets an empty tour rather than an error, and
    // an empty tour is invisible — so check the copy exists for all of them.
    foreach (Roles::ALL as $role) {
        expect(Walkthrough::stepsFor($role))->not->toBeEmpty("No walkthrough copy for {$role}");
    }
});

it('picks the most senior role, not the first one it finds', function (): void {
    // Roles are cumulative, so somebody senior also holds the junior ones.
    // Introducing a maintenance manager as an operator would be wrong.
    $user = newUser('operator');
    $user->assignRole('maintenance_manager');

    expect(Walkthrough::roleFor($user->fresh()))->toBe('maintenance_manager');
});

it('reaches an operator on the kiosk layout, where they actually start', function (): void {
    // The run form renders in the kiosk layout. An operator's first sight of
    // this system is a tablet, so a walkthrough only in the office chrome
    // would miss the person it is written for.
    expect(file_get_contents(resource_path('views/layouts/kiosk.blade.php')))
        ->toContain("@include('partials.walkthrough')")
        ->and(file_get_contents(resource_path('views/layouts/app.blade.php')))
        ->toContain("@include('partials.walkthrough')");
});

it('stops showing it once dismissed, and keeps it dismissed', function (): void {
    $user = newUser('supervisor');

    $this->actingAs($user)->get('/dashboard')->assertSee('walkthrough-title', escape: false);

    $this->actingAs($user)->post(route('walkthrough.complete'))->assertRedirect();

    expect($user->fresh()->walkthrough_seen_at)->not->toBeNull();

    // Including on a completely fresh session — the point of storing it on
    // the user rather than in the browser.
    $this->actingAs($user->fresh())->get('/dashboard')
        ->assertOk()
        ->assertDontSee('walkthrough-title', escape: false);
});

it('can be replayed by somebody who skipped it', function (): void {
    $user = newUser('supervisor');

    $this->actingAs($user)->post(route('walkthrough.complete'));
    expect($user->fresh()->needsWalkthrough())->toBeFalse();

    $this->actingAs($user->fresh())->post(route('walkthrough.replay'))->assertRedirect();

    expect($user->fresh()->needsWalkthrough())->toBeTrue();

    $this->actingAs($user->fresh())->get('/dashboard')->assertSee('walkthrough-title', escape: false);
});

it('does not interrupt an administrator who is previewing another role', function (): void {
    $admin = newUser('admin');

    $this->actingAs($admin)->post(route('view-as.start'), ['role' => 'operator']);

    // The admin has their own introduction pending, but a tour appearing over
    // a preview is noise — and dismissing it would mark the ADMIN onboarded
    // for a role they were only looking at.
    $this->actingAs($admin)->get('/runs')
        ->assertOk()
        ->assertDontSee('walkthrough-title', escape: false);

    expect(ViewAs::active())->toBeTrue()
        ->and($admin->fresh()->walkthrough_seen_at)->toBeNull();
});

it('never shows to a guest', function (): void {
    expect(Walkthrough::shouldShow(null))->toBeFalse();

    $this->get('/login')->assertOk()->assertDontSee('walkthrough-title', escape: false);
});

it('will not let a stray GET dismiss somebody’s walkthrough', function (): void {
    $user = newUser('operator');

    // Both routes write to the user's record, so neither may be reachable by
    // following a link or a crawler.
    $this->actingAs($user)->get('/walkthrough/complete')->assertMethodNotAllowed();

    expect($user->fresh()->needsWalkthrough())->toBeTrue();
});

it('has real copy behind every card, not a missing translation key', function (string $role): void {
    foreach (Walkthrough::stepsFor($role) as $step) {
        expect($step['title'])->not->toStartWith('app.')
            ->and($step['body'])->not->toStartWith('app.')
            ->and(mb_strlen($step['body']))->toBeGreaterThan(40);
    }
})->with(['operator', 'supervisor', 'maintenance_manager', 'quality_assurance', 'admin']);

it('keeps every sentence short enough to read on a shop floor', function (string $role): void {
    // The audience is somebody who has never used a computer-based form. The
    // first draft had sentences of 26 words; this is the guard that stops a
    // well-meant rewrite quietly putting them back.
    foreach (Walkthrough::stepsFor($role) as $step) {
        foreach (preg_split('/(?<=[.!?])\s+/', trim($step['body'])) as $sentence) {
            expect(str_word_count($sentence))
                ->toBeLessThanOrEqual(18, "Too long in {$role}: {$sentence}");
        }
    }
})->with(['operator', 'supervisor', 'maintenance_manager', 'quality_assurance', 'admin']);

/*
|--------------------------------------------------------------------------
| An administrator reading somebody else's walkthrough
|--------------------------------------------------------------------------
*/

it('lets an administrator preview any role, without spending their own', function (string $role): void {
    $admin = newUser('admin');
    $admin->markWalkthroughSeen();

    $this->actingAs($admin)->post(route('walkthrough.preview'), ['role' => $role])->assertRedirect();

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertSee(Walkthrough::stepsFor($role)[0]['title'])
        // Said plainly, or it reads as the administrator's own introduction.
        ->assertSee(__('app.walkthrough.previewing', ['role' => __('app.roles.'.$role)]));
})->with(['operator', 'supervisor', 'maintenance_manager', 'quality_assurance', 'admin']);

it('closing a preview does not mark the administrator onboarded', function (): void {
    $admin = newUser('admin');

    expect($admin->needsWalkthrough())->toBeTrue();

    $this->actingAs($admin)->post(route('walkthrough.preview'), ['role' => 'operator']);
    $this->actingAs($admin)->post(route('walkthrough.complete'));

    // Looking at an operator's cards must not consume the administrator's own
    // first-run introduction.
    expect($admin->fresh()->needsWalkthrough())->toBeTrue()
        ->and(Walkthrough::isPreviewing())->toBeFalse();
});

it('keeps the preview to administrators, and to real roles', function (): void {
    $supervisor = newUser('supervisor');

    $this->actingAs($supervisor)
        ->post(route('walkthrough.preview'), ['role' => 'operator'])
        ->assertForbidden();

    $admin = newUser('admin');

    $this->actingAs($admin)
        ->post(route('walkthrough.preview'), ['role' => 'wizard'])
        ->assertSessionHasErrors('role');

    expect(Walkthrough::isPreviewing())->toBeFalse();
});
