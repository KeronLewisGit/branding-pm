<?php

namespace Tests;

use App\Models\MailSetting;
use App\Support\MailRelay;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Clear anything memoised in a static between tests.
     *
     * `RefreshDatabase` rolls the database back; it cannot roll back a static
     * property, so a memo populated by one test outlives it and the next test
     * reads a row that no longer exists. That failure is worse than most: it
     * depends on execution order, so the test passes on its own and fails in
     * the suite, which reads like flakiness rather than a stale cache.
     */
    protected function setUp(): void
    {
        parent::setUp();

        MailSetting::forget();
        MailRelay::fakeBridge(null);
    }
}
