<?php

namespace Tests;

use App\Models\MailSetting;
use App\Support\MailRelay;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        /*
         * Before parent::setUp(), which is where the application boots — and
         * booting runs MailRelay::apply(), which reads this memo. Clearing it
         * afterwards is too late by exactly one boot: the previous test's
         * relay is already in config by then, and the suite quietly starts
         * opening real SMTP connections to whatever host that row named.
         */
        $this->forgetStatics();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Again on the way out, so a test that boots without calling setUp in
        // the usual order still leaves nothing behind.
        $this->forgetStatics();

        parent::tearDown();
    }

    /**
     * Clear anything memoised in a static.
     *
     * RefreshDatabase rolls the database back; it cannot roll back a static
     * property, so a memo populated by one test outlives it and the next test
     * reads a row that no longer exists. That failure is worse than most: it
     * depends on execution order, so the test passes on its own and fails in
     * the suite, which reads like flakiness rather than a stale cache.
     */
    private function forgetStatics(): void
    {
        MailSetting::forget();
        MailRelay::fakeBridge(null);
    }
}
