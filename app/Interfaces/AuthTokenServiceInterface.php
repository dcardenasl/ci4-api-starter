<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\DTO\SecurityContext;

/**
 * Modernized Auth Token Service Interface
 */
interface AuthTokenServiceInterface
{
    /**
     * Refresh access token using refresh token.
     */
    public function refreshAccessToken(RefreshTokenRequestDTO $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Revoke current access token from authorization header.
     */
    public function revokeToken(string $authorizationHeader, ?SecurityContext $context = null): bool;

    /**
     * Revoke all tokens for current user.
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): bool;
}
