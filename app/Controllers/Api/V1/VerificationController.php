<?php

namespace App\Controllers\Api\V1;

use App\Controllers\ApiController;
use App\Services\VerificationService;
use CodeIgniter\HTTP\ResponseInterface;

class VerificationController extends ApiController
{
    protected VerificationService $verificationService;

    public function __construct()
    {
        $this->verificationService = new VerificationService();
    }

    /**
     * Get the service instance
     *
     * @return object
     */
    protected function getService(): object
    {
        return $this->verificationService;
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
     * Verify email with token
     *
     * POST /api/v1/auth/verify-email
     *
     * @return ResponseInterface
     */
    public function verify(): ResponseInterface
    {
        return $this->handleRequest('verifyEmail');
    }

    /**
     * Resend verification email
     *
     * POST /api/v1/auth/resend-verification
     * Requires authentication
     *
     * @return ResponseInterface
     */
    public function resend(): ResponseInterface
    {
        $userId = $this->request->userId ?? null;

        if (! $userId) {
            return $this->respondUnauthorized('Authentication required');
        }

        $result = $this->verificationService->resendVerification($userId);

        return $this->respond($result, $result['status_code'] ?? 200);
    }
}
