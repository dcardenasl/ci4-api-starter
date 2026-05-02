<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Services\Iam\EffectivePermissionsResolver;

/**
 * Session Manager
 *
 * Orchestrates the issuance of Access Tokens (JWT) and Refresh Tokens
 * to establish a user session.
 */
class SessionManager
{
    private const APPLICATION_ID = 1;

    public function __construct(
        protected JwtServiceInterface $jwtService,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected EffectivePermissionsResolver $permissionsResolver,
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
        $permissions = $userId > 0 ? $this->permissionsResolver->resolve($userId, self::APPLICATION_ID) : [];

        $accessToken = $this->jwtService->encode($userId, $permissions);
        $refreshToken = $this->refreshTokenService->issueRefreshToken($userId);

        $userResponse = $userData;
        $userResponse['permissions'] = $permissions;

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $this->accessTokenTtl,
            'user' => $userResponse,
        ];
    }
}
