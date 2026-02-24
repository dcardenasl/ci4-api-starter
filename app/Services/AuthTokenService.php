<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Interfaces\AuthTokenServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Interfaces\TokenRevocationServiceInterface;

class AuthTokenService implements AuthTokenServiceInterface
{
    public function __construct(
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected TokenRevocationServiceInterface $tokenRevocationService
    ) {
    }

    public function refreshAccessToken(array $data): array
    {
        return $this->refreshTokenService->refreshAccessToken($data);
    }

    public function revoke(array $data): array
    {
        return $this->tokenRevocationService->revokeAccessToken($data);
    }

    public function revokeAll(array $data): array
    {
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;

        if ($userId <= 0) {
            throw new AuthenticationException(lang('Auth.authRequired'));
        }

        return $this->tokenRevocationService->revokeAllUserTokens($userId);
    }
}
