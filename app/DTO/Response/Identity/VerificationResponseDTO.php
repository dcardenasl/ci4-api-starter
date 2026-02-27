<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Verification Response DTO
 */
#[OA\Schema(
    schema: 'VerificationResponse',
    title: 'Verification Response',
    description: 'Email verification result',
    required: ['message', 'userId', 'email', 'verifiedAt']
)]
readonly class VerificationResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Result message', example: 'Email verified successfully')]
        public string $message,
        #[OA\Property(property: 'userId', description: 'Verified user id', example: 1)]
        public int $userId,
        #[OA\Property(description: 'Verified email', example: 'user@example.com')]
        public string $email,
        #[OA\Property(property: 'verifiedAt', description: 'Verification timestamp', example: '2026-02-26 12:00:00')]
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
