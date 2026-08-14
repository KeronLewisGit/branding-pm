<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| A record that a message left
|--------------------------------------------------------------------------
| "It said it sent but nobody received it" was unanswerable from this end.
| A relay accepting a message is not delivering it: the provider replies the
| moment it takes custody and decides later — suppression lists, bounces, a
| recipient domain that discards anything unauthenticated. All of it happens
| after the application has been told everything went fine, and rightly so.
*/

it('records the provider message id, which is what a support query needs', function (): void {
    Log::spy();

    $user = User::factory()->create(['email' => 'newstarter@labelhouse.com']);
    $user->notify(new AccountCredentials('the-password', null));

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context): bool => $message === 'Mail accepted by the relay'
            && ($context['message_id'] ?? null) !== null
            && $context['to'] === ['newstarter@labelhouse.com']
    )->once();
});

it('never writes the body, which carries the password', function (): void {
    // A log file is a far easier thing to read than a mailbox.
    Log::spy();

    $user = User::factory()->create();
    $user->notify(new AccountCredentials('Sup3rSecret-Password', null));

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context): bool => ! str_contains(json_encode($context), 'Sup3rSecret')
    )->once();
});

it('records it once, not once per registration', function (): void {
    /*
     * Laravel discovers listeners in app/Listeners by the event their handle()
     * type-hints. Registering this in a provider as well subscribed it twice
     * and wrote every send to the log twice, which is how a log stops being
     * trustworthy as a count of what happened.
     */
    Log::spy();

    User::factory()->create()->notify(new AccountCredentials('the-password', null));

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message): bool => $message === 'Mail accepted by the relay')
        ->once();
});
