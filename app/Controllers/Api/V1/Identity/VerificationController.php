<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\Controllers\ApiController;
use App\DTO\Request\Identity\VerificationRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized Verification Controller
 */
class VerificationController extends ApiController
{
    protected string $serviceName = 'verificationService';

    /**
     * Verify email with token
     */
    public function verify(): ResponseInterface
    {
        return $this->handleRequest('verifyEmail', VerificationRequestDTO::class);
    }

    /**
     * Resend verification email
     */
    public function resend(): ResponseInterface
    {
        return $this->handleRequest(function () {
            return $this->getService()->resendVerification($this->getUserId());
        });
    }
}
