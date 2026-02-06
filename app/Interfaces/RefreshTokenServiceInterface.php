<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Refresh Token Service Interface
 *
 * Contract for refresh token lifecycle management
 */
interface RefreshTokenServiceInterface
{
    /**
     * Issue a new refresh token
     *
     * @param int $userId
     * @return string Generated refresh token
     */
    public function issueRefreshToken(int $userId): string;

    /**
     * Refresh access token using refresh token
     *
     * @param array $data Request data with 'refresh_token'
     * @return array
     */
    public function refreshAccessToken(array $data): array;

    /**
     * Revoke a refresh token
     *
     * @param array $data Request data with 'refresh_token'
     * @return array
     */
    public function revoke(array $data): array;

    /**
     * Revoke all user's refresh tokens
     *
     * @param int $userId
     * @return array
     */
    public function revokeAllUserTokens(int $userId): array;
}
