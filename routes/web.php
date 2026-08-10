<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Kiosk\KioskActivationController;
use App\Http\Controllers\Kiosk\KioskEnrolmentController;
use App\Http\Controllers\Kiosk\KioskEnrolmentRequestController;
use App\Http\Controllers\Kiosk\KioskSessionController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\RunPdfController;
use App\Http\Controllers\ViewAsController;
use App\Http\Controllers\WalkthroughController;
use App\Livewire\Admin\HolidayManager;
use App\Livewire\Admin\KioskDeviceManager;
use App\Livewire\Admin\LocationManager;
use App\Livewire\Admin\MachineManager;
use App\Livewire\Admin\PartManager;
use App\Livewire\Admin\QrStickerSheet;
use App\Livewire\Admin\TemplateEditor;
use App\Livewire\Admin\TemplateManager;
use App\Livewire\Admin\UserManager;
use App\Livewire\Dashboard;
use App\Livewire\Issues\IssueDetail;
use App\Livewire\Issues\IssueRegister;
use App\Livewire\Kiosk\EnrolmentRequests;
use App\Livewire\Kiosk\MachinePicker;
use App\Livewire\Kiosk\MachineRuns;
use App\Livewire\Kiosk\OperatorPicker;
use App\Livewire\Machines\MachineProfile;
use App\Livewire\Reports\ReportViewer;
use App\Livewire\Runs\ApprovalQueue;
use App\Livewire\Runs\RunForm;
use App\Livewire\Runs\RunIndex;
use App\Livewire\Runs\RunReview;
use App\Livewire\Runs\VerificationQueue;
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

    /*
     * Password reset by email, for office users only — see
     * User::canResetPasswordByEmail(). Floor operators sign in by PIN and an
     * administrator clears a PIN from the users screen.
     *
     * Both POSTs are throttled per IP on top of the broker's own per-address
     * limit (`auth.passwords.users.throttle`, 60s): without it, this form is
     * an unauthenticated way to make the application send mail.
     */
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.update');
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

    // QR sticker deep link, carrying the slug printed on the sticker.
    //
    // The parameter is `{code}`, NOT `{machine}`, and that is load-bearing.
    // Livewire's ImplicitRouteBinding matches route parameters against the
    // component's public property NAMES: a `{machine}` parameter would bind
    // to MachineRuns::$machine (typed ?Machine) and resolve the model before
    // mount() ever ran — turning an unknown slug into a bare 404 and handing
    // mount() a model where it wants the raw string. MachineRuns does its own
    // lookup precisely so a peeled or out-of-date sticker gets a readable
    // kiosk screen instead. `{code}` matches the public string $code, which
    // Livewire passes through untouched.
    Route::get('/m/{code}', MachineRuns::class)->name('kiosk.machine');

    // "Tap your name" grid, optionally scoped to a machine and a run.
    Route::get('/kiosk/operators/{machine?}', OperatorPicker::class)->name('kiosk.operators');

    // PIN pad for one operator, then the PIN submission itself.
    Route::get('/kiosk/pin/{user}', [KioskSessionController::class, 'create'])->name('kiosk.pin.show');
    Route::post('/kiosk/pin', [KioskSessionController::class, 'store'])->name('kiosk.pin');

    // Drop the operator session but keep the tablet enrolled.
    Route::post('/kiosk/release', [KioskSessionController::class, 'destroy'])->name('kiosk.release');
});

/*
 * Tablet enrolment — plants the device cookie, so it is NOT behind the
 * `kiosk` middleware, because that cookie is what `kiosk` checks for.
 *
 * Two ways in. The signed link below is the one the admin screen uses and is
 * deliberately NOT behind `auth`.
 *
 * The signature is the credential: it is minted by a holder of
 * `kiosk.manage` on the admin screen, expires in minutes, and is scanned by
 * the tablet. The alternative is typing an admin password into a shared
 * shop-floor device, which is worse. See KioskEnrolmentController for what
 * the link is worth if it leaks.
 */
Route::get('/kiosk/link/{device}', [KioskEnrolmentController::class, 'enrolViaLink'])
    ->middleware('signed')
    ->name('kiosk.enrol.link');

