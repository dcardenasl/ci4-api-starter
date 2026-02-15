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
        $tokenRevocationService = \Config\Services::tokenRevocationService();

        try {
            $result = $tokenRevocationService->revokeAccessToken([
                'authorization_header' => $this->request->getHeaderLine('Authorization'),
            ]);

            return $this->respond($result, 200);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    public function revokeAll(): ResponseInterface
    {
        $tokenRevocationService = \Config\Services::tokenRevocationService();
        $result = $tokenRevocationService->revokeAllUserTokens($this->getUserId());

        return $this->respond($result, 200);
    }
}
