<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\ViewAs;
use App\Support\Walkthrough;
use Database\Seeders\RolesAndPermissionsSeeder;

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
