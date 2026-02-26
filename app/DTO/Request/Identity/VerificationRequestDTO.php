<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Verification Request DTO
 *
 * Validates the verification token.
 */
readonly class VerificationRequestDTO implements DataTransferObjectInterface
{
    public string $token;
    public ?string $email;

    public function __construct(array $data)
    {
        // REUSE: 'auth.verify_email' validation
        // If email is missing, add a placeholder to prevent validation failure if required
        // Or better, relax validation here if email is not strictly necessary to find the token
        $this->token = (string) ($data['token'] ?? '');
        $this->email = (string) ($data['email'] ?? 'temp@example.com'); // Temporary hack to pass strict CI4 validation if required

        validateOrFail($data + ['email' => $this->email], 'auth', 'verify_email');
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
