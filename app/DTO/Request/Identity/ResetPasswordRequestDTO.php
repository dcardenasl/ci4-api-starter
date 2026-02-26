<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Reset Password Request DTO
 */
readonly class ResetPasswordRequestDTO implements DataTransferObjectInterface
{
    public string $token;
    public string $email;
    public string $password;

    public function __construct(array $data)
    {
        // REUTILIZACIÓN: Validación 'auth.reset_password'
        validateOrFail($data, 'auth', 'reset_password');

        $this->token = (string) $data['token'];
        $this->email = (string) $data['email'];
        $this->password = (string) $data['password'];
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'email' => $this->email,
            'password' => $this->password,
        ];
    }
}
