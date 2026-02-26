<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\DTO\Response\Identity\TokenResponseDTO;

/**
 * Auth Token Service Interface
 *
 * Facade for authentication token operations used by TokenController.
 */
interface AuthTokenServiceInterface
{
    /**
     * Refresh access token using refresh token.
     */
    public function refreshAccessToken(RefreshTokenRequestDTO $request): TokenResponseDTO;

    /**
     * Revoke current access token from authorization header.
     */
    public function revoke(array $data): array;

    /**
     * Revoke all tokens for current user.
     */
    public function revokeAll(array $data): array;
}
