<?php

declare(strict_types=1);

use App\Enums\KioskDeviceKind;
use App\Http\Middleware\EnsureKioskDevice;
use App\Livewire\Admin\KioskDeviceManager;
use App\Models\KioskDevice;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use App\Support\DeviceType;
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
| "Not set up as a kiosk" — worded for whatever is asking
|--------------------------------------------------------------------------
*/

it('names the right kind of device on the not-enrolled screen', function (string $userAgent, DeviceType $expected): void {
    expect(DeviceType::detect($userAgent))->toBe($expected);

    $this->withHeader('User-Agent', $userAgent)
        ->get('/kiosk')
        ->assertForbidden()
        ->assertSee(__('app.kiosk.not_enrolled.title.'.$expected->value));
})->with([
    'windows laptop' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36', DeviceType::Computer],
    'ipad, classic UA' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', DeviceType::Tablet],
    'android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36', DeviceType::Tablet],
    'android phone' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36', DeviceType::Phone],
    'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', DeviceType::Phone],
]);

it('falls back to the neutral wording when the User-Agent says nothing', function (): void {
    expect(DeviceType::detect(''))->toBe(DeviceType::Unknown)
        ->and(DeviceType::detect(null))->toBe(DeviceType::Unknown);

    $this->withHeader('User-Agent', '')
        ->get('/kiosk')
        ->assertForbidden()
        ->assertSee(__('app.kiosk.not_enrolled.title.unknown'));
});

it('lets the browser correct an iPad that claims to be a Mac', function (): void {
    // Safari on iPad has sent a Macintosh User-Agent by default since
    // iPadOS 13 — identical to a MacBook's. The server renders the computer
    // wording and ships a script that swaps it when the browser reports
    // touch points.
    $mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

    $this->withHeader('User-Agent', $mac)
        ->get('/kiosk')
        ->assertForbidden()
        ->assertSee(__('app.kiosk.not_enrolled.title.computer'))
        ->assertSee('maxTouchPoints', escape: false)
        ->assertSee(__('app.kiosk.not_enrolled.title.tablet'));

    // A Windows touchscreen laptop also reports touch points, and calling it
    // a tablet would be worse than calling it a computer — so it gets no
    // correction script at all.
    $windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    $this->withHeader('User-Agent', $windows)
        ->get('/kiosk')
        ->assertForbidden()
        ->assertDontSee('maxTouchPoints', escape: false);
});

it('treats the device type as wording only, never as access', function (): void {
    $device = kioskDevice();
    aMachine();

    // A phone with a valid device cookie is still a kiosk. The User-Agent is
    // client-controlled and must not gate anything.
    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1')
        ->withCookies(kioskCookie($device))
        ->get('/kiosk')
        ->assertOk();
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
        ->call('openEnrolModal', $device->id)
        // The laptop-is-its-own-kiosk escape hatch: you cannot scan a QR
        // with the screen showing it.
        ->assertSee(__('app.kiosk_devices.enrol_here'));

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

it('enrols the current browser directly, for a laptop acting as its own kiosk', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $device = kioskDevice();
    aMachine();

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    // Turning a browser into a kiosk is an admin act, not an operator one.
    $this->actingAs($operator)->get(route('kiosk.enrol', ['device' => $device->id]))
        ->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('kiosk.enrol', ['device' => $device->id]))
        ->assertRedirect(route('kiosk.home'))
        ->assertCookie(EnsureKioskDevice::COOKIE, $device->token);
});

it('gives the browser the same idle timeout the server enforces', function (): void {
    $device = kioskDevice();
    aMachine();

    // The layout used to hardcode 120, so raising this moved the server's
    // deadline while the browser went on dropping the operator at two
    // minutes. Same number in both halves or the setting is a lie.
    config(['checklists.kiosk_idle_seconds' => 600]);

    $this->withCookies(kioskCookie($device))
        ->get('/kiosk')
        ->assertOk()
        ->assertSee('idleRelease(600', escape: false);
});

/*
|--------------------------------------------------------------------------
| Device kinds — a kiosk is not always a tablet
|--------------------------------------------------------------------------
*/

