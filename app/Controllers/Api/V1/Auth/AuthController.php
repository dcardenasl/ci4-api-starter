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
        return $this->handleRequest('loginWithToken');
    }

    public function me(): ResponseInterface
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return $this->respondUnauthorized(lang('Users.auth.notAuthenticated'));
        }

        $userService = \Config\Services::userService();
        $result = $userService->show(['id' => $userId]);

        return $this->respond($result, 200);
    }
}
