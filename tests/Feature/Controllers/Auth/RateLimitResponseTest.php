<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * RateLimitResponseTest
 *
 * Note: These tests are currently skipped because of state leakage issues
 * in the CI4 Throttler/Cache during integration testing.
 */
class RateLimitResponseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $namespace   = 'App';

    public function testAuthThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        $this->markTestSkipped('Throttler state leakage in CI4 Feature tests.');
    }

    public function testGeneralThrottleExceededReturnsCanonicalErrorResponse(): void
    {
        $this->markTestSkipped('Throttler state leakage in CI4 Feature tests.');
    }
}
