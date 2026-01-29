<?php

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Services\PasswordResetService;
use CodeIgniter\HTTP\ResponseInterface;

class PasswordResetController extends ApiController
{
    protected PasswordResetService $passwordResetService;

    public function __construct()
    {
        $this->passwordResetService = new PasswordResetService();
    }

    /**
     * Get the service instance
     *
     * @return object
     */
    protected function getService(): object
    {
        return $this->passwordResetService;
    }

    /**
     * Get success status code for method
     *
     * @param string $method
     * @return int
     */
    protected function getSuccessStatus(string $method): int
    {
        return 200;
    }

    /**
     * Send password reset link
     *
     * POST /api/v1/auth/forgot-password
     * Body: { "email": "user@example.com" }
     *
     * @return ResponseInterface
     */
    public function sendResetLink(): ResponseInterface
    {
        return $this->handleRequest('sendResetLink');
    }

    /**
     * Validate reset token
     *
     * GET /api/v1/auth/validate-reset-token
     * Query: ?token=xxx&email=user@example.com
     *
     * @return ResponseInterface
     */
    public function validateToken(): ResponseInterface
    {
        $token = $this->request->getGet('token') ?? '';
        $email = $this->request->getGet('email') ?? '';

        $result = $this->passwordResetService->validateToken($token, $email);

        return $this->respond($result, $result['status_code'] ?? 200);
    }

    /**
     * Reset password
     *
     * POST /api/v1/auth/reset-password
     * Body: { "token": "xxx", "email": "user@example.com", "password": "newpass" }
     *
     * @return ResponseInterface
     */
    public function resetPassword(): ResponseInterface
    {
        $data = $this->getJsonData();

        $result = $this->passwordResetService->resetPassword(
            $data['token'] ?? '',
            $data['email'] ?? '',
            $data['password'] ?? ''
        );

        return $this->respond($result, $result['status_code'] ?? 200);
    }
}
