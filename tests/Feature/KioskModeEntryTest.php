<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureKioskDevice;
use App\Models\KioskDevice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Getting into kiosk mode from the office UI
|--------------------------------------------------------------------------
| Before this there was no link to the kiosk anywhere in the application.
| You either scanned a machine's QR sticker or typed the URL, which meant an
| operator sitting at an enrolled browser had no way to reach the thing the
| browser was enrolled for.
*/

function kioskModeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * Where a password login actually lands for this role.
 *
 * Dashboard::mount() redirects anyone without `report.view` to the runs list,
 * so an operator never sees /dashboard and asserting against it only ever
 * tests the redirect.
 */
function officeLandingRoute(User $user): string
{
    return $user->can('report.view') ? route('dashboard') : route('runs.index');
}

function enrolledBrowser(): array
{
    $device = KioskDevice::create([
        'name' => 'Enrolled browser',
        'token' => Str::random(64),
        'is_active' => true,
    ]);

    return [EnsureKioskDevice::COOKIE => $device->token];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('offers an operator on an enrolled browser a way straight into the kiosk', function (): void {
    $this->actingAs($u = kioskModeUser('operator'))
        ->withCookies(enrolledBrowser())
        ->get(officeLandingRoute($u))
        ->assertOk()
        ->assertSee(__('app.nav.kiosk_mode'))
        ->assertSee(route('kiosk.home'));

    // Icon-only, so the accessible name is the only name it has.
    $this->actingAs($u)
        ->withCookies(enrolledBrowser())
        ->get(officeLandingRoute($u))
        ->assertSee('aria-label="'.__('app.nav.kiosk_mode').'"', escape: false);
});

it('hides the entry from an operator whose browser is not a kiosk', function (): void {
    // An operator holds no `kiosk.activate`, so the only thing this entry
    // could offer them is a 403. A dead menu item teaches people to distrust
    // the menu.
    $this->actingAs($u = kioskModeUser('operator'))
        ->get(officeLandingRoute($u))
        ->assertOk()
        ->assertDontSee(__('app.nav.kiosk_mode'))
        ->assertDontSee(__('app.nav.kiosk_mode_setup'));
});

it('offers a supervisor on an unenrolled browser the set-up screen instead', function (): void {
    $this->actingAs($u = kioskModeUser('supervisor'))
        ->get(officeLandingRoute($u))
        ->assertOk()
        ->assertSee(__('app.nav.kiosk_mode_setup'))
        ->assertSee(route('kiosk.activate'));
});

it('sends a supervisor on an already enrolled browser into the kiosk, not back to set-up', function (): void {
    // Enrolling a browser that is already a kiosk is the wrong offer, and the
    // one most likely to be taken by mistake.
    $this->actingAs($u = kioskModeUser('supervisor'))
        ->withCookies(enrolledBrowser())
        ->get(officeLandingRoute($u))
        ->assertOk()
        ->assertSee(__('app.nav.kiosk_mode'))
        ->assertDontSee(__('app.nav.kiosk_mode_setup'));
});

it('does not count a deactivated device as an enrolled browser', function (): void {
    // Same rule the middleware applies: a revoked tablet is not a kiosk, so
    // the nav must not send anyone into a screen that will refuse them.
    $device = KioskDevice::create([
        'name' => 'Retired tablet',
        'token' => Str::random(64),
        'is_active' => false,
    ]);

    $this->actingAs($u = kioskModeUser('operator'))
        ->withCookies([EnsureKioskDevice::COOKIE => $device->token])
        ->get(officeLandingRoute($u))
        ->assertOk()
        ->assertDontSee(__('app.nav.kiosk_mode'));
});

it('resolves an enrolled browser outside the kiosk middleware', function (): void {
    // The regression this guards: enrolledDevice() used to be device(), which
    // reads an attribute the `kiosk` middleware sets. Office pages run `auth`
    // and never `kiosk`, so it reported "not enrolled" on every browser and
    // the entry never appeared.
    $this->actingAs($u = kioskModeUser('operator'))
        ->withCookies(enrolledBrowser())
        ->get(officeLandingRoute($u))
        ->assertOk()
        ->assertSee(route('kiosk.home'));
});
