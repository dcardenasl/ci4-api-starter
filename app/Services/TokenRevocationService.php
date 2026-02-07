<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
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

        // Ensure revocation is effective immediately even if a stale cache entry exists
        $cacheKey = "token_revoked_{$jti}";
        $this->cache->save($cacheKey, 1, 300);

        return ApiResponse::success(null, lang('Tokens.tokenRevokedSuccess'));
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

        // Cache result for 5 minutes
        $this->cache->save($cacheKey, $isBlacklisted ? 1 : 0, 300);

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
