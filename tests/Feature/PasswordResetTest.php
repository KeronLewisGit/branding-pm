<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| Password reset by email
|--------------------------------------------------------------------------
| For office users, who sign in with a password. A floor operator signs in
| with a PIN on a shared tablet and has no email address to type — an
| administrator clears their PIN from the users screen instead.
*/

/*
|--------------------------------------------------------------------------
| Asking for a link
|--------------------------------------------------------------------------
*/

it('shows the form and links to it from the sign-in page', function (): void {
    $this->get('/login')->assertOk()->assertSee(route('password.request'), escape: false);
    $this->get('/forgot-password')->assertOk()->assertSee(__('app.auth.forgot_title'));
});

it('emails a link to somebody who signs in with a password', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'supervisor@example.com']);

    $this->post('/forgot-password', ['email' => 'supervisor@example.com'])
        ->assertSessionHas('status', __('app.auth.reset_link_sent'));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('says exactly the same thing when it sends nothing', function (array $attributes): void {
    // The reply must not reveal whether an address belongs to anybody, or
    // whether that person is allowed to reset. On a site this size, "does
    // this person work here?" is a real disclosure.
    Notification::fake();

    User::factory()->create(array_merge(['email' => 'known@example.com'], $attributes));

    $this->post('/forgot-password', ['email' => 'known@example.com'])
        ->assertSessionHas('status', __('app.auth.reset_link_sent'))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
})->with([
    'a deactivated account' => [['is_active' => false]],
    'a PIN-only operator' => [['password' => null, 'pin' => '1234']],
]);

it('says the same thing again for an address nobody has', function (): void {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status', __('app.auth.reset_link_sent'))
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});

it('still validates the address itself', function (): void {
    $this->post('/forgot-password', ['email' => 'not-an-address'])
        ->assertSessionHasErrors('email');
});

/*
|--------------------------------------------------------------------------
| Using the link
|--------------------------------------------------------------------------
*/

it('sets a new password, and lets them in with it', function (): void {
    $user = User::factory()->create(['email' => 'manager@example.com']);
    $token = Password::createToken($user);

    $this->get("/reset-password/{$token}?email=manager@example.com")->assertOk();

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'manager@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('login'))->assertSessionHas('status', __('app.auth.reset_done'));

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeTrue();

    $this->post('/login', [
        'identifier' => 'manager@example.com',
        'password' => 'a-brand-new-password',
    ])->assertRedirect(route('dashboard'));
});

it('does not sign them in just for setting one', function (): void {
    // A forwarded reset email must not be a session. They prove they hold the
    // new password by using it.
    $user = User::factory()->create(['email' => 'manager@example.com']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'manager@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    $this->assertGuest();
});

it('will not let one link be used twice', function (): void {
    $user = User::factory()->create(['email' => 'manager@example.com']);
    $token = Password::createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'manager@example.com',
        'password' => 'first-new-password',
        'password_confirmation' => 'first-new-password',
    ];

    $this->post('/reset-password', $payload)->assertSessionHasNoErrors();

    $this->post('/reset-password', array_merge($payload, [
        'password' => 'second-new-password',
        'password_confirmation' => 'second-new-password',
    ]))->assertSessionHasErrors('email');

    expect(Hash::check('first-new-password', $user->fresh()->password))->toBeTrue();
});

it('refuses a link issued before the account was deactivated', function (): void {
    // Eligibility is re-checked when the token is spent, not trusted from
    // when it was issued — otherwise deactivating somebody leaves a working
    // key in their inbox for an hour.
    $user = User::factory()->create(['email' => 'manager@example.com']);
    $token = Password::createToken($user);

    $user->update(['is_active' => false]);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'manager@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('a-brand-new-password', $user->fresh()->password))->toBeFalse();
});

it('rejects a token that was never issued', function (): void {
    User::factory()->create(['email' => 'manager@example.com']);

    $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'manager@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('email');
});

it('requires the two password fields to agree, and to be long enough', function (): void {
    $user = User::factory()->create(['email' => 'manager@example.com']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'manager@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'something-else',
    ])->assertSessionHasErrors('password');

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'manager@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');
});

it('records the change, because who reset this account is an audit question', function (): void {
    $user = User::factory()->create(['email' => 'manager@example.com']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'manager@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'description' => 'password.reset',
        'subject_id' => $user->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Who this is for
|--------------------------------------------------------------------------
*/

it('knows who can reset by email and who cannot', function (): void {
    expect(User::factory()->create()->canResetPasswordByEmail())->toBeTrue()
        ->and(User::factory()->create(['is_active' => false])->canResetPasswordByEmail())->toBeFalse()
        ->and(User::factory()->create(['email' => null])->canResetPasswordByEmail())->toBeFalse()
        ->and(User::factory()->create(['password' => null, 'pin' => '1234'])->canResetPasswordByEmail())->toBeFalse();
});

it('tells an operator with no email what to do instead', function (): void {
    // The one case the form cannot help with is the most common one on the
    // floor, so it is answered on the page rather than after a failed try.
    $this->get('/forgot-password')->assertOk()->assertSee(__('app.auth.forgot_no_email'));
});

it('throttles requests so this is not an open mail relay', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'manager@example.com']);

    foreach (range(1, 6) as $ignored) {
        $this->post('/forgot-password', ['email' => 'manager@example.com']);
    }

    $this->post('/forgot-password', ['email' => 'manager@example.com'])
        ->assertStatus(429);
});
