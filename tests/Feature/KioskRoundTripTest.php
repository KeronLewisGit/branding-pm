<?php

declare(strict_types=1);

use App\Enums\RunItemStatus;
use App\Enums\RunStatus;
use App\Http\Middleware\EnsureKioskDevice;
use App\Livewire\Runs\RunForm;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\ChecklistTemplate;
use App\Models\KioskDevice;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use App\Support\SignatureImage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Going into kiosk mode and back out again
|--------------------------------------------------------------------------
| Once the office nav grew a way into the kiosk, a third kind of session
| existed that the kiosk was never written for: a password login on an
| enrolled browser. It holds the device cookie but none of the kiosk.* session
| keys the PIN pad writes, and code testing for those keys quietly treated it
| as an office user sitting at a desk.
*/

function roundTripSignature(): string
{
    $png = hex2bin(
        '89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de'
        .'0000000c4944415408d763f8ffff3f0005fe02fea735e8b20000000049454e44ae426082'
    );

    return 'data:image/png;base64,'.base64_encode((string) $png);
}

function roundTripUser(string $role = 'operator'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function roundTripKiosk(): array
{
    $device = KioskDevice::create([
        'name' => 'Round trip tablet',
        'token' => Str::random(64),
        'is_active' => true,
    ]);

    return [EnsureKioskDevice::COOKIE => $device->token];
}

/**
 * A run, plus a user who is actually allowed to open it — MachineScope gates
 * runs.show, so a user with no machine and no site gets a 403 that has
 * nothing to do with what is being tested here.
 *
 * @return array{0: ChecklistRun, 1: User}
 */
function roundTripRunFor(string $role = 'operator'): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create(['code' => 'matan', 'name' => 'MATAN']);
    $template = ChecklistTemplate::factory()->for($machine)->create(['is_active' => true]);

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'scheduled_for' => now()->toDateString(),
        'status' => RunStatus::Pending,
    ]);

    $user = User::factory()->create(['default_site_id' => $site->id]);
    $user->assignRole($role);
    $user->machines()->attach($machine->id);

    return [$run, $user];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets a password session into the kiosk without a PIN', function (): void {
    $this->actingAs(roundTripUser())
        ->withCookies(roundTripKiosk())
        ->get(route('kiosk.home'))
        ->assertOk();
});

it('offers a password session a way back to the office, and does not log it out', function (): void {
    // They came in from the office and expect to go back to it. Logging them
    // out would be a strange punishment for looking at the kiosk.
    $this->actingAs(roundTripUser())
        ->withCookies(roundTripKiosk())
        ->get(route('kiosk.home'))
        ->assertOk()
        ->assertSee(__('app.kiosk.leave'))
        ->assertSee(route('runs.index'))
        ->assertDontSee(__('app.kiosk.finish'));
});

it('offers a PIN session the hand-back button instead', function (): void {
    // A PIN session belongs to whoever tapped their name on a shared tablet,
    // so finishing means handing the tablet on — not returning to an office
    // they were never in. Nothing but the idle timer used to do this.
    $this->actingAs(roundTripUser())
        ->withCookies(roundTripKiosk())
        ->withSession(['kiosk.authenticated_at' => now()->timestamp])
        ->get(route('kiosk.home'))
        ->assertOk()
        ->assertSee(__('app.kiosk.finish'))
        ->assertDontSee(__('app.kiosk.leave'));
});

it('shows nobody an exit when nobody is signed in', function (): void {
    $this->withCookies(roundTripKiosk())
        ->get(route('kiosk.home'))
        ->assertOk()
        ->assertDontSee(__('app.kiosk.finish'))
        ->assertDontSee(__('app.kiosk.leave'));
});

it('keeps the browser enrolled after a PIN session is handed back', function (): void {
    // Releasing ends the session, never the enrolment: the next operator must
    // find a working kiosk, not a "this tablet is not enrolled" screen.
    $cookies = roundTripKiosk();

    $this->actingAs(roundTripUser())
        ->withCookies($cookies)
        ->withSession(['kiosk.authenticated_at' => now()->timestamp])
        ->post(route('kiosk.release'))
        ->assertRedirect(route('kiosk.home'));

    // The proof that matters: the same browser can still reach the kiosk.
    $this->withCookies($cookies)
        ->get(route('kiosk.home'))
        ->assertOk();
});

it('gives the run sheet a way back to the machine on a kiosk', function (): void {
    // The sheet had no way out at all. On a tablet run without browser chrome,
    // browser-back is not an escape hatch that exists.
    [$run, $operator] = roundTripRunFor();

    $this->actingAs($operator)
        ->withCookies(roundTripKiosk())
        ->get(route('runs.show', $run))
        ->assertOk()
        ->assertSee(route('kiosk.machine', ['code' => 'matan']));
});

it('sends an office user back to the runs list, not to a machine screen', function (): void {
    // An office user has no device cookie, so a machine screen would tell them
    // their tablet is not enrolled — for a link they did not ask for.
    [$run, $supervisor] = roundTripRunFor('supervisor');

    $this->actingAs($supervisor)
        ->get(route('runs.show', $run))
        ->assertOk()
        ->assertSee(__('app.runs.back_to_runs'))
        ->assertDontSee(route('kiosk.machine', ['code' => 'matan']));
});

it('returns a kiosk operator to the machine after submitting, not to the office', function (): void {
    /*
     * The regression. The post-submit redirect used to test
     * session('kiosk.device_id'), which only the PIN pad writes — so it asked
     * "did this person use the PIN pad", when the question is "is this browser
     * a kiosk". An operator already signed in with a password never touches
     * the PIN pad, so submitting a sheet threw them out of the kiosk and into
     * the office runs list, mid-shift, on a tablet.
     */
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();
    $machine = Machine::factory()->for($location)->create(['code' => 'matan']);
    $template = ChecklistTemplate::factory()->for($machine)->create();

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::InProgress,
    ]);

    ChecklistRunItem::factory()->count(2)->create([
        'checklist_run_id' => $run->id,
        'status' => RunItemStatus::Done,
        'completed_at' => now(),
    ]);

    $operator = User::factory()->operator()->withPin()->create(['default_site_id' => $site->id]);
    $operator->machines()->attach($machine->id);

    Storage::fake(SignatureImage::diskName());

    // No kiosk.* session keys anywhere here: a password login that walked in
    // through the office nav, on an enrolled browser.
    Livewire::actingAs($operator)
        ->withCookies(roundTripKiosk())
        ->test(RunForm::class, ['run' => $run->fresh()])
        ->call('submit', roundTripSignature(), '1234')
        ->assertHasNoErrors()
        ->assertRedirect(route('kiosk.machine', ['code' => 'matan']));
});
