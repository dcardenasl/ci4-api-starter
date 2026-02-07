<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Interfaces\JwtServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Libraries\ApiResponse;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;

/**
 * Refresh Token Service
 *
 * Manages refresh token lifecycle (issue, refresh, revoke)
 */
class RefreshTokenService implements RefreshTokenServiceInterface
{
    public function __construct(
        protected RefreshTokenModel $refreshTokenModel,
        protected JwtServiceInterface $jwtService,
        protected UserModel $userModel
    ) {
    }

    /**
     * Issue a new refresh token
     *
     * @param int $userId
     * @return string Generated refresh token
     */
    public function issueRefreshToken(int $userId): string
    {
        // Generate secure random token
        $token = bin2hex(random_bytes(32));

        // Calculate expiry
        // Check getenv first for unit tests that use putenv(), then fall back to env() for .env files
        $ttl = (int) (getenv('JWT_REFRESH_TOKEN_TTL') ?: env('JWT_REFRESH_TOKEN_TTL', 604800)); // 7 days default
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        // Store in database
        $this->refreshTokenModel->insert([
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * Refresh access token using refresh token
     *
     * Uses database transactions and row-level locking to prevent race conditions
     * where concurrent requests could both succeed with the same token.
     *
     * @param array $data Request data with 'refresh_token'
     * @return array
     */
    public function refreshAccessToken(array $data): array
    {
        if (empty($data['refresh_token'])) {
            throw new BadRequestException(
                lang('Tokens.invalidRequest'),
                ['refresh_token' => lang('Tokens.refreshTokenRequired')]
            );
        }

        $refreshToken = $data['refresh_token'];
        $db = \Config\Database::connect();

        // Start transaction to protect token rotation
        $db->transStart();

        // Validate refresh token with row-level lock (FOR UPDATE)
        // This prevents concurrent requests from retrieving the same token
        $builder = $db->table('refresh_tokens');
        $sql = $builder
            ->where('token', $refreshToken)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->where('revoked_at', null)
            ->getCompiledSelect();

        // Append FOR UPDATE to the compiled query
        $sql .= ' FOR UPDATE';

        $result = $db->query($sql);
        $tokenRecord = $result ? $result->getFirstRow('object') : null;

        if (!$tokenRecord) {
            $db->transRollback();
            throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
        }

        // Revoke old refresh token (token rotation)
        // Still within transaction, so no other request can access this token
        $this->refreshTokenModel->revokeToken($refreshToken);

        // Issue new refresh token
        $newRefreshToken = $this->issueRefreshToken((int) $tokenRecord->user_id);

        // Get user to get role
        $user = $this->userModel->find($tokenRecord->user_id);

        if (!$user) {
            $db->transRollback();
            throw new AuthenticationException(lang('Tokens.userNotFound'));
        }

        if (($user->status ?? null) !== 'active') {
            $db->transRollback();
            throw new AuthorizationException(
                'Account pending approval',
                ['status' => lang('Auth.accountPendingApproval')]
            );
        }

        $isGoogleOAuth = ($user->oauth_provider ?? null) === 'google';
        if ($user->email_verified_at === null && ! $isGoogleOAuth) {
            $db->transRollback();
            throw new AuthenticationException(
                'Email not verified',
                ['email' => lang('Auth.emailNotVerified')]
            );
        }

        $accessToken = $this->jwtService->encode((int) $user->id, $user->role);

        // Commit transaction - all changes are now permanent
        $db->transComplete();

        return ApiResponse::success([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600)),
        ]);
    }

    /**
     * Revoke a refresh token
     *
     * @param array $data Request data with 'refresh_token'
     * @return array
     */
    public function revoke(array $data): array
    {
        if (empty($data['refresh_token'])) {
            throw new BadRequestException(
                lang('Tokens.invalidRequest'),
                ['refresh_token' => lang('Tokens.refreshTokenRequired')]
            );
        }

        $revoked = $this->refreshTokenModel->revokeToken($data['refresh_token']);

        if (!$revoked) {
            throw new NotFoundException(lang('Tokens.tokenNotFound'));
        }

        return ApiResponse::success([], lang('Tokens.refreshTokenRevoked'));
    }

    /**
     * Revoke all user's refresh tokens
     *
     * @param int $userId
     * @return array
     */
    public function revokeAllUserTokens(int $userId): array
    {
        $this->refreshTokenModel->revokeAllUserTokens($userId);

        return ApiResponse::success([], lang('Tokens.allTokensRevoked'));
    }
}
