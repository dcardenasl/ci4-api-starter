<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

use App\DTO\Response\Auth\MeResponseDTO;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Services\Iam\EffectivePermissionsResolver;

/**
 * Session Manager
 *
 * Issues access (JWT) + refresh tokens and assembles the canonical
 * session response. The `user` field is always a `MeResponseDTO`
 * (id, email, first/last name, status, avatar, timestamps, roles,
 * permissions) — the same shape returned by `/auth/me` and `PATCH
 * /auth/me`, so consumers can persist it as-is from any of the four
 * endpoints without losing fields.
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
     * Build a session response (access + refresh tokens + canonical user).
     *
     * Returns array by design — this is a helper-internal method consumed
     * immediately by AuthService::login() and TokenController::refresh(), both
     * of which wrap the result in a LoginResponseDTO / TokenResponseDTO. It is
     * not part of the service's public surface and the "services return DTOs"
     * rule does not apply here.
     *
     * @param object $user The authenticated user entity.
     * @return array{access_token:string, refresh_token:string, expires_in:int, user:array<string,mixed>}
     */
    public function generateSessionResponse(object $user): array
    {
        $userId = (int) ($user->id ?? 0);
        $permissions = $userId > 0
            ? $this->permissionsResolver->resolve($userId, self::APPLICATION_ID)
            : [];

        $accessToken = $this->jwtService->encode($userId, $permissions);
        $refreshToken = $this->refreshTokenService->issueRefreshToken($userId);

        $userPayload = MeResponseDTO::fromUserData(
            $this->normalizeUser($user),
            $permissions
        )->toArray();

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => $this->accessTokenTtl,
            'user'          => $userPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUser(object $user): array
    {
        if (method_exists($user, 'toArray')) {
            $array = $user->toArray();
            if (is_array($array)) {
                return $array;
            }
        }

        return get_object_vars($user);
    }
}
