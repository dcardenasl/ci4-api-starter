<?php

namespace App\Filters;

use App\Entities\ApiKeyEntity;
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
     * Rate limit cache TTL for resolved API keys (5 minutes).
     */
    private const API_KEY_CACHE_TTL = 300;

    /**
     * Rate limit requests by IP address, user ID (if authenticated), and API key
     * (if the X-App-Key header is present).
     *
     * Strategy matrix:
     *
     * | Context                | Primary limit         | Secondary limit         |
     * |------------------------|-----------------------|-------------------------|
     * | No key + no JWT        | IP (60/min)           | —                       |
     * | No key + with JWT      | IP (60/min)           | user_id (100/min)       |
     * | Valid key + no JWT     | app_key (600/min)     | IP defensive (200/min)  |
     * | Valid key + with JWT   | app_key (600/min)     | user_id (60/min)        |
     * | Invalid/inactive key   | 401 Unauthorized      | —                       |
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return RequestInterface|ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache    = Services::cache();
        $response = service('response');

        $ip     = $request->getIPAddress();
        $userId = $request instanceof ApiRequest ? $request->getAuthUserId() : null;

        // ------------------------------------------------------------------
        // 1. Resolve API key from X-App-Key header
        // ------------------------------------------------------------------
        $rawKey = $request->getHeaderLine('X-App-Key');
        $appKey = null;

        if ($rawKey !== '') {
            $appKey = $this->resolveApiKey($cache, $rawKey);

            if ($appKey === false) {
                // Key present but invalid or inactive → 401
                return $this->unauthorizedResponse($response);
            }
        }

        // ------------------------------------------------------------------
        // 2. Extract userId from JWT when it has not yet been set by JwtAuthFilter
        //    (ThrottleFilter runs before JwtAuthFilter on public routes).
        //    We do a best-effort, non-security-critical parse of the bearer token
        //    solely to apply per-user rate limiting.
        // ------------------------------------------------------------------
        if ($userId === null) {
            $userId = $this->extractUserIdFromBearer($request);
        }

        // ------------------------------------------------------------------
        // 3. Apply rate limits according to strategy matrix
        // ------------------------------------------------------------------
        $window = (int) env('RATE_LIMIT_WINDOW', 60);

        if ($appKey instanceof ApiKeyEntity) {
            // ---- API key path ----
            $appKeyWindow = $appKey->rate_limit_window > 0
                ? $appKey->rate_limit_window
                : $window;

            // Primary: global key budget
            $keyBucket    = 'api_key_' . $appKey->id;
            $keyRemaining = $this->checkRateLimit(
                $cache,
                $keyBucket,
                $appKey->rate_limit_requests,
                $appKeyWindow
            );

            if ($keyRemaining === false) {
                return $this->rateLimitExceeded($response, $appKey->rate_limit_requests, $appKeyWindow);
            }

            // Secondary: per-user or per-IP defensive limit
            if ($userId !== null) {
                $userBucket    = 'api_key_' . $appKey->id . '_user_' . $userId;
                $userRemaining = $this->checkRateLimit(
                    $cache,
                    $userBucket,
                    $appKey->user_rate_limit,
                    $appKeyWindow
                );

                if ($userRemaining === false) {
                    return $this->rateLimitExceeded($response, $appKey->user_rate_limit, $appKeyWindow);
                }
            } else {
                $ipBucket    = 'api_key_' . $appKey->id . '_ip_' . md5($ip);
                $ipRemaining = $this->checkRateLimit(
                    $cache,
                    $ipBucket,
                    $appKey->ip_rate_limit,
                    $appKeyWindow
                );

                if ($ipRemaining === false) {
                    return $this->rateLimitExceeded($response, $appKey->ip_rate_limit, $appKeyWindow);
                }
            }

            // Report the primary (key-level) limit in headers
            if ($request instanceof ApiRequest) {
                $request->setRateLimitInfo([
                    'limit'     => $appKey->rate_limit_requests,
                    'remaining' => max(0, $keyRemaining),
                    'reset'     => time() + $appKeyWindow,
                ]);
                $request->setAppKeyId($appKey->id);
            }
        } else {
            // ---- Fallback path (no API key) ----
            $ipLimit   = (int) env('RATE_LIMIT_REQUESTS', 60);
            $userLimit = (int) env('RATE_LIMIT_USER_REQUESTS', 100);

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
        }

        return $request;
    }

    /**
     * Process after the request — attach rate limit response headers.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if ($request instanceof ApiRequest && $request->getRateLimitInfo() !== null) {
            $info = $request->getRateLimitInfo();
            $response->setHeader('X-RateLimit-Limit', (string) $info['limit']);
            $response->setHeader('X-RateLimit-Remaining', (string) $info['remaining']);
            $response->setHeader('X-RateLimit-Reset', (string) $info['reset']);
        }

        return $response;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve an API key entity from the raw key value.
     *
     * Results are cached for API_KEY_CACHE_TTL seconds.
     * Cache misses (invalid keys) are NOT cached to prevent enumeration attacks.
     *
     * @param CacheInterface $cache
     * @param string         $rawKey Raw key from X-App-Key header
     * @return ApiKeyEntity|false ApiKeyEntity on success, false when invalid/inactive
     */
    private function resolveApiKey(CacheInterface $cache, string $rawKey): ApiKeyEntity|false
    {
        $hash     = hash('sha256', $rawKey);
        $cacheKey = 'api_key_' . $hash;

        $cached = $cache->get($cacheKey);

        if ($cached !== null) {
            // Cached as an associative array to avoid serialising the entity class
            $entity = new ApiKeyEntity();
            foreach ($cached as $field => $value) {
                $entity->$field = $value;
            }
            return $entity;
        }

        // Look up in database
        $model  = Services::apiKeyModel();
        $apiKey = $model->findByHash($hash);

        if (!$apiKey || !$apiKey->isActive()) {
            // Do NOT cache misses — prevents timing/enumeration attacks
            return false;
        }

        // Cache the hit as a plain array
        $cache->save($cacheKey, $apiKey->toArray(), self::API_KEY_CACHE_TTL);

        return $apiKey;
    }

    /**
     * Best-effort extraction of user ID from the Authorization Bearer token.
     *
     * This is used ONLY for rate limiting decisions on routes where JwtAuthFilter
     * has not yet run. The JWT is NOT fully validated here (no revocation check,
     * no signature re-validation beyond what Firebase\JWT does internally).
     *
     * @param RequestInterface $request
     * @return int|null
     */
    private function extractUserIdFromBearer(RequestInterface $request): ?int
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        if ($token === '') {
            return null;
        }

        try {
            $jwtService = Services::jwtService();
            $decoded    = $jwtService->decode($token);

            return ($decoded && isset($decoded->uid)) ? (int) $decoded->uid : null;
        } catch (\Throwable $e) {
            // Swallow all errors — this is best-effort only
            return null;
        }
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
     * Return 401 Unauthorized when the supplied API key is invalid or inactive.
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function unauthorizedResponse(ResponseInterface $response): ResponseInterface
    {
        $body = ApiResponse::error(
            ['api_key' => 'The provided API key is invalid or inactive'],
            'Unauthorized',
            401
        );

        $response->setStatusCode(401);
        $response->setJSON($body);

        return $response;
    }

    /**
     * Return 429 Too Many Requests response
     *
     * @param ResponseInterface $response
     * @param int               $maxRequests
     * @param int               $window
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
