<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

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
        return $this->handleRequest('sendResetLink', ['email' => $email]);
    }

    public function validateToken(): ResponseInterface
    {
        $token = $this->request->getGet('token') ?? '';
        $email = $this->request->getGet('email') ?? '';

        return $this->handleRequest('validateToken', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(): ResponseInterface
    {
        $data = $this->getJsonData();

        return $this->handleRequest('resetPassword', [
            'token'    => $data['token'] ?? '',
            'email'    => $data['email'] ?? '',
            'password' => $data['password'] ?? '',
        ]);
    }
}
