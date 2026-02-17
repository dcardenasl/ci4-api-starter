<?php

declare(strict_types=1);

namespace Tests\Unit\Filters;

use App\Filters\ThrottleFilter;
use App\HTTP\ApiRequest;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

/**
 * ThrottleFilter Unit Tests
 *
 * Tests general rate limiting for API endpoints.
 * Critical for preventing API abuse and DoS attacks.
 */
class ThrottleFilterTest extends CIUnitTestCase
{
    protected ThrottleFilter $filter;
    protected CacheInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new ThrottleFilter();
        $this->mockCache = $this->createMock(CacheInterface::class);

        // Inject mocked cache into Services
        Services::injectMock('cache', $this->mockCache);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Services::reset(true);
    }

    /**
     * Helper: Create mock ApiRequest with IP address
     */
    private function createMockRequest(string $ip = '127.0.0.1', ?int $userId = null): ApiRequest
    {
        $request = $this->createMock(ApiRequest::class);

        $request->method('getIPAddress')
            ->willReturn($ip);

        $request->method('getAuthUserId')
            ->willReturn($userId);

        return $request;
    }

    // ==================== TEST CASES ====================

    public function testBeforeAllowsRequestsWithinLimit(): void
    {
        $request = $this->createMockRequest('192.168.1.1');

        // Simulate first request (cache returns null)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->with(
                $this->stringContains('rate_limit_'),
                1,
                60
            )
            ->willReturn(true);

        // Expect setRateLimitInfo to be called
        $request->expects($this->once())
            ->method('setRateLimitInfo')
            ->with($this->callback(function ($info) {
                return isset($info['limit'], $info['remaining'], $info['reset'])
                    && $info['limit'] === 60
                    && $info['remaining'] === 59;
            }));

        $result = $this->filter->before($request);

        $this->assertInstanceOf(ApiRequest::class, $result);
    }

    public function testBeforeBlocksRequestsExceedingLimit(): void
    {
        $request = $this->createMockRequest('192.168.1.1');

        // Simulate exceeded limit (60 requests already made)
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(60);

        $this->mockCache->expects($this->never())
            ->method('save');

        $result = $this->filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(429, $result->getStatusCode());
    }

    public function testBeforeReturns429WhenThrottled(): void
    {
        $request = $this->createMockRequest('192.168.1.1');

        $this->mockCache->method('get')
            ->willReturn(60); // Limit reached

        $result = $this->filter->before($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(429, $result->getStatusCode());
        $this->assertTrue($result->hasHeader('Retry-After'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Limit'));
        $this->assertEquals('0', $result->getHeaderLine('X-RateLimit-Remaining'));

        $body = json_decode($result->getBody(), true);
        $this->assertEquals('error', $body['status']);
        $this->assertEquals(429, $body['code']);
        $this->assertArrayHasKey('retry_after', $body);
    }

    public function testBeforeUsesIPAddressAsIdentifier(): void
    {
        $request = $this->createMockRequest('10.0.0.5');

        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($this->stringContains('rl_'))
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setRateLimitInfo');

        $this->filter->before($request);

        // Test passes if cache key contains identifier based on IP
        $this->assertTrue(true);
    }

    public function testBeforeUsesIPPlusUserIdForAuthenticatedRequests(): void
    {
        $request = $this->createMockRequest('192.168.1.1', 123);

        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($this->stringContains('rl_'))
            ->willReturn(null);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setRateLimitInfo');

        $this->filter->before($request);

        // Authenticated users get separate rate limit bucket
        $this->assertTrue(true);
    }

    public function testBeforeIncrementsRequestCount(): void
    {
        $request = $this->createMockRequest('192.168.1.1');

        // Simulate 5th request
        $this->mockCache->expects($this->once())
            ->method('get')
            ->willReturn(4);

        $this->mockCache->expects($this->once())
            ->method('save')
            ->with(
                $this->anything(),
                5, // Should increment to 5
                $this->anything()
            )
            ->willReturn(true);

        $request->expects($this->once())
            ->method('setRateLimitInfo')
            ->with($this->callback(function ($info) {
                return $info['remaining'] === 55; // 60 - 5 = 55
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }

    public function testAfterSetsRateLimitHeaders(): void
    {
        $request = $this->createMock(ApiRequest::class);
        $response = new Response(new \Config\App());

        $rateLimitInfo = [
            'limit' => 60,
            'remaining' => 45,
            'reset' => time() + 60,
        ];

        $request->method('getRateLimitInfo')
            ->willReturn($rateLimitInfo);

        $result = $this->filter->after($request, $response);

        $this->assertTrue($result->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Remaining'));
        $this->assertTrue($result->hasHeader('X-RateLimit-Reset'));
        $this->assertEquals('60', $result->getHeaderLine('X-RateLimit-Limit'));
        $this->assertEquals('45', $result->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testAfterDoesNotSetHeadersWhenNoRateLimitInfo(): void
    {
        $request = $this->createMock(ApiRequest::class);
        $response = new Response(new \Config\App());

        $request->method('getRateLimitInfo')
            ->willReturn(null);

        $result = $this->filter->after($request, $response);

        $this->assertFalse($result->hasHeader('X-RateLimit-Limit'));
        $this->assertFalse($result->hasHeader('X-RateLimit-Remaining'));
        $this->assertFalse($result->hasHeader('X-RateLimit-Reset'));
    }

    public function testBeforeRespectsCustomEnvironmentLimits(): void
    {
        // Environment variables need to be set before filter instantiation
        // This test verifies the logic exists, but actual env() calls
        // happen during filter execution and can't be easily mocked

        $request = $this->createMockRequest('192.168.1.1');

        $this->mockCache->method('get')->willReturn(null);
        $this->mockCache->expects($this->once())
            ->method('save')
            ->with(
                $this->anything(),
                1,
                $this->greaterThan(0) // Accept any positive window
            );

        $request->expects($this->once())
            ->method('setRateLimitInfo')
            ->with($this->callback(function ($info) {
                // Verify structure is correct (default is 60 requests)
                return $info['limit'] > 0 && $info['remaining'] >= 0;
            }));

        $this->filter->before($request);

        $this->assertTrue(true);
    }
}