/*
 * Self-service activation from a machine's printed QR sticker. Reached from
 * the not-enrolled screen, so `auth` bounces an unauthenticated scanner to
 * the login page and returns them here afterwards — which is what makes
 * "scan, log in, and it works" a single journey.
 *
 * Gated on `kiosk.activate`, NOT `kiosk.manage`: setting up the tablet in
 * your hand is a shop-floor act a supervisor needs when one is replaced
 * mid-shift, while renaming, revoking and deleting devices stays with
 * administrators on the fleet screen below.
 */
Route::middleware(['auth', 'permission:kiosk.activate'])->group(function (): void {
    Route::get('/kiosk/activate', [KioskActivationController::class, 'create'])->name('kiosk.activate');
    Route::post('/kiosk/activate', [KioskActivationController::class, 'store'])->name('kiosk.activate.store');
});

/*
 * Asking for a kiosk, for people who may not enrol one themselves.
 *
 * `auth` only — deliberately NOT `permission:kiosk.activate`. The whole point
 * is that an operator, who holds no such permission, has a move other than
 * finding a supervisor. Asking is not enrolling: nothing here creates a
 * device or sets the kiosk cookie, and every request waits for somebody who
 * does hold the permission.
 *
 * `claim` is a POST and lives here rather than on the review screen because
 * enrolment sets a cookie, and a cookie can only be set on the browser making
 * the request. An approval granted at a supervisor's desk has to be redeemed
 * by the tablet it was granted for.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/kiosk/request', [KioskEnrolmentRequestController::class, 'create'])->name('kiosk.request');
    Route::post('/kiosk/request', [KioskEnrolmentRequestController::class, 'store'])->name('kiosk.request.store');
    Route::post('/kiosk/request/claim', [KioskEnrolmentRequestController::class, 'claim'])->name('kiosk.request.claim');
});

/*
 * The review queue. `kiosk.activate`, not `kiosk.manage`: approving a request
 * is the act of turning a device into a kiosk, which is the supervisor's
 * power. Putting it on the fleet screen would hide it from every supervisor
 * able to act on it.
 */
