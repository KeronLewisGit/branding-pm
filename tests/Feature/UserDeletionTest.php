<?php

declare(strict_types=1);

use App\Livewire\Admin\UserManager;
use App\Models\ChecklistRun;
use App\Models\User;
use App\Support\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Deleting a user
|--------------------------------------------------------------------------
| Two outcomes, and which one applies is not a preference — it is decided by
| whether the maintenance record names the person. Runs, signatures and issues
| reference users with `nullOnDelete`, so removing somebody who has worked
| would leave completed checklists that no longer say who completed them.
|
| The cost of always retiring fell in the wrong place: the row keeps its unique
| email and employee number while being invisible in every list, so an account
| created by mistake could never be created again — the administrator is told
| the address is taken, by a user they cannot find anywhere.
*/

function deletionAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('removes an account outright when nothing in the record names them', function (): void {
    $created = User::factory()->create(['email' => 'mistake@labelhouse.com']);
    $created->assignRole(Roles::SUPERVISOR);

    Livewire::actingAs(deletionAdmin())
        ->test(UserManager::class)
        ->call('confirmDelete', $created->id)
        ->call('deleteUser');

    expect(User::withTrashed()->whereKey($created->id)->exists())->toBeFalse();
});

it('frees the email address so the account can be created again', function (): void {
    // The whole point. An account created by mistake — a typo, a person who
    // never started — has to be creatable again.
    $created = User::factory()->create(['email' => 'mistake@labelhouse.com']);
    $created->assignRole(Roles::SUPERVISOR);

    $admin = deletionAdmin();

    Livewire::actingAs($admin)
        ->test(UserManager::class)
        ->call('confirmDelete', $created->id)
        ->call('deleteUser');

    Livewire::actingAs($admin)
        ->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Second Attempt')
        ->set('email', 'mistake@labelhouse.com')
        ->set('role', Roles::SUPERVISOR)
        ->set('password', 'a-strong-password')
        ->call('save')
        ->assertHasNoErrors();

    expect(User::query()->where('email', 'mistake@labelhouse.com')->count())->toBe(1);
});

it('retires rather than removes an account the record still names', function (): void {
    // A completed checklist with no operator is worse than no checklist at
    // all, because it still looks like a record.
    $operator = User::factory()->create();
    $operator->assignRole(Roles::OPERATOR);

    ChecklistRun::factory()->create(['operator_id' => $operator->id]);

    Livewire::actingAs(deletionAdmin())
        ->test(UserManager::class)
        ->call('confirmDelete', $operator->id)
        ->call('deleteUser');

    $operator->refresh();

    expect(User::withTrashed()->whereKey($operator->id)->exists())->toBeTrue()
        ->and($operator->trashed())->toBeTrue()
        ->and($operator->is_active)->toBeFalse();
});

it('keeps the operator name on work the retired account signed', function (): void {
    $operator = User::factory()->create(['full_name' => 'Named On The Record']);
    $operator->assignRole(Roles::OPERATOR);

    $run = ChecklistRun::factory()->create(['operator_id' => $operator->id]);

    Livewire::actingAs(deletionAdmin())
        ->test(UserManager::class)
        ->call('confirmDelete', $operator->id)
        ->call('deleteUser');

    expect($run->fresh()->operator_id)->toBe($operator->id);
});

it('tells the administrator which of the two is about to happen', function (): void {
    $worked = User::factory()->create();
    $worked->assignRole(Roles::OPERATOR);
    ChecklistRun::factory()->create(['operator_id' => $worked->id]);

    $fresh = User::factory()->create();
    $fresh->assignRole(Roles::SUPERVISOR);

    $admin = deletionAdmin();

    // "This can be undone" and "this cannot" are different decisions, and the
    // administrator is entitled to know which is in front of them.
    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('confirmDelete', $worked->id)
        ->assertSee(__('app.users.delete_keeps_record', ['name' => $worked->full_name]), false);

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('confirmDelete', $fresh->id)
        ->assertSee(__('app.users.delete_removes', ['name' => $fresh->full_name]), false);
});

