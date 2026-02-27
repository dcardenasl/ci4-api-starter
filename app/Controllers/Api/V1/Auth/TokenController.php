<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Controllers\ApiController;
use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized Token Controller
 */
class TokenController extends ApiController
{
    protected string $serviceName = 'authTokenService';

    /**
     * Refresh access token using refresh token
     */
    public function refresh(): ResponseInterface
    {
        return $this->handleRequest('refreshAccessToken', RefreshTokenRequestDTO::class);
    }

    /**
     * Revoke current access token
     */
    public function revoke(): ResponseInterface
    {
        return $this->handleRequest(function ($dto, $context) {
            return $this->getService()->revokeToken(
                $this->request->getHeaderLine('Authorization'),
                $context
            );
        });
    }

    /**
     * Revoke all tokens for the current user
     */
    public function revokeAll(): ResponseInterface
    {
        return $this->handleRequest('revokeAllUserTokens');
    }
}
