<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\SecurityContext;

/**
 * Token Revocation Service Interface
 *
 * Contract for token revocation functionality
 */
interface TokenRevocationServiceInterface
{
    /**
     * Revoke an access token by adding its JTI to blacklist
     */
    public function revokeToken(string $jti, int $expiresAt, ?SecurityContext $context = null): bool;

    /**
     * Revoke an access token from authorization header
     */
    public function revokeAccessToken(array $data, ?SecurityContext $context = null): bool;

    /**
     * Check if a token is revoked
     */
    public function isRevoked(string $jti): bool;

    /**
     * Revoke all tokens for a user
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): bool;

    /**
     * Clean up expired blacklisted tokens
     */
    public function cleanupExpired(): int;
}
