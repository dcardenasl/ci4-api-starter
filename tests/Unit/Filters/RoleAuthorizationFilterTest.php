<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\RoleAuthorizationFilter;
use App\HTTP\ApiRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * RoleAuthorizationFilter Unit Tests
 *
 * Tests role-based access control filter.
 * Critical security component - verifies hierarchical role permissions.
 */
class RoleAuthorizationFilterTest extends CIUnitTestCase
{
    protected RoleAuthorizationFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new RoleAuthorizationFilter();
    }

    /**
     * Helper: Create mock ApiRequest with user role
     */
    private function createMockRequest(?string $userRole = null): ApiRequest
    {
        $request = $this->createMock(ApiRequest::class);

        $request->method('getAuthUserRole')
            ->willReturn($userRole);

        return $request;
    }

    // ==================== TEST CASES ====================

    public function testBeforeWithoutUserRoleReturnsUnauthorized(): void
    {
        $request = $this->createMockRequest(null);

        $result = $this->filter->before($request, ['admin']);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(ResponseInterface::HTTP_UNAUTHORIZED, $result->getStatusCode());

        $body = json_decode($result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertStringContainsString('required', strtolower($body['message']));
    }

    public function testBeforeWithInsufficientRoleReturnsForbidden(): void
    {
        // User role (level 0) trying to access admin route (level 10)
        $request = $this->createMockRequest('user');

        $result = $this->filter->before($request, ['admin']);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(ResponseInterface::HTTP_FORBIDDEN, $result->getStatusCode());

        $body = json_decode($result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertStringContainsString('permission', strtolower($body['message']));
    }

    public function testBeforeWithUserRoleAccessingUserRouteAllowed(): void
    {
        // User role (level 0) accessing user route (level 0) - should pass
        $request = $this->createMockRequest('user');

        $result = $this->filter->before($request, ['user']);

        // When access is granted, filter returns nothing (null/void)
        $this->assertNull($result);
    }

    public function testBeforeWithAdminRoleAccessingUserRouteAllowed(): void
    {
        // Admin role (level 10) accessing user route (level 0) - hierarchical access
        $request = $this->createMockRequest('admin');

        $result = $this->filter->before($request, ['user']);

        // When access is granted, filter returns nothing (null/void)
        $this->assertNull($result);
    }

    public function testBeforeWithAdminRoleAccessingAdminRouteAllowed(): void
    {
        // Admin role (level 10) accessing admin route (level 10) - exact match
        $request = $this->createMockRequest('admin');

        $result = $this->filter->before($request, ['admin']);

        // When access is granted, filter returns nothing (null/void)
        $this->assertNull($result);
    }

    public function testBeforeWithUnknownRoleDefaultsToDeny(): void
    {
        // Unknown role should default to level 0
        $request = $this->createMockRequest('superuser');

        $result = $this->filter->before($request, ['admin']);

        // Unknown role defaults to 0, admin requires 10, so should be forbidden
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(ResponseInterface::HTTP_FORBIDDEN, $result->getStatusCode());
    }

    public function testBeforeWithoutArgumentsDefaultsToUserRole(): void
    {
        // If no role argument provided, defaults to 'user' requirement
        $request = $this->createMockRequest('user');

        $result = $this->filter->before($request);

        // User accessing default 'user' route should pass
        $this->assertNull($result);
    }

    public function testBeforeRoleHierarchyIsRespected(): void
    {
        // Test the hierarchical role system explicitly
        $adminRequest = $this->createMockRequest('admin');
        $userRequest = $this->createMockRequest('user');

        // Admin (10) can access user routes (0)
        $this->assertNull($this->filter->before($adminRequest, ['user']));

        // Admin (10) can access admin routes (10)
        $this->assertNull($this->filter->before($adminRequest, ['admin']));

        // User (0) can access user routes (0)
        $this->assertNull($this->filter->before($userRequest, ['user']));

        // User (0) CANNOT access admin routes (10)
        $result = $this->filter->before($userRequest, ['admin']);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(ResponseInterface::HTTP_FORBIDDEN, $result->getStatusCode());
    }

    public function testAfterDoesNothing(): void
    {
        $request = $this->createMock(ApiRequest::class);
        $response = $this->createMock(Response::class);

        $result = $this->filter->after($request, $response);

        $this->assertNull($result);
    }
}
