<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Kiosk\KioskEnrolmentController;
use App\Http\Controllers\Kiosk\KioskSessionController;
use App\Livewire\Admin\HolidayManager;
use App\Livewire\Admin\LocationManager;
use App\Livewire\Admin\MachineManager;
use App\Livewire\Admin\PartManager;
use App\Livewire\Admin\TemplateEditor;
use App\Livewire\Admin\TemplateManager;
use App\Livewire\Kiosk\MachinePicker;
use App\Livewire\Kiosk\MachineRuns;
use App\Livewire\Kiosk\OperatorPicker;
use App\Livewire\Runs\RunForm;
use App\Livewire\Runs\RunIndex;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Route names are fixed by docs/BUILD-CONTRACT.md §6. Other code links to
| them by name, so renaming one here breaks views authored against it.
|
| Three audiences share this file:
|
|   1. Office users (supervisors, managers, admin) — full email/password
|      login, `auth` middleware.
|   2. Shop-floor operators at a shared tablet — the `kiosk` middleware
|      resolves a signed device cookie, then a PIN signs the operator in for
|      a single run. `kiosk.idle` drops them after 2 minutes of inactivity.
|   3. Anyone scanning a QR sticker on a machine — /m/{code}.
*/

Route::redirect('/', '/dashboard')->name('home');

/*
|--------------------------------------------------------------------------
| Authentication (office users)
|--------------------------------------------------------------------------
| The login form takes ONE identifier field accepting either an email
| address or an employee number, because operator email is "mixed" — some
| floor staff have a company address and some do not.
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Kiosk (shared shop-floor tablet)
|--------------------------------------------------------------------------
| `kiosk` = the tablet is enrolled (signed device cookie).
| `kiosk.idle` = the 2-minute inactivity drop, enforced server-side.
|
| These routes are deliberately NOT behind `auth`: the tablet itself is the
| authenticated party, and an operator only signs in (by PIN) at the point
| of signing for a run.
*/
Route::middleware(['kiosk', 'kiosk.idle'])->group(function (): void {
    Route::get('/kiosk', MachinePicker::class)->name('kiosk.home');

    // QR sticker deep link. Machine::getRouteKeyName() is `code`, so this
    // binds on the slug printed on the sticker, not the numeric id.
    Route::get('/m/{machine}', MachineRuns::class)->name('kiosk.machine');

    // "Tap your name" grid, optionally scoped to a machine and a run.
    Route::get('/kiosk/operators/{machine?}', OperatorPicker::class)->name('kiosk.operators');

    // PIN pad for one operator, then the PIN submission itself.
    Route::get('/kiosk/pin/{user}', [KioskSessionController::class, 'create'])->name('kiosk.pin.create');
    Route::post('/kiosk/pin', [KioskSessionController::class, 'store'])->name('kiosk.pin');

    // Drop the operator session but keep the tablet enrolled.
    Route::post('/kiosk/release', [KioskSessionController::class, 'destroy'])->name('kiosk.release');
});

/*
| Tablet enrolment. An admin hits this once per device to plant the cookie;
| it is NOT behind the `kiosk` middleware, because that is what it creates.
*/
Route::middleware(['auth', 'permission:kiosk.manage'])->group(function (): void {
    Route::get('/kiosk/enrol/{device}', [KioskEnrolmentController::class, 'enrol'])->name('kiosk.enrol');
    Route::post('/kiosk/unenrol', [KioskEnrolmentController::class, 'unenrol'])->name('kiosk.unenrol');
});

/*
|--------------------------------------------------------------------------
| Authenticated application
|--------------------------------------------------------------------------
| `kiosk.idle` is applied here too: a PIN-authenticated operator lands on
| runs.show, and must be dropped if they walk away from the tablet. It is a
| no-op for ordinary password sessions, which carry no kiosk session keys.
*/
Route::middleware(['auth', 'kiosk.idle'])->group(function (): void {
    // Milestone 7 — placeholder view, states so on screen.
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/runs', RunIndex::class)->name('runs.index');
    Route::get('/runs/{run}', RunForm::class)->name('runs.show');
});

/*
|--------------------------------------------------------------------------
| Administration
|--------------------------------------------------------------------------
| Permission names come from BUILD-CONTRACT §5. These are a coarse first
| gate only — every component re-authorises in mount() via its policy, and
| every mutating Livewire action re-checks, because a Livewire action is a
| public HTTP endpoint in its own right.
*/
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('permission:machine.manage')->group(function (): void {
        Route::get('/machines', MachineManager::class)->name('machines');
        Route::get('/locations', LocationManager::class)->name('locations');
    });

    Route::get('/parts', PartManager::class)
        ->middleware('permission:part.manage')
        ->name('parts');

    Route::middleware('permission:template.manage')->group(function (): void {
        Route::get('/templates', TemplateManager::class)->name('templates');
        Route::get('/templates/{template}/edit', TemplateEditor::class)->name('templates.edit');
    });

    Route::get('/holidays', HolidayManager::class)
        ->middleware('permission:holiday.manage')
        ->name('holidays');
});
