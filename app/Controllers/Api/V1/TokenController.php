<?php

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Models\RefreshTokenModel;
use App\Models\TokenBlacklistModel;
use App\Services\RefreshTokenService;
use App\Services\TokenRevocationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Token Controller
 *
 * Handles token refresh and revocation
 */
class TokenController extends ApiController
{
    protected RefreshTokenService $refreshTokenService;
    protected TokenRevocationService $tokenRevocationService;

    public function __construct()
    {
        $refreshTokenModel = new RefreshTokenModel();
        $this->refreshTokenService = new RefreshTokenService($refreshTokenModel);

        $blacklistModel = new TokenBlacklistModel();
        $this->tokenRevocationService = new TokenRevocationService(
            $blacklistModel,
            $refreshTokenModel
        );
    }

    /**
     * Get the service instance (not used for this controller)
     *
     * @return object
     */
    protected function getService(): object
    {
        return $this->refreshTokenService;
    }

    /**
     * Get success status code
     *
     * @param string $method
     * @return int
     */
    protected function getSuccessStatus(string $method): int
    {
        return 200;
    }

    /**
     * Refresh access token using refresh token
     *
     * POST /api/v1/auth/refresh
     *
     * @return ResponseInterface
     */
    public function refresh(): ResponseInterface
    {
        $data = [
            'refresh_token' => $this->request->getVar('refresh_token'),
        ];

        $result = $this->refreshTokenService->refreshAccessToken($data);

        $statusCode = isset($result['errors']) ? 401 : 200;

        return $this->response
            ->setJSON($result)
            ->setStatusCode($statusCode);
    }

    /**
     * Revoke current access token
     *
     * POST /api/v1/auth/revoke
     *
     * @return ResponseInterface
     */
    public function revoke(): ResponseInterface
    {
        // Get JWT payload from request (set by JwtAuthFilter)
        $jwtService = \Config\Services::jwtService();
        $token = $this->extractToken();

        if (!$token) {
            return $this->response
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Token not found',
                ])
                ->setStatusCode(400);
        }

        $payload = $jwtService->decode($token);

        if (!$payload || !isset($payload->jti) || !isset($payload->exp)) {
            return $this->response
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid token',
                ])
                ->setStatusCode(400);
        }

        $result = $this->tokenRevocationService->revokeToken(
            $payload->jti,
            (int) $payload->exp
        );

        return $this->response
            ->setJSON($result)
            ->setStatusCode(200);
    }

    /**
     * Revoke all user tokens
     *
     * POST /api/v1/auth/revoke-all
     *
     * @return ResponseInterface
     */
    public function revokeAll(): ResponseInterface
    {
        $userId = $this->request->userId;

        $result = $this->tokenRevocationService->revokeAllUserTokens($userId);

        return $this->response
            ->setJSON($result)
            ->setStatusCode(200);
    }

    /**
     * Extract JWT token from Authorization header
     *
     * @return string|null
     */
    protected function extractToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');

        if (empty($header)) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
