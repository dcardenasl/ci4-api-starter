<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;

/**
 * Modernized Auth Token Service Interface
 */
interface AuthTokenServiceInterface
{
    /**
     * Refresh access token using refresh token.
     */
    public function refreshAccessToken(RefreshTokenRequestDTO $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Revoke current access token from authorization header.
     */
    public function revokeToken(string $authorizationHeader): array;

    /**
     * Revoke all tokens for current user.
     */
    public function revokeAllUserTokens(int $userId): array;
}
