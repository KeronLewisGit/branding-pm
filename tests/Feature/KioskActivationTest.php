<?php

declare(strict_types=1);

use App\Enums\KioskDeviceKind;
use App\Http\Middleware\EnsureKioskDevice;
use App\Models\KioskDevice;
use App\Models\Machine;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Activating a kiosk from a machine's QR sticker
|--------------------------------------------------------------------------
| Every machine carries a printed sticker pointing at /m/{code}. On an
| enrolled tablet that opens the checklists; on anything else it used to be a
| 403 and a dead end. Now the same sticker walks you through setting the
| device up, and afterwards goes straight to that machine's checklists.
*/

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function kioskAdmin(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole('admin');

    return $user;
}

/*
|--------------------------------------------------------------------------
| The dead end, and the way out of it
|--------------------------------------------------------------------------
*/

it('offers to set the device up when an unenrolled device scans a sticker', function (): void {
    $machine = Machine::factory()->create(['code' => 'matan']);

    $this->get('/m/'.$machine->code)
        ->assertForbidden()
        ->assertSee(__('app.kiosk.activate.cta'))
        // Carrying the code is what lets the device land on this machine
        // rather than the grid once it is set up.
        ->assertSee(route('kiosk.activate', ['machine' => 'matan']), escape: false);
});

it('sends an unauthenticated scanner to log in, and back again afterwards', function (): void {
    $this->get('/kiosk/activate?machine=matan')->assertRedirect(route('login'));

    expect(session('url.intended'))->toContain('/kiosk/activate');
});

/*
|--------------------------------------------------------------------------
| Who may do it
|--------------------------------------------------------------------------
*/

it('lets a holder of kiosk.activate set a device up, and nobody else', function (string $role, bool $allowed): void {
    // A tablet gets dropped mid-shift and the supervisor fetches the spare;
    // waiting for an administrator to come to the floor is how a shift ends
    // up back on paper. Operators and QA officers still cannot: enrolment
    // permanently grants the machine grid and the PIN pad, so a photographed
    // sticker must not turn a personal phone into a kiosk.
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)->get('/kiosk/activate');

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'operator' => ['operator', false],
    'quality assurance' => ['quality_assurance', false],
    'supervisor' => ['supervisor', true],
    'maintenance manager' => ['maintenance_manager', true],
    'admin' => ['admin', true],
]);

it('does not hand the fleet screen to a supervisor along with it', function (): void {
    // The point of a separate permission. Setting up the tablet in your hand
    // must not also grant renaming, revoking and deleting devices.
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    expect($supervisor->can('kiosk.activate'))->toBeTrue()
        ->and($supervisor->can('kiosk.manage'))->toBeFalse();

    $this->actingAs($supervisor)->get('/admin/kiosk')->assertForbidden();
});

it('lets a supervisor complete the whole journey from the sticker', function (): void {
    $machine = Machine::factory()->create(['code' => 'matan']);
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    $this->actingAs($supervisor)
        ->post('/kiosk/activate', ['machine' => 'matan', 'name' => 'Spare tablet'])
        ->assertRedirect(route('kiosk.machine', ['code' => 'matan']));

    $device = KioskDevice::sole();

    expect($device->enrolled_by_id)->toBe($supervisor->id);

    // And the sticker works on it from then on.
    $this->withCookies([EnsureKioskDevice::COOKIE => $device->token])
        ->get('/m/'.$machine->code)
        ->assertOk();
});

