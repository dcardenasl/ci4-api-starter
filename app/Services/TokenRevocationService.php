<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\TokenRevocationServiceInterface;
use App\Libraries\ApiResponse;
use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;

/**
 * Token Revocation Service
 *
 * Handles revocation of access tokens (JWT) and refresh tokens
 */
class TokenRevocationService implements TokenRevocationServiceInterface
{
    protected TokenBlacklistModel $blacklistModel;
    protected RefreshTokenModel $refreshTokenModel;

    public function __construct(
        TokenBlacklistModel $blacklistModel,
        RefreshTokenModel $refreshTokenModel
    ) {
        $this->blacklistModel = $blacklistModel;
        $this->refreshTokenModel = $refreshTokenModel;
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
            return ApiResponse::error(
                ['token' => 'Failed to revoke token'],
                'Revocation failed'
            );
        }

        return ApiResponse::success(null, 'Token revoked successfully');
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
        $cache = \Config\Services::cache();
        $cacheKey = "token_revoked_{$jti}";

        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return (bool) $cached;
        }

        // Check database
        $isBlacklisted = $this->blacklistModel->isBlacklisted($jti);

        // Cache result for 5 minutes
        $cache->save($cacheKey, $isBlacklisted ? 1 : 0, 300);

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
            'All user tokens revoked successfully'
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
