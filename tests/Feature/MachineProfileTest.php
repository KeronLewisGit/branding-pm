<?php

declare(strict_types=1);

use App\Enums\RunStatus;
use App\Livewire\Machines\MachineProfile;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunPart;
use App\Models\ChecklistTemplate;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Machine;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Machine profile — SPEC screen 5
|--------------------------------------------------------------------------
| Everything on it was already reachable, but only by visiting three screens
| and filtering each one. The test that matters is that it is scoped like
| every other machine view, not that the panels render.
*/

/**
 * @return array{0: Machine, 1: Site}
 */
function profileMachine(string $name = 'MATAN'): array
{
    $site = Site::factory()->create();
    $location = Location::factory()->for($site)->create();

    return [Machine::factory()->for($location)->create(['name' => $name]), $site];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('is reachable by anyone who may view the machine, not just managers', function (string $role): void {
    [$machine, $site] = profileMachine();

    $user = User::factory()->create(['default_site_id' => $site->id]);
    $user->assignRole($role);

    // The people who need a machine's history are the ones standing next to
    // it — this is deliberately not an admin-only screen.
    $this->actingAs($user)
        ->get(route('machines.show', ['machine' => $machine->code]))
        ->assertOk()
        ->assertSee('MATAN');
})->with(['operator', 'supervisor', 'maintenance_manager', 'admin']);

it('refuses a machine at another site', function (): void {
    [, $site] = profileMachine();
    [$theirs] = profileMachine('Someone else’s press');

    $operator = User::factory()->create(['default_site_id' => $site->id]);
    $operator->assignRole('operator');

    $this->actingAs($operator)
        ->get(route('machines.show', ['machine' => $theirs->code]))
        ->assertForbidden();
});

it('404s on an unknown code rather than explaining itself', function (): void {
    [, $site] = profileMachine();

    $manager = User::factory()->create(['default_site_id' => $site->id]);
    $manager->assignRole('maintenance_manager');

    // Unlike the kiosk's /m/{code}, this is an office screen reached from a
    // list — there is no peeling sticker to account for.
    $this->actingAs($manager)
        ->get('/machines/no-such-machine')
        ->assertNotFound();
});

it('gathers the checklists, runs, faults and parts for the machine', function (): void {
    [$machine, $site] = profileMachine();

    $template = ChecklistTemplate::factory()->for($machine)->create(['name' => 'MATAN — Daily Maintenance']);

    $run = ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::Approved,
        'scheduled_for' => now()->subDays(2)->toDateString(),
    ]);

    // No factory for this one — created directly so the snapshot columns are
    // exactly what the profile groups on.
    ChecklistRunPart::create([
        'checklist_run_id' => $run->id,
        'part_id' => null,
        'part_code_snapshot' => '23',
        'part_name_snapshot' => 'Isopropyl alcohol',
        'sort_order' => 0,
        'qty_used' => 3,
    ]);

    Issue::factory()->create([
        'machine_id' => $machine->id,
        'description' => 'Vacuum table losing suction',
    ]);

    $manager = User::factory()->create(['default_site_id' => $site->id]);
    $manager->assignRole('maintenance_manager');

    Livewire::actingAs($manager)
        ->test(MachineProfile::class, ['machine' => $machine])
        ->assertSee('MATAN — Daily Maintenance')
        ->assertSee('Vacuum table losing suction')
        ->assertSee('Isopropyl alcohol');
});

it('counts a window and refuses a hand-edited one', function (): void {
    [$machine, $site] = profileMachine();

    $template = ChecklistTemplate::factory()->for($machine)->create();

    ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::Approved,
        'scheduled_for' => now()->subDays(5)->toDateString(),
    ]);

    ChecklistRun::factory()->create([
        'checklist_template_id' => $template->id,
        'machine_id' => $machine->id,
        'status' => RunStatus::Missed,
        // Outside a 30-day window, inside a 90-day one.
        'scheduled_for' => now()->subDays(60)->toDateString(),
        'shift' => 'night',
    ]);

    $manager = User::factory()->create(['default_site_id' => $site->id]);
    $manager->assignRole('maintenance_manager');

    $component = Livewire::actingAs($manager)->test(MachineProfile::class, ['machine' => $machine]);

    expect($component->instance()->runStats())->toMatchArray(['completed' => 1, 'missed' => 0]);

    $component->call('setWindow', 90);

    expect($component->instance()->runStats())->toMatchArray(['completed' => 1, 'missed' => 1]);

    // A window that is not one of the offered ones falls back rather than
    // reaching the database with whatever was in the query string.
    $component->call('setWindow', 9999);

    expect($component->get('days'))->toBe(30);
});

it('shows no percentage when nothing was due', function (): void {
    [$machine, $site] = profileMachine();

    $manager = User::factory()->create(['default_site_id' => $site->id]);
    $manager->assignRole('maintenance_manager');

    // 0% and 100% would both be lies, matching the Compliance definition.
    Livewire::actingAs($manager)
        ->test(MachineProfile::class, ['machine' => $machine])
        ->assertSee('—');
});

/*
|--------------------------------------------------------------------------
| The sidebar, per role
|--------------------------------------------------------------------------
*/

it('shows an operator only the two screens they can use', function (): void {
    [, $site] = profileMachine();

    $operator = User::factory()->create(['default_site_id' => $site->id]);
    $operator->assignRole('operator');

    $response = $this->actingAs($operator)->get(route('runs.index'));

    $response->assertOk()
        ->assertSee(__('app.nav.runs'))
        ->assertSee(__('app.nav.issues'))
        // The Dashboard redirects anyone without `report.view` straight to
        // /runs, so linking an operator to it sent them back where they were.
        ->assertDontSee(__('app.nav.dashboard'))
        ->assertDontSee(__('app.nav.reports'))
        ->assertDontSee(__('app.nav.group_plant'))
        ->assertDontSee(__('app.nav.group_system'));
});

it('gives a supervisor the work screens but nothing to administer', function (): void {
    [, $site] = profileMachine();

    $supervisor = User::factory()->create(['default_site_id' => $site->id]);
    $supervisor->assignRole('supervisor');

    $this->actingAs($supervisor)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('app.nav.dashboard'))
        ->assertSee(__('app.nav.approvals'))
        ->assertSee(__('app.nav.reports'))
        ->assertDontSee(__('app.nav.group_plant'))
        ->assertDontSee(__('app.nav.group_system'));
});

it('gives a manager the plant but not the system', function (): void {
    [, $site] = profileMachine();

    $manager = User::factory()->create(['default_site_id' => $site->id]);
    $manager->assignRole('maintenance_manager');

    $this->actingAs($manager)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('app.nav.group_plant'))
        ->assertSee(__('app.nav.machines'))
        ->assertDontSee(__('app.nav.group_system'))
        ->assertDontSee(__('app.nav.users'));
});

it('gives an administrator the system group', function (): void {
    [, $site] = profileMachine();

    $admin = User::factory()->create(['default_site_id' => $site->id]);
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('app.nav.group_plant'))
        ->assertSee(__('app.nav.group_system'))
        ->assertSee(__('app.nav.kiosk_devices'))
        ->assertSee(__('app.nav.users'));
});
