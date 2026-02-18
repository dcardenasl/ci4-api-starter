<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\BadRequestException;
use App\Interfaces\JwtServiceInterface;
use App\Interfaces\TokenRevocationServiceInterface;
use App\Libraries\ApiResponse;
use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use CodeIgniter\Cache\CacheInterface;

/**
 * Token Revocation Service
 *
 * Handles revocation of access tokens (JWT) and refresh tokens
 */
class TokenRevocationService implements TokenRevocationServiceInterface
{
    public function __construct(
        protected TokenBlacklistModel $blacklistModel,
        protected RefreshTokenModel $refreshTokenModel,
        protected JwtServiceInterface $jwtService,
        protected ?CacheInterface $cache = null
    ) {
        // Use injected cache or get from Services
        $this->cache = $cache ?? \Config\Services::cache();
    }

    /**
     * Revoke an access token by adding its JTI to blacklist
     *
     * @param string $jti Token JTI (unique identifier)
     * @param int $expiresAt Token expiration timestamp
     * @return array
     */
    public function revokeToken(string $jti, int $expiresAt): array
    {
        $added = $this->blacklistModel->addToBlacklist($jti, $expiresAt);

        if (!$added) {
            throw new BadRequestException(
                lang('Tokens.revocationFailed'),
                ['token' => lang('Tokens.revocationFailed')]
            );
        }

        // Mark as revoked in cache immediately. Use the full token TTL because a revoked
        // token will never become valid again, so long-lived caching is safe here.
        $cacheKey = "token_revoked_{$jti}";
        $ttl      = (int) env('JWT_ACCESS_TOKEN_TTL', 3600);
        $this->cache->save($cacheKey, 1, $ttl);

        return ApiResponse::success(null, lang('Tokens.tokenRevokedSuccess'));
    }

    /**
     * Revoke an access token from authorization header
     *
     * @param array $data Request data with 'authorization_header'
     * @return array
     * @throws BadRequestException If header missing or invalid format
     * @throws AuthenticationException If token invalid or missing claims
     */
    public function revokeAccessToken(array $data): array
    {
        // Validar header presente
        if (empty($data['authorization_header'])) {
            throw new BadRequestException(
                lang('Tokens.invalidRequest'),
                ['authorization' => lang('Tokens.authorizationHeaderRequired')]
            );
        }

        $header = $data['authorization_header'];

        // Parsear Bearer token
        if (!preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            throw new BadRequestException(
                lang('Tokens.invalidRequest'),
                ['authorization' => lang('Tokens.invalidAuthorizationFormat')]
            );
        }

        $token = $matches[1];

        // Decodificar y validar JWT
        $payload = $this->jwtService->decode($token);

        if (!$payload) {
            throw new AuthenticationException(
                lang('Tokens.invalidToken'),
                ['token' => lang('Tokens.tokenDecodeFailed')]
            );
        }

        if (!isset($payload->jti) || !isset($payload->exp)) {
            throw new AuthenticationException(
                lang('Tokens.invalidToken'),
                ['token' => lang('Tokens.missingRequiredClaims')]
            );
        }

        // Revocar token agregando JTI a blacklist
        return $this->revokeToken($payload->jti, (int) $payload->exp);
    }

    /**
     * Check if a token is revoked
     *
     * @param string $jti Token JTI
     * @return bool
     */
    public function isRevoked(string $jti): bool
    {
        // Check cache first for performance
        $cacheKey = "token_revoked_{$jti}";

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        // Check database
        $isBlacklisted = $this->blacklistModel->isBlacklisted($jti);

        if ($isBlacklisted) {
            // Revoked tokens never become valid again — cache for the full token lifetime
            $ttl = (int) env('JWT_ACCESS_TOKEN_TTL', 3600);
        } else {
            // Non-revoked tokens are cached briefly so newly revoked tokens are detected
            // within JWT_REVOCATION_CACHE_TTL seconds (default 60)
            $ttl = (int) env('JWT_REVOCATION_CACHE_TTL', 60);
        }

        $this->cache->save($cacheKey, $isBlacklisted ? 1 : 0, $ttl);

        return $isBlacklisted;
    }

    /**
     * Revoke all tokens for a user
     *
     * @param int $userId
     * @return array
     */
    public function revokeAllUserTokens(int $userId): array
    {
        // Revoke all refresh tokens
        $this->refreshTokenModel->revokeAllUserTokens($userId);

        // Note: We can't easily revoke all access tokens without tracking them
        // Access tokens will expire naturally based on JWT_ACCESS_TOKEN_TTL

        return ApiResponse::success(
            null,
            lang('Tokens.allUserTokensRevoked')
        );
    }

    /**
     * Clean up expired blacklisted tokens
     *
     * @return int Number of deleted tokens
     */
    public function cleanupExpired(): int
    {
        $deletedBlacklist = $this->blacklistModel->deleteExpired();
        $deletedRefresh = $this->refreshTokenModel->deleteExpired();

        return $deletedBlacklist + $deletedRefresh;
    }
}
