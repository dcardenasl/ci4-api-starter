<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Forgot Password Request DTO
 */
readonly class ForgotPasswordRequestDTO implements DataTransferObjectInterface
{
    public string $email;

    public function __construct(array $data)
    {
        // REUSE: 'auth.forgot_password' validation
        validateOrFail($data, 'auth', 'forgot_password');

        $this->email = (string) $data['email'];
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
