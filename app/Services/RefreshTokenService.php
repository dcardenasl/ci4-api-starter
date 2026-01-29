<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\ApiResponse;
use App\Models\RefreshTokenModel;

/**
 * Refresh Token Service
 *
 * Manages refresh token lifecycle (issue, refresh, revoke)
 */
class RefreshTokenService
{
    protected RefreshTokenModel $refreshTokenModel;

    public function __construct(RefreshTokenModel $refreshTokenModel)
    {
        $this->refreshTokenModel = $refreshTokenModel;
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
        $ttl = (int) env('JWT_REFRESH_TOKEN_TTL', 604800); // 7 days default
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
     * @param array $data Request data with 'refresh_token'
     * @return array
     */
    public function refreshAccessToken(array $data): array
    {
        if (empty($data['refresh_token'])) {
            return ApiResponse::error(
                ['refresh_token' => 'Refresh token is required'],
                'Invalid request'
            );
        }

        $refreshToken = $data['refresh_token'];

        // Validate refresh token
        $tokenRecord = $this->refreshTokenModel->getActiveToken($refreshToken);

        if (!$tokenRecord) {
            return ApiResponse::error(
                ['refresh_token' => 'Invalid or expired refresh token'],
                'Unauthorized',
                401
            );
        }

        // Revoke old refresh token (token rotation)
        $this->refreshTokenModel->revokeToken($refreshToken);

        // Issue new refresh token
        $newRefreshToken = $this->issueRefreshToken((int) $tokenRecord->user_id);

        // Generate new access token
        $jwtService = \Config\Services::jwtService();

        // Get user to get role
        $userModel = new \App\Models\UserModel();
        $user = $userModel->find($tokenRecord->user_id);

        if (!$user) {
            return ApiResponse::error(
                ['user' => 'User not found'],
                'Unauthorized',
                401
            );
        }

        $accessToken = $jwtService->encode((int) $user->id, $user->role);

        return ApiResponse::success([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => (int) env('JWT_ACCESS_TOKEN_TTL', 3600),
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
            return ApiResponse::error(
                ['refresh_token' => 'Refresh token is required'],
                'Invalid request'
            );
        }

        $revoked = $this->refreshTokenModel->revokeToken($data['refresh_token']);

        if (!$revoked) {
            return ApiResponse::error(
                ['refresh_token' => 'Token not found'],
                'Not found',
                404
            );
        }

        return ApiResponse::success(null, 'Refresh token revoked successfully');
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

        return ApiResponse::success(null, 'All refresh tokens revoked successfully');
    }
}
