<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureKioskDevice;
use App\Livewire\Admin\KioskDeviceManager;
use App\Models\KioskDevice;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| The kiosk: the QR deep link, and getting a tablet enrolled in the first
| place
|--------------------------------------------------------------------------
| Both of these were broken or missing in the first eight milestones. The
| deep link handed mount() a Machine model where it wanted the raw slug, and
| there was no way at all to create a kiosk device except by hand.
*/

function kioskDevice(array $attributes = []): KioskDevice
{
    return KioskDevice::create(array_merge([
        'name' => 'Test tablet',
        'token' => Str::random(64),
        'is_active' => true,
    ], $attributes));
}

function aMachine(array $attributes = []): Machine
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();

    return Machine::factory()->for($location)->create($attributes);
}

/**
 * The `kiosk_device` cookie of an enrolled tablet.
 *
 * Plaintext on purpose: `withCookies()` encrypts what it is given and adds
 * the name-bound value prefix, matching what EncryptCookies expects to
 * decrypt. Passing an already-encrypted value here encrypts it twice and the
 * request arrives looking unenrolled; `withUnencryptedCookie()` fails
 * decryption and is dropped. Both produce a 403 that looks like a bug in the
 * middleware rather than a bug in the test.
 *
 * @return array<string, string>
 */
function kioskCookie(KioskDevice $device): array
{
    return [EnsureKioskDevice::COOKIE => $device->token];
}

/*
|--------------------------------------------------------------------------
| /m/{code} — the QR sticker deep link
|--------------------------------------------------------------------------
*/

it('opens the machine page from the code on the sticker', function (): void {
    $device = kioskDevice();
    $machine = aMachine(['code' => 'esko-c64-kongsberg', 'name' => 'ESKO C64 Kongsberg']);

    $this->withCookies(kioskCookie($device))
        ->get('/m/'.$machine->code)
        ->assertOk()
        ->assertSee('ESKO C64 Kongsberg')
        // The regression: the route parameter used to be named {machine},
        // which Livewire's ImplicitRouteBinding matched against the public
        // ?Machine $machine property. mount() is typed string, so the model
        // was coerced through Model::__toString() and the component looked
        // up a machine whose code was a blob of JSON.
        ->assertDontSee('"id":', escape: false);
});

it('explains an unknown sticker instead of returning a bare 404', function (): void {
    $device = kioskDevice();
    aMachine(['code' => 'matan']);

    // A peeled, smudged or out-of-date sticker must reach a screen an
    // operator can act on. Implicit model binding would 404 here.
    $this->withCookies(kioskCookie($device))
        ->get('/m/no-such-machine')
        ->assertOk()
        ->assertSee(__('app.kiosk.machine_unknown'));
});

it('refuses the machine page to a tablet that is not enrolled', function (): void {
    $machine = aMachine();

    $this->get('/m/'.$machine->code)->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Enrolment by temporary signed link
|--------------------------------------------------------------------------
*/

it('enrols a tablet from a signed link with nobody logged in', function (): void {
    $device = kioskDevice();
    aMachine();

    $url = URL::temporarySignedRoute(
        'kiosk.enrol.link',
        now()->addMinutes(KioskDeviceManager::LINK_TTL_MINUTES),
        ['device' => $device->id],
    );

    // No acting-as: the whole point is that no admin password is typed on
    // the tablet.
    $this->get($url)
        ->assertRedirect(route('kiosk.home'))
        ->assertCookie(EnsureKioskDevice::COOKIE, $device->token);
});

it('rejects an expired or tampered enrolment link', function (): void {
    $device = kioskDevice();

    $expired = URL::temporarySignedRoute('kiosk.enrol.link', now()->subMinute(), ['device' => $device->id]);
    $this->get($expired)->assertForbidden();

    $valid = URL::temporarySignedRoute('kiosk.enrol.link', now()->addMinutes(15), ['device' => $device->id]);
    $this->get($valid.'deadbeef')->assertForbidden();
});

it('will not enrol a deactivated tablet', function (): void {
    $device = kioskDevice(['is_active' => false]);

    $url = URL::temporarySignedRoute('kiosk.enrol.link', now()->addMinutes(15), ['device' => $device->id]);

    $this->get($url)
        ->assertRedirect(route('login'))
        ->assertCookieMissing(EnsureKioskDevice::COOKIE);
});

it('locks out an enrolled tablet the moment its token is rotated', function (): void {
    $device = kioskDevice();
    aMachine();

    // Captured before the rotation — this is the cookie already sitting on
    // the tablet out on the floor.
    $oldCookie = kioskCookie($device);

    $this->withCookies($oldCookie)
        ->get('/kiosk')
        ->assertOk();

    // "Un-enrol" on the admin screen — the lost-tablet button.
    $device->update(['token' => Str::random(64)]);

    $this->withCookies($oldCookie)
        ->get('/kiosk')
        ->assertForbidden();
});

it('locks out an enrolled tablet the moment it is deactivated', function (): void {
    $device = kioskDevice();
    aMachine();

    // Enrolled while active, then switched off from the admin screen.
    $cookie = kioskCookie($device);

    $device->update(['is_active' => false]);

    $this->withCookies($cookie)
        ->get('/kiosk')
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| The admin screen
|--------------------------------------------------------------------------
*/

it('keeps the tablet admin screen to holders of kiosk.manage', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)->get('/admin/kiosk')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/kiosk')->assertOk();
});

it('creates a tablet with a token nobody had to invent', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->set('name', 'Digital Print — wall tablet')
        ->set('isActive', true)
        ->call('save')
        ->assertHasNoErrors();

    $device = KioskDevice::query()->firstWhere('name', 'Digital Print — wall tablet');

    expect($device)->not->toBeNull()
        ->and($device->token)->toHaveLength(64)
        ->and($device->is_active)->toBeTrue();
});

it('shows a working enrolment link and renders its QR', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $device = kioskDevice();
    aMachine();

    $component = Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->call('openEnrolModal', $device->id);

    $url = $component->get('enrolUrl');

    expect($url)->toContain('/kiosk/link/'.$device->id)
        ->and($url)->toContain('signature=')
        // Generated on the admin's screen, then actually followed by a
        // tablet — the whole point, so walk it end to end.
        ->and($component->instance()->enrolSvg())->toContain('<svg');

    $this->get($url)->assertRedirect(route('kiosk.home'));
});

it('refuses to hand out an enrolment link for a deactivated tablet', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $device = kioskDevice(['is_active' => false]);

    $component = Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->call('openEnrolModal', $device->id);

    expect($component->get('enrolUrl'))->toBe('');
});

it('rotates the token when a tablet is un-enrolled', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $device = kioskDevice();
    $before = $device->token;

    Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->call('confirmRevoke', $device->id)
        ->call('revokeEnrolment');

    expect($device->fresh()->token)->not->toBe($before);
});
