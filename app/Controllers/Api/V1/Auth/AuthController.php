<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Authentication Controller
 */
class AuthController extends ApiController
{
    protected string $serviceName = 'authService';

    public function login(): ResponseInterface
    {
        $dto = $this->getDTO(\App\DTO\Request\Auth\LoginRequestDTO::class);

        return $this->handleRequest(
            fn () => $this->getService()->login($dto)
        );
    }

    public function me(): ResponseInterface
    {
        return $this->handleRequest('me');
    }

    public function googleLogin(): ResponseInterface
    {
        return $this->handleRequest('loginWithGoogleToken');
    }

    protected function resolveSuccessStatus(string $method, array $result): int
    {
        if ($method === 'loginWithGoogleToken') {
            $pendingStatus = $result['data']['user']['status'] ?? null;
            $hasAccessToken = isset($result['data']['access_token']);

            if ($pendingStatus === 'pending_approval' && ! $hasAccessToken) {
                return 202;
            }
        }

        return parent::resolveSuccessStatus($method, $result);
    }
}
