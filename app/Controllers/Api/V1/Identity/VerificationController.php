<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Verification Controller - Email verification
 */
class VerificationController extends ApiController
{
    protected string $serviceName = 'verificationService';

    public function verify(): ResponseInterface
    {
        $token = $this->request->getVar('token') ?? '';
        $dto = new \App\DTO\Request\Identity\VerificationRequestDTO(['token' => $token]);

        return $this->handleRequest(
            fn () => $this->getService()->verifyEmail($dto)
        );
    }

    public function resend(): ResponseInterface
    {
        $userId = $this->getUserId();

        if (!$userId) {
            return $this->respondUnauthorized(lang('Auth.authRequired'));
        }

        return $this->handleRequest('resendVerification', [
            'user_id' => $userId,
        ]);
    }
}
