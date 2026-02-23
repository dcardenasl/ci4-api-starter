<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Concerns\ApiKeyThrottleHelpers;
use App\Filters\Concerns\RateLimitResponseHelpers;
use App\HTTP\ApiRequest;
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
    use ApiKeyThrottleHelpers;
    use RateLimitResponseHelpers;

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
        $ip = $request->getIPAddress();
        $userId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;

        // API key policy for auth routes:
        // 1) If X-App-Key is present, validate it first and enforce key-based limits.
        // 2) If X-App-Key is absent, fallback to auth IP-based throttle.
        $rawKey = $request->getHeaderLine('X-App-Key');
        if ($rawKey !== '') {
            $appKey = $this->resolveApiKey($cache, $rawKey);

            if ($appKey === false) {
                return $this->unauthorizedApiKeyResponse($response);
            }

            if ($userId === null) {
                $userId = $this->extractUserIdFromBearer($request);
            }

            $apiKeyResult = $this->enforceApiKeyRateLimit(
                $cache,
                $appKey,
                $ip,
                $userId,
                $window,
                fn (int $maxRequests, int $window): ResponseInterface =>
                    $this->rateLimitExceeded($response, $maxRequests, $window)
            );

            if ($apiKeyResult instanceof ResponseInterface) {
                return $apiKeyResult;
            }

            if ($request instanceof ApiRequest) {
                $request->setAuthRateLimitInfo($apiKeyResult);
                $request->setAppKeyId($appKey->id);
            }

            return $request;
        }

        // No API key: use stricter auth route limit by IP.
        $cacheKey = 'auth_rate_limit_' . md5($ip);

        $remaining = $this->checkRateLimit($cache, $cacheKey, $maxAttempts, $window);

        if ($remaining === false) {
            return $this->rateLimitExceeded($response, $maxAttempts, $window);
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
            $this->attachRateLimitHeaders($response, $info);
        }

        return $response;
    }

    private function rateLimitExceeded(ResponseInterface $response, int $maxAttempts, int $window): ResponseInterface
    {
        return $this->buildRateLimitExceededResponse(
            $response,
            $maxAttempts,
            $window,
            'Auth.tooManyLoginAttempts',
            [$maxAttempts, (int) ($window / 60)]
        );
    }

}
