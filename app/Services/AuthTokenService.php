<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Interfaces\AuthTokenServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Interfaces\TokenRevocationServiceInterface;

/**
 * Auth Token Service
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
    public function refreshAccessToken(\App\DTO\Request\Identity\RefreshTokenRequestDTO $request): \App\DTO\Response\Identity\TokenResponseDTO
    {
        return $this->refreshTokenService->refreshAccessToken($request);
    }

    /**
     * Revoke current access token from authorization header
     */
    public function revoke(array $data): array
    {
        return $this->tokenRevocationService->revokeAccessToken($data);
    }

    /**
     * Revoke all user tokens
     */
    public function revokeAll(array $data): array
    {
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;

        if ($userId <= 0) {
            throw new AuthenticationException(lang('Auth.authRequired'));
        }

        return $this->tokenRevocationService->revokeAllUserTokens($userId);
    }
}
