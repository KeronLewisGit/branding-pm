<?php

declare(strict_types=1);

use App\Enums\KioskRequestStatus;
use App\Http\Controllers\Kiosk\KioskEnrolmentRequestController as RequestController;
use App\Http\Middleware\EnsureKioskDevice;
use App\Livewire\Kiosk\EnrolmentRequests;
use App\Models\KioskDevice;
use App\Models\KioskEnrolmentRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| An operator asking for a kiosk, and a supervisor deciding
|--------------------------------------------------------------------------
| Enrolling a device is a trust decision, so `kiosk.activate` stays with
| supervisors. Before this an operator at an unenrolled tablet had no move at
| all, and being stuck is what people work around.
|
| The piece worth testing hardest is the claim: enrolment sets a cookie, and a
| cookie can only be set on the browser making the request, so an approval
| given at a supervisor's desk has to be redeemed by the tablet it was for.
*/

function requestUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/*
|--------------------------------------------------------------------------
| Asking
|--------------------------------------------------------------------------
*/

it('lets an operator ask for a kiosk without letting them enrol one', function (): void {
    $operator = requestUser('operator');

    $this->actingAs($operator)
        ->post(route('kiosk.request.store'), [
            'suggested_name' => 'Tablet at the MATAN',
            'note' => 'The old one stopped working.',
        ])
        ->assertRedirect(route('kiosk.request'));

    $created = KioskEnrolmentRequest::query()->first();

    expect($created)->not->toBeNull()
        ->and($created->status)->toBe(KioskRequestStatus::Pending)
        ->and($created->requested_by_id)->toBe($operator->id)
        // Asking is not enrolling. Nothing exists to sign in on yet.
        ->and(KioskDevice::query()->count())->toBe(0);
});

it('gives the asking browser a claim cookie, and no device cookie', function (): void {
    $this->actingAs(requestUser('operator'))
        ->post(route('kiosk.request.store'), ['suggested_name' => 'Tablet at the MATAN'])
        ->assertCookie(RequestController::CLAIM_COOKIE)
        ->assertCookieMissing(EnsureKioskDevice::COOKIE);
});

it('refuses to queue the same browser twice', function (): void {
    // Asking again is what somebody does when nothing visibly happened the
    // first time, and a queue with one tablet in it four times stops
    // being read.
    $operator = requestUser('operator');

    $this->actingAs($operator)
        ->post(route('kiosk.request.store'), ['suggested_name' => 'Tablet at the MATAN']);

    $claim = KioskEnrolmentRequest::query()->value('claim_token');

    $this->actingAs($operator)
        ->withCookies([RequestController::CLAIM_COOKIE => $claim])
        ->post(route('kiosk.request.store'), ['suggested_name' => 'Tablet at the MATAN again'])
        ->assertRedirect(route('kiosk.request'));

    expect(KioskEnrolmentRequest::query()->count())->toBe(1);
});

