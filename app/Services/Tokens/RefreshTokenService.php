<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\Exceptions\AuthenticationException;
use App\Interfaces\Tokens\JwtServiceInterface;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\Users\UserAccountGuard;
use App\Support\OperationResult;

/**
 * Refresh Token Service
 *
 * Manages refresh token lifecycle (issue, refresh, revoke)
 */
readonly class RefreshTokenService implements \App\Interfaces\Tokens\RefreshTokenServiceInterface
{
    use \App\Traits\HandlesTransactions;

    public function __construct(
        protected RefreshTokenModel $refreshTokenModel,
        protected JwtServiceInterface $jwtService,
        protected UserModel $userModel,
        protected UserAccountGuard $userAccountGuard
    ) {
    }

    /**
     * Issue a new refresh token
     */
    public function issueRefreshToken(int $userId): string
    {
        $token = \generate_token();

        // Calculate expiry from environment or default to 7 days
        $ttl = (int) (getenv('JWT_REFRESH_TOKEN_TTL') ?: env('JWT_REFRESH_TOKEN_TTL', 604800));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $this->refreshTokenModel->insert([
            'user_id'    => $userId,
            'token'      => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Refresh access token using refresh token (with rotation)
     */
    public function refreshAccessToken(\App\DTO\Request\Identity\RefreshTokenRequestDTO $request): \App\DTO\Response\Identity\TokenResponseDTO
    {
        return $this->wrapInTransaction(function () use ($request) {
            $tokenRecord = $this->refreshTokenModel->findActiveForUpdate($request->refreshToken);

            if (!$tokenRecord || !isset($tokenRecord->user_id)) {
                throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
            }

            // Revoke old refresh token (token rotation security)
            $this->refreshTokenModel->revokeToken($request->refreshToken);

            // Issue new refresh token
            $newRefreshToken = $this->issueRefreshToken((int) $tokenRecord->user_id);

            // Validate user account status
            $user = $this->userModel->find($tokenRecord->user_id);
            if (!$user instanceof \App\Entities\UserEntity) {
                throw new AuthenticationException(lang('Tokens.userNotFound'));
            }

            $this->userAccountGuard->assertCanAuthenticate($user);

            // Generate new access token
            $accessToken = $this->jwtService->encode((int) $user->id, (string) ($user->role ?? 'user'));

            return \App\DTO\Response\Identity\TokenResponseDTO::fromArray([
                'access_token'  => $accessToken,
                'refresh_token' => $newRefreshToken,
                'expires_in'    => (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600)),
            ]);
        });
    }

    /**
     * Revoke a refresh token
     */
    public function revoke(array $data): OperationResult
    {
        $revoked = $this->refreshTokenModel->revokeToken($data['refresh_token'] ?? '');

        if (!$revoked) {
            throw new \App\Exceptions\NotFoundException(lang('Tokens.tokenNotFound'));
        }

        return OperationResult::success(null, lang('Tokens.refreshTokenRevoked'));
    }

    /**
     * Revoke all user's refresh tokens
     */
    public function revokeAllUserTokens(int $userId): OperationResult
    {
        $this->refreshTokenModel->revokeAllUserTokens($userId);

        return OperationResult::success(null, lang('Tokens.allTokensRevoked'));
    }
}
