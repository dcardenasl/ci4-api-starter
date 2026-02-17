<?php

declare(strict_types=1);

namespace App\Filters;

use App\HTTP\ApiRequest;
use App\Libraries\ApiResponse;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Authentication-specific Rate Limiting Filter
 *
 * Applies stricter rate limits to authentication endpoints (login, register,
 * password reset) to prevent brute-force attacks and credential stuffing.
 */
class AuthThrottleFilter implements FilterInterface
{
    /**
     * Maximum authentication attempts per window (per IP)
     */
    private const MAX_AUTH_ATTEMPTS = 5;

    /**
     * Time window in seconds (15 minutes)
     */
    private const AUTH_WINDOW = 900;

    public function before(RequestInterface $request, $arguments = null)
    {
        $cache = Services::cache();
        $response = service('response');

        $maxAttempts = (int) env('AUTH_RATE_LIMIT_REQUESTS', self::MAX_AUTH_ATTEMPTS);
        $window = (int) env('AUTH_RATE_LIMIT_WINDOW', self::AUTH_WINDOW);

        // Rate limit by IP only for auth endpoints (no user context available)
        $ip = $request->getIPAddress();
        $cacheKey = 'auth_rate_limit_' . md5($ip);

        $attempts = $cache->get($cacheKey);

        if ($attempts === null) {
            $cache->save($cacheKey, 1, $window);
            $remaining = $maxAttempts - 1;
        } else {
            $attempts = (int) $attempts;

            if ($attempts >= $maxAttempts) {
                return $this->rateLimitExceeded($response, $maxAttempts, $window);
            }

            $cache->save($cacheKey, $attempts + 1, $window);
            $remaining = $maxAttempts - ($attempts + 1);
        }

        if ($request instanceof ApiRequest) {
            $request->setAuthRateLimitInfo([
                'limit' => $maxAttempts,
                'remaining' => max(0, $remaining),
                'reset' => time() + $window,
            ]);
        }

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request instanceof ApiRequest && $request->getAuthRateLimitInfo() !== null) {
            $info = $request->getAuthRateLimitInfo();
            $response->setHeader('X-RateLimit-Limit', (string) $info['limit']);
            $response->setHeader('X-RateLimit-Remaining', (string) $info['remaining']);
            $response->setHeader('X-RateLimit-Reset', (string) $info['reset']);
        }

        return $response;
    }

    private function rateLimitExceeded(ResponseInterface $response, int $maxAttempts, int $window): ResponseInterface
    {
        $retryAfter = $window;
        $body = array_merge(
            ApiResponse::error(
                ['rate_limit' => lang('Auth.tooManyLoginAttempts', [$maxAttempts, (int) ($window / 60)])],
                lang('Auth.rateLimitExceeded'),
                429
            ),
            ['retry_after' => $retryAfter]
        );

        $response->setStatusCode(429);
        $response->setHeader('Retry-After', (string) $retryAfter);
        $response->setHeader('X-RateLimit-Limit', (string) $maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', '0');
        $response->setHeader('X-RateLimit-Reset', (string) (time() + $retryAfter));
        $response->setJSON($body);

        return $response;
    }
}
