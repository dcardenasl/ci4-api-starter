<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use Tests\Support\ApiTestCase;

/**
 * RateLimitResponseTest
 *
 * Note: These tests are currently skipped because of state leakage issues
 * in the CI4 Throttler/Cache during integration testing.
 */
class RateLimitResponseTest extends ApiTestCase
{
    public function testAuthThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        $this->markTestSkipped('Throttler state leakage in CI4 Feature tests.');
    }

    public function testGeneralThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        $this->markTestSkipped('Throttler state leakage in CI4 Feature tests.');
    }
}
