<?php

namespace App\Filters;

use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ThrottleFilter implements FilterInterface
{
    /**
     * Rate limit requests by IP address and user ID (if authenticated).
     *
     * Two independent limits are enforced:
     * - IP-based: applies to all requests from this IP (prevents unauthenticated abuse)
     * - User-based: applies to all requests from this user regardless of IP
     *   (prevents bypass via VPN/proxy rotation by authenticated users)
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache = Services::cache();
        $response = service('response');

        $ipLimit   = (int) env('RATE_LIMIT_REQUESTS', 60);
        $userLimit = (int) env('RATE_LIMIT_USER_REQUESTS', 100);
        $window    = (int) env('RATE_LIMIT_WINDOW', 60);

        $ip     = $request->getIPAddress();
        $userId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;

        // IP-based rate limit (always applied)
        $ipKey       = 'rate_limit_ip_' . md5($ip);
        $ipRemaining = $this->checkRateLimit($cache, $ipKey, $ipLimit, $window);

        if ($ipRemaining === false) {
            return $this->rateLimitExceeded($response, $ipLimit, $window);
        }

        // User-based rate limit (only when authenticated)
        if ($userId !== null) {
            $userKey       = 'rate_limit_user_' . $userId;
            $userRemaining = $this->checkRateLimit($cache, $userKey, $userLimit, $window);

            if ($userRemaining === false) {
                return $this->rateLimitExceeded($response, $userLimit, $window);
            }
        }

        // Store rate limit info in request for after() method (reports IP limit)
        if ($request instanceof ApiRequest) {
            $request->setRateLimitInfo([
                'limit'     => $ipLimit,
                'remaining' => max(0, $ipRemaining),
                'reset'     => time() + $window,
            ]);
        }

        return $request;
    }

    /**
     * Check and increment rate limit counter for a given cache key.
     *
     * @return int|false Remaining requests if allowed, false if limit exceeded
     */
    private function checkRateLimit(CacheInterface $cache, string $key, int $limit, int $window): int|false
    {
        $requests = $cache->get($key);

        if ($requests === null) {
            $cache->save($key, 1, $window);

            return $limit - 1;
        }

        $requests = (int) $requests;

        if ($requests >= $limit) {
            return false;
        }

        $cache->save($key, $requests + 1, $window);

        return $limit - ($requests + 1);
    }

    /**
     * Process after the request
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Set rate limit headers if they were stored in the before() method
        if ($request instanceof ApiRequest && $request->getRateLimitInfo() !== null) {
            $info = $request->getRateLimitInfo();
            $response->setHeader('X-RateLimit-Limit', (string) $info['limit']);
            $response->setHeader('X-RateLimit-Remaining', (string) $info['remaining']);
            $response->setHeader('X-RateLimit-Reset', (string) $info['reset']);
        }

        return $response;
    }

    /**
     * Return 429 Too Many Requests response
     *
     * @param ResponseInterface $response
     * @param int $maxRequests
     * @param int $window
     * @return ResponseInterface
     */
    private function rateLimitExceeded(ResponseInterface $response, int $maxRequests, int $window): ResponseInterface
    {
        $retryAfter = $window;
        $body = array_merge(
            ApiResponse::error(
                ['rate_limit' => lang('Auth.tooManyRequests', [$maxRequests, $window])],
                lang('Auth.rateLimitExceeded'),
                429
            ),
            ['retry_after' => $retryAfter]
        );

        $response->setStatusCode(429);
        $response->setHeader('Retry-After', (string) $retryAfter);
        $response->setHeader('X-RateLimit-Limit', (string) $maxRequests);
        $response->setHeader('X-RateLimit-Remaining', '0');
        $response->setHeader('X-RateLimit-Reset', (string) (time() + $retryAfter));
        $response->setJSON($body);

        return $response;
    }
}
