<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Controllers\ApiController;
use App\DTO\Request\Auth\GoogleLoginRequestDTO;
use App\DTO\Request\Auth\LoginRequestDTO;
use App\DTO\Request\Auth\RegisterRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized Authentication Controller
 *
 * Uses automated DTO validation and standardized request handling.
 */
class AuthController extends ApiController
{
    protected string $serviceName = 'authService';

    /**
     * Authenticate with email/password
     */
    public function login(): ResponseInterface
    {
        return $this->handleRequest('login', LoginRequestDTO::class);
    }

    /**
     * Register a new user
     */
    public function register(): ResponseInterface
    {
        return $this->handleRequest('register', RegisterRequestDTO::class);
    }

    /**
     * Authenticate with Google ID Token
     */
    public function googleLogin(): ResponseInterface
    {
        return $this->handleRequest(function ($dto) {
            $result = $this->getService()->loginWithGoogleToken($dto);

            // Explicitly detect 202 status for pending approval
            $status = 200;
            if (isset($result['message']) && (str_contains($result['message'], 'pending') || str_contains($result['message'], 'pendiente'))) {
                $status = 202;
            }

            return $this->respond($result, $status);
        }, GoogleLoginRequestDTO::class);
    }

    /**
     * Get current user profile
     */
    public function me(): ResponseInterface
    {
        return $this->handleRequest(function () {
            return $this->getService()->me($this->getUserId());
        });
    }
}
