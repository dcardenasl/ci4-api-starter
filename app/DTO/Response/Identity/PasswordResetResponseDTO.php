<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Password Reset Response DTO
 */
readonly class PasswordResetResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public string $message,
        public bool $success = true
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: (string) ($data['message'] ?? lang('Auth.passwordResetSuccess'))
        );
    }

    public function toArray(): array
    {
        return [
            'status' => 'success',
            'message' => $this->message,
        ];
    }
}
