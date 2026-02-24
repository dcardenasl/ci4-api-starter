<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Token Controller - Refresh and revoke tokens
 */
class TokenController extends ApiController
{
    protected string $serviceName = 'authTokenService';

    public function refresh(): ResponseInterface
    {
        return $this->handleRequest('refreshAccessToken', [
            'refresh_token' => $this->request->getVar('refresh_token'),
        ]);
    }

    public function revoke(): ResponseInterface
    {
        return $this->handleRequest('revoke', [
            'authorization_header' => $this->request->getHeaderLine('Authorization'),
        ]);
    }

    public function revokeAll(): ResponseInterface
    {
        return $this->handleRequest('revokeAll');
    }
}
