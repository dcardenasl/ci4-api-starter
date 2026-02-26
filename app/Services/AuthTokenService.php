<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Interfaces\AuthTokenServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Interfaces\TokenRevocationServiceInterface;

/**
 * Modernized Auth Token Service
 *
 * Facade for token management operations.
 */
class AuthTokenService implements AuthTokenServiceInterface
{
    public function __construct(
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected TokenRevocationServiceInterface $tokenRevocationService
    ) {
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(\App\DTO\Request\Identity\RefreshTokenRequestDTO $request): \App\Interfaces\DataTransferObjectInterface
    {
        return $this->refreshTokenService->refreshAccessToken($request);
    }

    /**
     * Revoke current access token from authorization header
     */
    public function revokeToken(string $authorizationHeader): array
    {
        return $this->tokenRevocationService->revokeAccessToken([
            'authorization_header' => $authorizationHeader
        ]);
    }

    /**
     * Revoke all user tokens
     */
    public function revokeAllUserTokens(int $userId): array
    {
        if ($userId <= 0) {
            throw new AuthenticationException(lang('Auth.authRequired'));
        }

        return $this->tokenRevocationService->revokeAllUserTokens($userId);
    }
}
