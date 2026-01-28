<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ThrottleFilter implements FilterInterface
{
    /**
     * Rate limit requests by IP address and JWT token
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache = Services::cache();
        $response = service('response');

        // Get rate limit configuration
        $maxRequests = (int) env('RATE_LIMIT_REQUESTS', 60);
        $window = (int) env('RATE_LIMIT_WINDOW', 60); // in seconds

        // Get identifier (IP + user ID if authenticated)
        $identifier = $this->getIdentifier($request);

        // Cache key for this identifier
        $cacheKey = 'rate_limit_' . $identifier;

        // Get current request count
        $requests = $cache->get($cacheKey);

        if ($requests === null) {
            // First request in this window
            $cache->save($cacheKey, 1, $window);
            $remaining = $maxRequests - 1;
        } else {
            $requests = (int) $requests;

            if ($requests >= $maxRequests) {
                // Rate limit exceeded
                return $this->rateLimitExceeded($response, $maxRequests, $window);
            }

            // Increment request count
            $cache->save($cacheKey, $requests + 1, $window);
            $remaining = $maxRequests - ($requests + 1);
        }

        // Add rate limit headers to response
        $response->setHeader('X-RateLimit-Limit', (string) $maxRequests);
        $response->setHeader('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->setHeader('X-RateLimit-Reset', (string) (time() + $window));

        return $request;
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
        return $response;
    }

    /**
     * Get unique identifier for rate limiting
     * Uses IP address + user ID (if authenticated)
     *
     * @param RequestInterface $request
     * @return string
     */
    private function getIdentifier(RequestInterface $request): string
    {
        $ip = $request->getIPAddress();

        // If user is authenticated, include user ID in identifier
        $userId = $request->userId ?? null;

        if ($userId) {
            return 'rl_' . md5($ip . '_user_' . $userId);
        }

        return 'rl_' . md5($ip . '_guest');
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

        $response->setStatusCode(429);
        $response->setHeader('Retry-After', (string) $retryAfter);
        $response->setHeader('X-RateLimit-Limit', (string) $maxRequests);
        $response->setHeader('X-RateLimit-Remaining', '0');
        $response->setHeader('X-RateLimit-Reset', (string) (time() + $retryAfter));
        $response->setContentType('application/json');
        $response->setBody(json_encode([
            'success' => false,
            'message' => 'Rate limit exceeded. Please try again later.',
            'errors' => [
                'rate_limit' => sprintf(
                    'Too many requests. Maximum %d requests per %d seconds allowed.',
                    $maxRequests,
                    $window
                ),
            ],
            'retry_after' => $retryAfter,
        ]));

        return $response;
    }
}
