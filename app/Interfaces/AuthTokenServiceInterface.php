<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Auth Token Service Interface
 *
 * Facade for authentication token operations used by TokenController.
 */
interface AuthTokenServiceInterface
{
    /**
     * Refresh access token using refresh token.
     *
     * @param array $data
     * @return array
     */
    public function refreshAccessToken(array $data): array;

    /**
     * Revoke current access token from authorization header.
     *
     * @param array $data
     * @return array
     */
    public function revoke(array $data): array;

    /**
     * Revoke all tokens for current user.
     *
     * @param array $data
     * @return array
     */
    public function revokeAll(array $data): array;
}