it('hides retired accounts until they are asked for', function (): void {
    $operator = User::factory()->create(['full_name' => 'Retired Operator']);
    $operator->assignRole(Roles::OPERATOR);
    ChecklistRun::factory()->create(['operator_id' => $operator->id]);

    $admin = deletionAdmin();

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('confirmDelete', $operator->id)
        ->call('deleteUser');

    // The everyday list is the people who exist...
    Livewire::actingAs($admin)->test(UserManager::class)
        ->set('includeInactive', true)
        ->assertDontSee('Retired Operator');

    // ...but it must be reachable, or the account holds an email address while
    // appearing nowhere.
    Livewire::actingAs($admin)->test(UserManager::class)
        ->set('showDeleted', true)
        ->assertSee('Retired Operator');
});

it('puts a retired account back, switched off', function (): void {
    // Coming back into the list should not also hand somebody a working
    // sign-in; turning the account on is a separate, deliberate act.
    $operator = User::factory()->create();
    $operator->assignRole(Roles::OPERATOR);
    ChecklistRun::factory()->create(['operator_id' => $operator->id]);

    $admin = deletionAdmin();

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('confirmDelete', $operator->id)
        ->call('deleteUser');

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('restoreUser', $operator->id);

    $operator->refresh();

    expect($operator->trashed())->toBeFalse()
        ->and($operator->is_active)->toBeFalse();
});

it('names the retired account when its email blocks a new one', function (): void {
    /*
     * The plain "already been taken" sent an administrator looking for a user
     * who was not in the list — the exact dead end that made deleting an
     * account feel broken.
     */
    $operator = User::factory()->create([
        'full_name' => 'Blocking Person',
        'email' => 'blocking@labelhouse.com',
    ]);
    $operator->assignRole(Roles::OPERATOR);
    ChecklistRun::factory()->create(['operator_id' => $operator->id]);

    $admin = deletionAdmin();

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('confirmDelete', $operator->id)
        ->call('deleteUser');

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Someone New')
        ->set('email', 'blocking@labelhouse.com')
        ->set('role', Roles::SUPERVISOR)
        ->call('save')
        ->assertHasErrors('email');

    expect(__('app.users.taken_by_deleted', ['name' => 'Blocking Person']))
        ->toContain('Blocking Person')
        ->and(__('app.users.taken_by_deleted', ['name' => 'Blocking Person']))
        ->toContain(__('app.users.show_deleted'));
});

it('will not hand a retired employee number to somebody new', function (): void {
    // Worse than the inconvenience of choosing another: it would attach the
    // new person to the old one's history.
    $operator = User::factory()->create(['employee_number' => 'OP-4242']);
    $operator->assignRole(Roles::OPERATOR);
    ChecklistRun::factory()->create(['operator_id' => $operator->id]);

    $admin = deletionAdmin();

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('confirmDelete', $operator->id)
        ->call('deleteUser');

    Livewire::actingAs($admin)->test(UserManager::class)
        ->call('openCreateModal')
        ->set('fullName', 'Someone New')
        ->set('employeeNumber', 'OP-4242')
        ->set('role', Roles::SUPERVISOR)
        ->call('save')
        ->assertHasErrors('employeeNumber');
});

it('considers every foreign key that points at a user', function (): void {
    /*
     * The guard on the rule above. `hasMaintenanceHistory()` is an explicit
     * list so the decision is reviewable, and an explicit list goes stale the
     * moment somebody adds a table. A new `SET NULL` reference that nobody
     * added here would silently make accounts hard-deletable while their name
     * was still on something — the record would lose it without a word.
     */
    $referenced = DB::select("
        SELECT k.TABLE_NAME AS t, k.COLUMN_NAME AS c
        FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r
          ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
        WHERE k.REFERENCED_TABLE_NAME = 'users'
          AND k.TABLE_SCHEMA = DATABASE()
          AND r.DELETE_RULE = 'SET NULL'
    ");

    $considered = collect(User::HISTORY_REFERENCES)
        ->flatMap(fn (array $columns, string $table) => collect($columns)->map(fn (string $c) => "{$table}.{$c}"))
        ->sort()
        ->values();

    $actual = collect($referenced)
        ->map(fn (object $r) => "{$r->t}.{$r->c}")
        ->sort()
        ->values();

    expect($actual->all())->toEqualCanonicalizing($considered->all());
});
