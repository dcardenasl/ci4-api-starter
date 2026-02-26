<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Interfaces\JwtServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
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
        protected UserModel $userModel,
        protected ?UserAccessPolicyService $userAccessPolicy = null
    ) {
        $this->userAccessPolicy ??= \Config\Services::userAccessPolicyService();
    }

    /**
     * Issue a new refresh token
     */
    public function issueRefreshToken(int $userId): string
    {
        // Generate secure random token
        $token = generate_token();

        // Calculate expiry
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
     */
    public function refreshAccessToken(\App\DTO\Request\Identity\RefreshTokenRequestDTO $request): \App\DTO\Response\Identity\TokenResponseDTO
    {
        $refreshToken = $request->refreshToken;
        $db = \Config\Database::connect();

        // Start transaction to protect token rotation
        $db->transStart();

        // Validate refresh token with row-level lock (FOR UPDATE)
        $builder = $db->table('refresh_tokens');
        $sql = $builder
            ->where('token', $refreshToken)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->where('revoked_at', null)
            ->getCompiledSelect();

        $sql .= ' FOR UPDATE';

        $result = $db->query($sql);
        $tokenRecord = null;
        if ($result instanceof \CodeIgniter\Database\ResultInterface) {
            /** @var object|null $tokenRecord */
            $tokenRecord = $result->getFirstRow('object');
        }

        if (!$tokenRecord || !isset($tokenRecord->user_id)) {
            $db->transRollback();
            throw new AuthenticationException(lang('Tokens.invalidRefreshToken'));
        }

        // Revoke old refresh token (token rotation)
        $this->refreshTokenModel->revokeToken($refreshToken);

        // Issue new refresh token
        $newRefreshToken = $this->issueRefreshToken((int) $tokenRecord->user_id);

        // Get user to get role
        $user = $this->userModel->find($tokenRecord->user_id);

        if (!$user instanceof \App\Entities\UserEntity) {
            $db->transRollback();
            throw new AuthenticationException(lang('Tokens.userNotFound'));
        }

        try {
            $this->userAccessPolicy->assertCanAuthenticate($user);
        } catch (AuthenticationException | AuthorizationException $e) {
            $db->transRollback();
            throw $e;
        }

        $accessToken = $this->jwtService->encode((int) $user->id, (string) ($user->role ?? 'user'));

        // Commit transaction
        $db->transComplete();

        return \App\DTO\Response\Identity\TokenResponseDTO::fromArray([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'expires_in' => (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600)),
        ]);
    }

    /**
     * Revoke a refresh token
     */
    public function revoke(array $data): array
    {
        $revoked = $this->refreshTokenModel->revokeToken($data['refresh_token'] ?? '');

        if (!$revoked) {
            throw new \App\Exceptions\NotFoundException(lang('Tokens.tokenNotFound'));
        }

        return ['status' => 'success', 'message' => lang('Tokens.refreshTokenRevoked')];
    }

    /**
     * Revoke all user's refresh tokens
     */
    public function revokeAllUserTokens(int $userId): array
    {
        $this->refreshTokenModel->revokeAllUserTokens($userId);

        return ['status' => 'success', 'message' => lang('Tokens.allTokensRevoked')];
    }
}
