<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Verification Response DTO
 */
readonly class VerificationResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public string $message,
        public int $userId,
        public string $email,
        public string $verifiedAt
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: (string) ($data['message'] ?? lang('Verification.success')),
            userId: (int) $data['user_id'],
            email: (string) $data['email'],
            verifiedAt: (string) $data['verified_at']
        );
    }

    public function toArray(): array
    {
        return [
            'status' => 'success',
            'message' => $this->message,
            'userId' => $this->userId,
            'email' => $this->email,
            'verifiedAt' => $this->verifiedAt,
        ];
    }
}
