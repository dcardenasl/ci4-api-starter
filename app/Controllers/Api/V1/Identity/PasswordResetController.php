<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Identity;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Password Reset Controller
 */
class PasswordResetController extends ApiController
{
    protected string $serviceName = 'passwordResetService';

    public function sendResetLink(): ResponseInterface
    {
        $email = $this->request->getVar('email') ?? '';
        $dto = new \App\DTO\Request\Identity\ForgotPasswordRequestDTO(['email' => $email]);

        return $this->handleRequest(
            fn () => $this->getService()->sendResetLink($dto)
        );
    }

    public function validateToken(): ResponseInterface
    {
        $token = $this->request->getGet('token') ?? '';
        $email = $this->request->getGet('email') ?? '';

        return $this->handleRequest(
            fn () => $this->getService()->validateToken([
                'token' => $token,
                'email' => $email,
            ])
        );
    }

    public function resetPassword(): ResponseInterface
    {
        $dto = $this->getDTO(\App\DTO\Request\Identity\ResetPasswordRequestDTO::class);

        return $this->handleRequest(
            fn () => $this->getService()->resetPassword($dto)
        );
    }
}
