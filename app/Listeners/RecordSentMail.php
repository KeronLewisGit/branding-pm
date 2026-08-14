<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Write down that a message left, and the id the provider gave it.
 *
 * Nothing recorded any send, which made "it said it sent but nobody got it"
 * unanswerable from this end. A relay accepting a message is not the same as
 * delivering it: SendGrid replies the moment it takes custody, then decides
 * later — suppression lists, bounces, a recipient domain that silently
 * discards anything unauthenticated. All of that happens after the application
 * has been told everything went fine, and rightly so; it had.
 *
 * The message id is the part that matters. It is what a provider's activity
 * feed is searched by, so this is the difference between "check SendGrid" and
 * "look up this exact message in SendGrid".
 *
 * Subjects and recipients only. Never the body: these carry issued passwords,
 * and a log file is a far easier thing to read than a mailbox.
 */
class RecordSentMail
{
    /*
     * Registered by discovery, not by hand. Laravel finds listeners in
     * app/Listeners by the event their handle() type-hints, so registering
     * this in a provider as well subscribes it twice and writes every send to
     * the log twice — which is how a log stops being trustworthy as a count.
     */
    public function handle(MessageSent $event): void
    {
        Log::info('Mail accepted by the relay', [
            'message_id' => $event->sent->getMessageId(),
            // The mailer in force, from config. MessageSent does not carry
            // the mailer's name — an earlier version of this line read a
            // `__laravel_mailer` key that this framework version never sets,
            // so it was a fallback pretending to be a lookup.
            'mailer' => config('mail.default'),
            'to' => $this->addresses($event->message->getTo()),
            'cc' => $this->addresses($event->message->getCc()),
            'subject' => $event->message->getSubject(),
        ]);
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<string>
     */
    private function addresses(array $addresses): array
    {
        return array_map(static fn (Address $address): string => $address->getAddress(), $addresses);
    }
}
