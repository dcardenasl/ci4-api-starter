<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Iam;

use App\Interfaces\Iam\AppUserMembershipServiceInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * Smoke tests for AppUserMembershipService. Extend with domain-specific assertions
 * as business rules accumulate in the service.
 *
 * @internal
 */
final class AppUserMembershipServiceTest extends CIUnitTestCase
{
    public function testServiceImplementsItsInterface(): void
    {
        $service = Services::appUserMembershipService(false);

        $this->assertInstanceOf(AppUserMembershipServiceInterface::class, $service);
    }
}