it('keeps the request screen open to anyone signed in', function (): void {
    $this->actingAs(requestUser('operator'))
        ->get(route('kiosk.request'))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Deciding
|--------------------------------------------------------------------------
*/

it('keeps the review queue to holders of kiosk.activate', function (): void {
    $this->actingAs(requestUser('operator'))
        ->get(route('kiosk.requests'))
        ->assertForbidden();

    $this->actingAs(requestUser('supervisor'))
        ->get(route('kiosk.requests'))
        ->assertOk();
});

it('creates the device on approval but does not enrol the approver', function (): void {
    // The supervisor is not sitting at the tablet. A device cookie set here
    // would enrol the wrong browser, and would take their own session with it.
    $operator = requestUser('operator');
    $supervisor = requestUser('supervisor');

    $enrolmentRequest = KioskEnrolmentRequest::create([
        'requested_by_id' => $operator->id,
        'claim_token' => KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'Tablet at the MATAN',
        'status' => KioskRequestStatus::Pending,
    ]);

    Livewire::actingAs($supervisor)
        ->test(EnrolmentRequests::class)
        ->call('startApprove', $enrolmentRequest->id)
        ->set('deviceName', 'MATAN tablet')
        ->call('approve')
        ->assertHasNoErrors();

    $enrolmentRequest->refresh();

    // Nothing queued a device cookie: a Livewire response cannot assert on
    // cookies, and the queue is where one would have to appear.
    expect(Cookie::hasQueued(EnsureKioskDevice::COOKIE))->toBeFalse()
        ->and($enrolmentRequest->status)->toBe(KioskRequestStatus::Approved)
        ->and($enrolmentRequest->reviewed_by_id)->toBe($supervisor->id)
        ->and($enrolmentRequest->device?->name)->toBe('MATAN tablet');
});

it('will not decline without a reason', function (): void {
    // A refusal with no reason is one that gets asked again tomorrow.
    $enrolmentRequest = KioskEnrolmentRequest::create([
        'requested_by_id' => requestUser('operator')->id,
        'claim_token' => KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'Tablet',
        'status' => KioskRequestStatus::Pending,
    ]);

    Livewire::actingAs(requestUser('supervisor'))
        ->test(EnrolmentRequests::class)
        ->call('startDecline', $enrolmentRequest->id)
        ->set('declineReason', '')
        ->call('decline')
        ->assertHasErrors('declineReason');

    expect($enrolmentRequest->refresh()->status)->toBe(KioskRequestStatus::Pending);
});

it('cannot decide the same request twice', function (): void {
    $enrolmentRequest = KioskEnrolmentRequest::create([
        'requested_by_id' => requestUser('operator')->id,
        'claim_token' => KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'Tablet',
        'status' => KioskRequestStatus::Approved,
    ]);

    Livewire::actingAs(requestUser('supervisor'))
        ->test(EnrolmentRequests::class)
        ->call('startApprove', $enrolmentRequest->id)
        ->set('deviceName', 'Second device')
        ->call('approve');

    // Already decided, so no second device is conjured for it.
    expect(KioskDevice::query()->count())->toBe(0)
        ->and($enrolmentRequest->refresh()->status)->toBe(KioskRequestStatus::Approved);
});

/*
|--------------------------------------------------------------------------
| Claiming — the half that must happen on the asking browser
|--------------------------------------------------------------------------
*/

it('enrols the asking browser when it redeems an approved request', function (): void {
    $operator = requestUser('operator');
    $supervisor = requestUser('supervisor');
    $device = KioskDevice::create(['name' => 'MATAN tablet', 'token' => Str::random(64), 'is_active' => true]);

    $enrolmentRequest = KioskEnrolmentRequest::create([
        'requested_by_id' => $operator->id,
        'claim_token' => $token = KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'MATAN tablet',
        'status' => KioskRequestStatus::Approved,
        'reviewed_by_id' => $supervisor->id,
        'reviewed_at' => now(),
        'kiosk_device_id' => $device->id,
    ]);

    $this->actingAs($operator)
        ->withCookies([RequestController::CLAIM_COOKIE => $token])
        ->post(route('kiosk.request.claim'))
        ->assertRedirect(route('kiosk.home'))
        ->assertCookie(EnsureKioskDevice::COOKIE, $device->token);

    $enrolmentRequest->refresh();

    expect($enrolmentRequest->status)->toBe(KioskRequestStatus::Claimed)
        // The reviewer authorised it; the operator only redeemed it. The fleet
        // list must name whoever made the trust decision.
        ->and($device->refresh()->enrolled_by_id)->toBe($supervisor->id);
});

it('refuses to redeem a request that is still pending', function (): void {
    $operator = requestUser('operator');

    KioskEnrolmentRequest::create([
        'requested_by_id' => $operator->id,
        'claim_token' => $token = KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'Tablet',
        'status' => KioskRequestStatus::Pending,
    ]);

    $this->actingAs($operator)
        ->withCookies([RequestController::CLAIM_COOKIE => $token])
        ->post(route('kiosk.request.claim'))
        ->assertRedirect(route('kiosk.request'))
        ->assertCookieMissing(EnsureKioskDevice::COOKIE);
});

it('refuses to redeem a request twice', function (): void {
    $operator = requestUser('operator');
    $device = KioskDevice::create(['name' => 'T', 'token' => Str::random(64), 'is_active' => true]);

    KioskEnrolmentRequest::create([
        'requested_by_id' => $operator->id,
        'claim_token' => $token = KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'T',
        'status' => KioskRequestStatus::Claimed,
        'kiosk_device_id' => $device->id,
        'claimed_at' => now(),
    ]);

    $this->actingAs($operator)
        ->withCookies([RequestController::CLAIM_COOKIE => $token])
        ->post(route('kiosk.request.claim'))
        ->assertCookieMissing(EnsureKioskDevice::COOKIE);
});

it('refuses to redeem a request whose device has since been revoked', function (): void {
    $operator = requestUser('operator');
    $device = KioskDevice::create(['name' => 'Revoked', 'token' => Str::random(64), 'is_active' => false]);

    KioskEnrolmentRequest::create([
        'requested_by_id' => $operator->id,
        'claim_token' => $token = KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'Revoked',
        'status' => KioskRequestStatus::Approved,
        'kiosk_device_id' => $device->id,
    ]);

    $this->actingAs($operator)
        ->withCookies([RequestController::CLAIM_COOKIE => $token])
        ->post(route('kiosk.request.claim'))
        ->assertCookieMissing(EnsureKioskDevice::COOKIE);
});

it('gives a browser holding no claim nothing at all', function (): void {
    // The one message covers "no claim", "not approved" and "already
    // redeemed": a browser with a stale token learns nothing about whether
    // the request was ever real.
    $this->actingAs(requestUser('operator'))
        ->post(route('kiosk.request.claim'))
        ->assertRedirect(route('kiosk.request'))
        ->assertCookieMissing(EnsureKioskDevice::COOKIE);
});

/*
|--------------------------------------------------------------------------
| The way in
|--------------------------------------------------------------------------
*/

it('offers an operator the request entry instead of nothing', function (): void {
    $this->actingAs(requestUser('operator'))
        ->get(route('runs.index'))
        ->assertOk()
        ->assertSee(__('app.nav.kiosk_mode_request'))
        ->assertSee(route('kiosk.request'));
});

it('shows a supervisor how many requests are waiting', function (): void {
    KioskEnrolmentRequest::create([
        'requested_by_id' => requestUser('operator')->id,
        'claim_token' => KioskEnrolmentRequest::newClaimToken(),
        'suggested_name' => 'Tablet',
        'status' => KioskRequestStatus::Pending,
    ]);

    $this->actingAs(requestUser('supervisor'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('app.nav.kiosk_requests'))
        ->assertSee(route('kiosk.requests'));
});

it('files the review queue under System for an administrator', function (): void {
    // An admin already has a System group holding the kiosk fleet; the queue
    // belongs beside it rather than as another top-level row.
    $admin = requestUser('admin');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('app.nav.group_system'))
        ->assertSee(route('kiosk.requests'))
        // Inside the group means after its heading, not above it.
        ->assertSeeInOrder([__('app.nav.group_system'), route('kiosk.requests')], escape: false);
});

it('keeps the review queue top-level for a supervisor, who has no System group', function (): void {
    // kiosk.activate without kiosk.manage: the System group never renders for
    // them, so filing it there would hide it from the people who clear it.
    $this->actingAs(requestUser('supervisor'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('kiosk.requests'))
        ->assertDontSee(__('app.nav.group_system'));
});

it('ships Alpine on every office-layout page, or the menu eats the screen', function (): void {
    /*
     * The regression. `layouts.app` sizes its sidebar through `x-bind:class`,
     * and Alpine arrives ONLY bundled inside Livewire's injected script. A
     * plain controller view on that layout therefore booted no Alpine, the
     * aside kept its static `w-full` and `md:!flex`, and the page rendered as
     * a full-width menu with a blank screen behind it.
     *
     * Nothing about that is visible to `assertSee` — the markup is all there,
     * it is simply laid out wrong — so this asserts the script instead. Any
     * office page added without Livewire fails here rather than on a tablet.
     */
    $operator = requestUser('operator');

    $officePages = [
        'kiosk.request' => route('kiosk.request'),
        'kiosk.requests' => route('kiosk.requests'),
        'runs.index' => route('runs.index'),
    ];

    $supervisor = requestUser('supervisor');

    foreach ($officePages as $name => $url) {
        $actor = $name === 'kiosk.requests' ? $supervisor : $operator;

        $html = $this->actingAs($actor)->get($url)->assertOk()->getContent();

        /*
         * `wire:snapshot`, not the script tag. Livewire injects its assets
         * once per process and skips every page after the first, so asserting
         * on the <script> passes or fails according to test ORDER. Being a
         * Livewire component is the real invariant anyway: that is what pulls
         * the script in, in a browser where each page is its own request.
         */
        expect(str_contains($html, 'wire:snapshot'))
            ->toBeTrue("{$name} renders on layouts.app without Livewire, so Alpine never boots and the sidebar fills the screen");
    }
});
