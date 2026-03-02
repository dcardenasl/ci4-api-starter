<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;

/**
 * Session Manager
 *
 * Orchestrates the issuance of Access Tokens (JWT) and Refresh Tokens
 * to establish a user session.
 */
class SessionManager
{
    public function __construct(
        protected JwtServiceInterface $jwtService,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected int $accessTokenTtl = 3600
    ) {
    }

    /**
     * Generate a complete session response (Access + Refresh tokens)
     *
     * @param array $userData Pre-mapped user data from AuthUserMapper
     */
    public function generateSessionResponse(array $userData): array
    {
        $userId = (int) ($userData['id'] ?? 0);
        $role = (string) ($userData['role'] ?? 'user');

        $accessToken = $this->jwtService->encode($userId, $role);
        $refreshToken = $this->refreshTokenService->issueRefreshToken($userId);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->accessTokenTtl,
            'user' => $userData,
        ];
    }
}
