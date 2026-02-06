<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Token Controller - Refresh and revoke tokens
 */
class TokenController extends ApiController
{
    protected string $serviceName = 'refreshTokenService';

    public function refresh(): ResponseInterface
    {
        return $this->handleRequest('refreshAccessToken', [
            'refresh_token' => $this->request->getVar('refresh_token'),
        ]);
    }

    public function revoke(): ResponseInterface
    {
        $token = $this->extractToken();
        if (!$token) {
            return $this->respond(['status' => 'error', 'message' => 'Token not found'], 400);
        }

        $jwtService = \Config\Services::jwtService();
        $payload = $jwtService->decode($token);

        if (!$payload || !isset($payload->jti, $payload->exp)) {
            return $this->respond(['status' => 'error', 'message' => 'Invalid token'], 400);
        }

        $tokenRevocationService = \Config\Services::tokenRevocationService();
        $result = $tokenRevocationService->revokeToken($payload->jti, (int) $payload->exp);

        return $this->respond($result, 200);
    }

    public function revokeAll(): ResponseInterface
    {
        $tokenRevocationService = \Config\Services::tokenRevocationService();
        $result = $tokenRevocationService->revokeAllUserTokens($this->getUserId());

        return $this->respond($result, 200);
    }

    protected function extractToken(): ?string
    {
        $header = $this->request->getHeaderLine('Authorization');
        if (empty($header)) {
            return null;
        }

        return preg_match('/Bearer\s+(.*)$/i', $header, $matches) ? $matches[1] : null;
    }
}
