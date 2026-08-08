<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\ViewAs;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| "View as" — an administrator previewing another role
|--------------------------------------------------------------------------
| The property that makes this safe to have at all is that it can only ever
| REMOVE access. Most of these tests exist to hold that line.
*/

function anAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets an administrator preview each role', function (string $role, int $expectedLinks): void {
    $admin = anAdmin();

    $this->actingAs($admin)
        ->post(route('view-as.start'), ['role' => $role])
        ->assertRedirect(route('dashboard'));

    expect(ViewAs::active())->toBeTrue()
        ->and(ViewAs::role())->toBe($role);

    // The menu is the visible half of the preview.
    $landing = $role === 'operator' ? route('runs.index') : route('dashboard');

    $this->actingAs($admin)->get($landing)
        ->assertOk()
        ->assertSee(__('app.view_as.active', ['role' => __('app.roles.'.$role)]));
})->with([
    'operator' => ['operator', 2],
    'supervisor' => ['supervisor', 5],
    'maintenance manager' => ['maintenance_manager', 10],
    'quality assurance' => ['quality_assurance', 5],
]);

it('takes permissions away and never adds any', function (): void {
    $admin = anAdmin();

    // An administrator holds everything, so the effective set while
    // previewing is exactly the previewed role's — the intersection.
    expect($admin->can('user.manage'))->toBeTrue();

    $this->actingAs($admin)->post(route('view-as.start'), ['role' => 'operator']);

    expect($admin->fresh()->can('user.manage'))->toBeFalse()
        ->and($admin->fresh()->can('machine.manage'))->toBeFalse()
        ->and($admin->fresh()->can('run.approve'))->toBeFalse()
        // …and keeps the ones the role genuinely has.
        ->and($admin->fresh()->can('run.view'))->toBeTrue()
        ->and($admin->fresh()->can('issue.create'))->toBeTrue();
});

it('closes the admin screens while previewing an operator', function (): void {
    $admin = anAdmin();

    $this->actingAs($admin)->get(route('admin.users'))->assertOk();

    $this->actingAs($admin)->post(route('view-as.start'), ['role' => 'operator']);

    $this->actingAs($admin)->get(route('admin.users'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.machines'))->assertForbidden();
    $this->actingAs($admin)->get(route('runs.verifications'))->assertForbidden();

    // Still an operator's own screens.
    $this->actingAs($admin)->get(route('runs.index'))->assertOk();
});

it('stops waving the administrator past the policies', function (): void {
    $admin = anAdmin();

    // Every policy has a before() hook that returns true for an admin. While
    // previewing, that must not fire — otherwise the menu shrinks but the
    // permissions do not, and the preview answers nothing.
    expect($admin->isActingAdmin())->toBeTrue();

    $this->actingAs($admin)->post(route('view-as.start'), ['role' => 'operator']);

    expect($admin->fresh()->isActingAdmin())->toBeFalse()
        // The real role is untouched — that is what lets them stop again.
        ->and($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('restores everything when stopped', function (): void {
    $admin = anAdmin();

    $this->actingAs($admin)->post(route('view-as.start'), ['role' => 'operator']);
    $this->actingAs($admin)->get(route('admin.users'))->assertForbidden();

    $this->actingAs($admin)
        ->post(route('view-as.stop'))
        ->assertRedirect(route('dashboard'));

    expect(ViewAs::active())->toBeFalse();

    $this->actingAs($admin)->get(route('admin.users'))->assertOk();
});

it('lets an administrator out again even mid-preview', function (): void {
    $admin = anAdmin();

    $this->actingAs($admin)->post(route('view-as.start'), ['role' => 'operator']);

    // The stop route is gated on the REAL admin role. Gating it on effective
    // permissions would trap them inside the preview.
    $this->actingAs($admin)->post(route('view-as.stop'))->assertRedirect(route('dashboard'));

    expect(ViewAs::active())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| It must not become an escalation route
|--------------------------------------------------------------------------
*/

it('refuses anyone who is not really an administrator', function (string $role): void {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->post(route('view-as.start'), ['role' => 'admin'])->assertForbidden();
    $this->actingAs($user)->post(route('view-as.stop'))->assertForbidden();

    expect(ViewAs::active())->toBeFalse();
})->with(['operator', 'supervisor', 'maintenance_manager', 'quality_assurance']);

it('refuses a role that is not on the list, including admin', function (string $role): void {
    $admin = anAdmin();

    $this->actingAs($admin)
        ->post(route('view-as.start'), ['role' => $role])
        ->assertSessionHasErrors('role');

    expect(ViewAs::active())->toBeFalse();
})->with([
    // `admin` is deliberately absent from selectableRoles() — stopping is how
    // you go back, and accepting it here would be a no-op that looks like a
    // privilege grant.
    'admin' => ['admin'],
    'made up' => ['superuser'],
    'empty' => [''],
]);

it('ignores a stale role left in the session', function (): void {
    $admin = anAdmin();

    // A hand-edited or outdated session value must not strand somebody with
    // no permissions and no way back.
    session([ViewAs::SESSION_KEY => 'wizard']);

    expect(ViewAs::active())->toBeFalse()
        ->and(ViewAs::role())->toBeNull();

    $this->actingAs($admin)->get(route('admin.users'))->assertOk();
});