it('refuses the POST to somebody without the permission', function (): void {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)
        ->post('/kiosk/activate', ['name' => 'Sneaky phone'])
        ->assertForbidden();

    expect(KioskDevice::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Activating
|--------------------------------------------------------------------------
*/

it('creates the device and lands on the machine that was scanned', function (): void {
    $machine = Machine::factory()->create(['code' => 'matan', 'name' => 'MATAN Guillotine']);

    $this->actingAs(kioskAdmin())
        ->post('/kiosk/activate', [
            'machine' => 'matan',
            'name' => 'iPad at the MATAN',
        ])
        // The whole point of arriving this way.
        ->assertRedirect(route('kiosk.machine', ['code' => 'matan']))
        ->assertCookie(EnsureKioskDevice::COOKIE);

    $device = KioskDevice::sole();

    expect($device->name)->toBe('iPad at the MATAN')
        // Inherited from the machine — the best available guess at where this
        // device is about to live.
        ->and($device->location_id)->toBe($machine->location_id)
        ->and($device->is_active)->toBeTrue();
});

it('makes the same sticker open the checklists from then on', function (): void {
    $machine = Machine::factory()->create(['code' => 'matan']);
    $admin = kioskAdmin();

    $this->actingAs($admin)->post('/kiosk/activate', [
        'machine' => 'matan',
        'name' => 'Shop floor tablet',
    ]);

    $token = KioskDevice::sole()->token;

    // A fresh visit carrying only the device cookie — no logged-in user, which
    // is how the tablet will actually be used.
    //
    // Plaintext, via withCookies(): it encrypts what it is given, matching
    // what EncryptCookies expects. `withUnencryptedCookie()` fails decryption
    // and is silently dropped, producing a 403 that reads as a middleware bug
    // rather than a test one. See the note on kioskCookie() in KioskTest.
    $this->withCookies([EnsureKioskDevice::COOKIE => $token])
        ->get('/m/'.$machine->code)
        ->assertOk()
        ->assertDontSee(__('app.kiosk.activate.cta'));
});

it('records who set it up, when, and what the hardware said it was', function (): void {
    Machine::factory()->create(['code' => 'matan']);
    $admin = kioskAdmin(['full_name' => 'Kerry Ann Baptiste']);

    $this->actingAs($admin)
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1',
            'Sec-CH-UA-Platform' => '"iPadOS"',
        ])
        ->post('/kiosk/activate', [
            'machine' => 'matan',
            'name' => 'iPad at the MATAN',
            'device' => [
                'screen' => '2360 x 1640',
                'touch_points' => '5',
                'timezone' => 'America/Port_of_Spain',
            ],
        ]);

    $device = KioskDevice::sole();

    expect($device->enrolled_by_id)->toBe($admin->id)
        ->and($device->enrolled_at)->not->toBeNull()
        ->and($device->kind)->toBe(KioskDeviceKind::Tablet)
        ->and($device->device_info)->toMatchArray([
            'screen' => '2360 x 1640',
            'touch_points' => '5',
            'detected_type' => 'tablet',
            'platform_hint' => 'iPadOS',
        ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'kiosk.device_enrolled',
        'subject_id' => $device->id,
        'causer_id' => $admin->id,
    ]);
});

it('can take over an existing device rather than leaving a dead row behind', function (): void {
    // Replacing a broken tablet: the new one inherits the name, place and
    // history of the one it replaces.
    Machine::factory()->create(['code' => 'matan']);
    $existing = KioskDevice::factory()->create(['name' => 'Guillotine tablet']);

    $this->actingAs(kioskAdmin())->post('/kiosk/activate', [
        'machine' => 'matan',
        'device_id' => $existing->id,
        // Deliberately different: choosing an existing device must not rename
        // it from a field the person never looked at.
        'name' => 'Something else entirely',
    ])->assertRedirect(route('kiosk.machine', ['code' => 'matan']));

    expect(KioskDevice::count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Guillotine tablet')
        ->and($existing->fresh()->enrolled_at)->not->toBeNull();
});

it('will not activate a device that has been deactivated', function (): void {
    Machine::factory()->create(['code' => 'matan']);
    $device = KioskDevice::factory()->create(['is_active' => false]);

    $this->actingAs(kioskAdmin())
        ->post('/kiosk/activate', ['machine' => 'matan', 'device_id' => $device->id])
        ->assertSessionHas('error')
        ->assertCookieMissing(EnsureKioskDevice::COOKIE);
});

it('still activates when the scanned code matches no machine', function (): void {
    // A peeled or out-of-date sticker is not a reason to refuse to set up the
    // tablet somebody is standing there holding.
    $this->actingAs(kioskAdmin())
        ->post('/kiosk/activate', ['machine' => 'no-such-machine', 'name' => 'Spare tablet'])
        ->assertRedirect(route('kiosk.home'));

    expect(KioskDevice::sole()->name)->toBe('Spare tablet');
});

it('requires a name when no existing device was chosen', function (): void {
    $this->actingAs(kioskAdmin())
        ->post('/kiosk/activate', ['machine' => 'matan'])
        ->assertSessionHasErrors('name');

    expect(KioskDevice::count())->toBe(0);
});

it('does not let the machine parameter drive the redirect', function (): void {
    // The code is resolved against the machines table and the route rebuilt
    // from what comes back, so nothing client-supplied reaches the redirect.
    $this->actingAs(kioskAdmin())
        ->post('/kiosk/activate', [
            'machine' => 'https://evil.example.com/phish',
            'name' => 'Tablet',
        ])
        ->assertRedirect(route('kiosk.home'));
});

/*
|--------------------------------------------------------------------------
| The QR codes themselves
|--------------------------------------------------------------------------
*/

it('points every machine sticker at that machine', function (): void {
    // The stickers already existed; this is what makes the journey above
    // reachable at all, so it is guarded.
    $machine = Machine::factory()->create(['code' => 'matan']);

    expect(route('kiosk.machine', ['code' => $machine->code]))->toEndWith('/m/matan');
});
