<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Authentication Controller
 */
class AuthController extends ApiController
{
    protected string $serviceName = 'authService';

    protected array $statusCodes = [
        'register' => 201,
    ];

    public function login(): ResponseInterface
    {
        return $this->handleRequest('loginWithToken');
    }

    public function register(): ResponseInterface
    {
        return $this->handleRequest('register');
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
