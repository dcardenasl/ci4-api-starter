<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Token Revocation Service Interface
 *
 * Contract for token revocation functionality
 */
interface TokenRevocationServiceInterface
{
    /**
     * Revoke an access token by adding its JTI to blacklist
     *
     * @param string $jti Token JTI (unique identifier)
     * @param int $expiresAt Token expiration timestamp
     * @return array
     */
    public function revokeToken(string $jti, int $expiresAt): array;

    /**
     * Check if a token is revoked
     *
     * @param string $jti Token JTI
     * @return bool
     */
    public function isRevoked(string $jti): bool;

    /**
     * Revoke all tokens for a user
     *
     * @param int $userId
     * @return array
     */
    public function revokeAllUserTokens(int $userId): array;

    /**
     * Clean up expired blacklisted tokens
     *
     * @return int Number of deleted tokens
     */
    public function cleanupExpired(): int;
}
