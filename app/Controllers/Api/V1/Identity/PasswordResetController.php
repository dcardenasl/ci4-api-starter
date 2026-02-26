<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\Controllers\ApiController;
use App\DTO\Request\Identity\ForgotPasswordRequestDTO;
use App\DTO\Request\Identity\PasswordResetTokenValidationDTO;
use App\DTO\Request\Identity\ResetPasswordRequestDTO;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Modernized Password Reset Controller
 */
class PasswordResetController extends ApiController
{
    protected string $serviceName = 'passwordResetService';

    /**
     * Send reset link to email
     */
    public function sendResetLink(): ResponseInterface
    {
        return $this->handleRequest('sendResetLink', ForgotPasswordRequestDTO::class);
    }

    public function validateToken(): ResponseInterface
    {
        return $this->handleRequest(
            'validateToken',
            PasswordResetTokenValidationDTO::class,
            (array) $this->request->getGet()
        );
    }
    /**
     * Reset password using token
     */
    public function resetPassword(): ResponseInterface
    {
        return $this->handleRequest('resetPassword', ResetPasswordRequestDTO::class);
    }
}