it('defaults a new device to a tablet and records the kind chosen', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $component = Livewire::actingAs($admin)->test(KioskDeviceManager::class);

    expect($component->get('kind'))->toBe('tablet');

    $component->set('name', 'Bench laptop')
        ->set('kind', 'laptop')
        ->call('save')
        ->assertHasNoErrors();

    expect(KioskDevice::query()->firstWhere('name', 'Bench laptop')->kind)
        ->toBe(KioskDeviceKind::Laptop);
});

it('rejects a device kind that is not one of the offered options', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->set('name', 'Something odd')
        ->set('kind', 'toaster')
        ->call('save')
        ->assertHasErrors('kind');
});

it('leads with the enrolment method that suits the device', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // A laptop cannot scan a code displayed on its own screen, so the
    // browser route comes first — and the reverse for a tablet.
    $laptop = kioskDevice(['name' => 'Bench laptop', 'kind' => 'laptop']);

    Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->call('openEnrolModal', $laptop->id)
        ->assertSeeInOrder([
            __('app.kiosk_devices.enrol_browser_title_primary'),
            __('app.kiosk_devices.enrol_scan_title_secondary'),
        ]);

    $tablet = kioskDevice(['name' => 'Floor tablet', 'kind' => 'tablet']);

    Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->call('openEnrolModal', $tablet->id)
        ->assertSeeInOrder([
            __('app.kiosk_devices.enrol_scan_title_primary'),
            __('app.kiosk_devices.enrol_browser_title_secondary'),
        ]);
});

it('offers both enrolment methods whatever the kind', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Guessing the kind wrong must cost a scroll, not a dead end.
    foreach (KioskDeviceKind::cases() as $kind) {
        $device = kioskDevice(['name' => 'Device '.$kind->value, 'kind' => $kind->value]);

        Livewire::actingAs($admin)
            ->test(KioskDeviceManager::class)
            ->call('openEnrolModal', $device->id)
            ->assertSee(__('app.kiosk_devices.enrol_here'))
            ->assertSee(__('app.kiosk_devices.enrol_url_label'));
    }
});

it('records what the device actually is, and flags a mismatch', function (): void {
    $device = kioskDevice(['kind' => 'tablet']);
    aMachine();

    $laptopUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    $this->withHeader('User-Agent', $laptopUa)
        ->withCookies(kioskCookie($device))
        ->get('/kiosk')
        ->assertOk();

    $device->refresh();

    expect($device->last_user_agent)->toBe($laptopUa)
        ->and($device->detectedType())->toBe(DeviceType::Computer)
        // Declared a tablet, driven from a computer — worth showing an admin.
        ->and($device->kindLooksWrong())->toBeTrue();
});

it('does not cry mismatch when the device is what it says it is', function (): void {
    $device = kioskDevice(['kind' => 'laptop']);
    aMachine();

    expect($device->kindLooksWrong())->toBeFalse(); // never used yet

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36')
        ->withCookies(kioskCookie($device))
        ->get('/kiosk')
        ->assertOk();

    expect($device->fresh()->kindLooksWrong())->toBeFalse();
});

it('never accuses an unrecognised or unclassified device', function (): void {
    expect(KioskDeviceKind::Other->matches(DeviceType::Computer))->toBeTrue()
        ->and(KioskDeviceKind::Other->matches(DeviceType::Tablet))->toBeTrue()
        ->and(KioskDeviceKind::Tablet->matches(DeviceType::Unknown))->toBeTrue()
        ->and(KioskDeviceKind::Desktop->matches(DeviceType::Computer))->toBeTrue()
        ->and(KioskDeviceKind::Tablet->matches(DeviceType::Computer))->toBeFalse();
});

it('filters the device list by kind', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    kioskDevice(['name' => 'Floor tablet', 'kind' => 'tablet']);
    kioskDevice(['name' => 'Bench laptop', 'kind' => 'laptop']);

    Livewire::actingAs($admin)
        ->test(KioskDeviceManager::class)
        ->set('kindFilter', 'laptop')
        ->assertSee('Bench laptop')
        ->assertDontSee('Floor tablet');
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