Route::middleware(['auth', 'permission:kiosk.activate'])->group(function (): void {
    Route::get('/kiosk/requests', EnrolmentRequests::class)->name('kiosk.requests');
});

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
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    /*
     * "View as" — an administrator previewing another role's permissions.
     * POST because it changes session state; gated on the REAL admin role, so
     * an administrator midway through a preview can still get back out.
     */
    /*
     * First-run walkthrough. Both write to the signed-in user's own record,
     * so neither is a GET — a crawler or a pasted link must not be able to
     * dismiss somebody's introduction for them.
     */
    Route::post('/walkthrough/complete', [WalkthroughController::class, 'complete'])->name('walkthrough.complete');
    Route::post('/walkthrough/replay', [WalkthroughController::class, 'replay'])->name('walkthrough.replay');
    Route::post('/walkthrough/preview', [WalkthroughController::class, 'preview'])->name('walkthrough.preview');

    Route::post('/view-as', [ViewAsController::class, 'start'])->name('view-as.start');
    Route::post('/view-as/stop', [ViewAsController::class, 'stop'])->name('view-as.stop');

    /*
    | Reports (milestone 7). `report.view` shows the numbers; taking them out
    | of the building needs `export.data` as well, which the export
    | controller checks for itself.
    */
    Route::middleware('permission:report.view')->group(function (): void {
        Route::get('/reports', ReportViewer::class)->name('reports.index');
        Route::get('/reports/{report}/csv', [ReportExportController::class, 'csv'])->name('reports.csv');
        Route::get('/reports/{report}/pdf', [ReportExportController::class, 'pdf'])->name('reports.pdf');
    });

    /*
     * Machine profile (SPEC screen 5). Not under /admin: the people who need
     * a machine's history are the ones standing next to it, so this is gated
     * on MachinePolicy::view — `machine.view` plus the machine scope — rather
     * than on `machine.manage`.
     *
     * Machine::getRouteKeyName() is `code`, so the URI carries the same slug
     * the QR sticker does. Binding to the model here IS wanted, unlike the
     * kiosk's /m/{code}: an unknown code on an office screen reached from a
     * list is a plain 404, not a peeling sticker needing an explanation.
     */
    Route::get('/machines/{machine}', MachineProfile::class)->name('machines.show');

    /*
     * Run photos and signature images.
     *
     * Both were served straight off the public disk as guessable URLs
     * (seed-notes §D11) — no login required to fetch a fault photo or an
     * operator's signature. MediaController re-checks the policy of the
     * record each file belongs to and streams it.
     *
     * `role` is constrained here so it can never name another column.
     */
    Route::get('/media/attachments/{attachment}', [MediaController::class, 'attachment'])
        ->name('media.attachment');

    Route::get('/media/signatures/{run}/{role}', [MediaController::class, 'signature'])
        ->whereIn('role', ['operator', 'supervisor'])
        ->name('media.signature');

    Route::get('/runs', RunIndex::class)->name('runs.index');

    /*
     * The approval queue MUST be declared before /runs/{run}: registered the
     * other way round, "approvals" would be bound as a run id and 404.
     * `permission:run.approve` is the coarse gate — both components
     * re-authorise in mount(), and every decision re-checks its policy.
     */
    Route::get('/runs/approvals', ApprovalQueue::class)
        ->middleware('permission:run.approve')
        ->name('runs.approvals');

    /*
     * The QA queue. Declared BEFORE /runs/{run} for the same reason the
     * approval queue is: registered after, "verifications" binds as a run id
     * and 404s.
     */
    Route::get('/runs/verifications', VerificationQueue::class)
        ->middleware('permission:run.verify')
        ->name('runs.verifications');

    Route::get('/runs/{run}', RunForm::class)->name('runs.show');

    // The paper-form facsimile (milestone 7). Gated on the run's own view
    // policy, not `export.data` — printing a sheet you may already read is
    // the same disclosure, and a supervisor signing off has to file it.
    Route::get('/runs/{run}/pdf', RunPdfController::class)->name('runs.pdf');

    /*
     * The review screen serves two audiences now: a supervisor deciding, and
     * a Quality Assurance officer verifying afterwards. QA holds neither
     * `run.approve` nor `run.reject`, so the gate is either permission —
     * `|` is spatie's OR. The component re-checks in mount(), and every
     * action re-checks its own policy.
     */
    Route::get('/runs/{run}/review', RunReview::class)
        ->middleware('permission:run.approve|run.verify')
        ->name('runs.review');

    /*
    | Issues register (milestone 6). `issue.view` is the coarse gate; both
    | components re-authorise through IssuePolicy, which scopes visibility to
    | the machines the user may see.
    */
    Route::middleware('permission:issue.view')->group(function (): void {
        Route::get('/issues', IssueRegister::class)->name('issues.index');
        Route::get('/issues/{issue}', IssueDetail::class)->name('issues.show');
    });
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
        // Before /machines/{...} would matter — there is no such route today,
        // but the literal segment is registered first regardless.
        Route::get('/machines/qr', QrStickerSheet::class)->name('machines.qr');
        Route::get('/locations', LocationManager::class)->name('locations');
    });

    // Kiosk tablets. `kiosk.manage`, not `machine.manage` — enrolling a
    // tablet is a different job from editing the equipment list.
    Route::get('/kiosk', KioskDeviceManager::class)
        ->middleware('permission:kiosk.manage')
        ->name('kiosk');

    Route::get('/parts', PartManager::class)
        ->middleware('permission:part.manage')
        ->name('parts');

    Route::middleware('permission:template.manage')->group(function (): void {
        Route::get('/templates', TemplateManager::class)->name('templates');
        Route::get('/templates/{template}/edit', TemplateEditor::class)->name('templates.edit');
    });

    // User administration. `user.manage` is held only by the admin role, so
    // this is admin-only without a second check.
    Route::get('/users', UserManager::class)
        ->middleware('permission:user.manage')
        ->name('users');

    Route::get('/holidays', HolidayManager::class)
        ->middleware('permission:holiday.manage')
        ->name('holidays');
});
