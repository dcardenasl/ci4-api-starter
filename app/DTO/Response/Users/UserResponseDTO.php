<?php

declare(strict_types=1);

namespace App\DTO\Response\Users;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * User Response DTO
 *
 * Standardized output for user data.
 */
#[OA\Schema(
    schema: 'UserResponse',
    title: 'User Response',
    description: 'User data returned by the API',
    required: ['id', 'email', 'role', 'status']
)]
readonly class UserResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique user identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'User email address', example: 'user@example.com')]
        public string $email,
        #[OA\Property(property: 'firstName', description: 'User first name', example: 'John', nullable: true)]
        public string $firstName,
        #[OA\Property(property: 'lastName', description: 'User last name', example: 'Doe', nullable: true)]
        public string $lastName,
        #[OA\Property(description: 'User role', example: 'user', enum: ['user', 'admin', 'superadmin'])]
        public string $role,
        #[OA\Property(description: 'Account status', example: 'active', enum: ['pending_approval', 'active', 'invited'])]
        public string $status,
        #[OA\Property(property: 'avatarUrl', description: 'URL to user avatar', example: 'https://example.com/avatar.png', nullable: true)]
        public ?string $avatarUrl = null,
        #[OA\Property(property: 'createdAt', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $createdAt = null,
        #[OA\Property(property: 'updatedAt', description: 'Last update timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $updatedAt = null
    ) {
    }

    public static function fromArray(array $data): self
    {
        $createdAt = $data['created_at'] ?? null;
        $updatedAt = $data['updated_at'] ?? null;

        if ($createdAt instanceof \DateTimeInterface) {
            $createdAt = $createdAt->format('Y-m-d H:i:s');
        }
        if ($updatedAt instanceof \DateTimeInterface) {
            $updatedAt = $updatedAt->format('Y-m-d H:i:s');
        }

        return new self(
            id: (int) ($data['id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            firstName: (string) ($data['first_name'] ?? ''),
            lastName: (string) ($data['last_name'] ?? ''),
            role: (string) ($data['role'] ?? 'user'),
            status: (string) ($data['status'] ?? 'pending'),
            avatarUrl: isset($data['avatar_url']) ? (string) $data['avatar_url'] : null,
            createdAt: $createdAt ? (string) $createdAt : null,
            updatedAt: $updatedAt ? (string) $updatedAt : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'role' => $this->role,
            'status' => $this->status,
            'avatarUrl' => $this->avatarUrl,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
