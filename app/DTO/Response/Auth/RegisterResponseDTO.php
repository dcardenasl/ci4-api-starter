<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Register Response DTO
 *
 * Ensures the API contract is respected for registration responses.
 */
#[OA\Schema(
    schema: 'RegisterResponse',
    title: 'Register Response',
    description: 'User data returned after registration',
    required: ['id', 'email', 'firstName', 'lastName', 'role', 'status']
)]
readonly class RegisterResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(description: 'Unique user identifier', example: 1)]
        public int $id,
        #[OA\Property(description: 'User email address', example: 'newuser@example.com')]
        public string $email,
        #[OA\Property(property: 'firstName', description: 'User first name', example: 'Alex')]
        public string $firstName,
        #[OA\Property(property: 'lastName', description: 'User last name', example: 'Doe')]
        public string $lastName,
        #[OA\Property(description: 'User role', example: 'user', enum: ['user', 'admin', 'superadmin'])]
        public string $role,
        #[OA\Property(description: 'Account status', example: 'pending_approval', enum: ['pending_approval', 'active', 'invited'])]
        public string $status,
        #[OA\Property(property: 'createdAt', description: 'Creation timestamp', example: '2026-02-26 12:00:00', nullable: true)]
        public ?string $createdAt = null
    ) {
    }

    /**
     * @param array $data Data from UserModel/Entity
     */
    public static function fromArray(array $data): self
    {
        $createdAt = $data['created_at'] ?? null;

        // Normalize date to string if it's an object (CI4 Time or DateTime)
        if ($createdAt instanceof \DateTimeInterface) {
            $createdAt = $createdAt->format('Y-m-d H:i:s');
        }

        return new self(
            id: (int) $data['id'],
            email: $data['email'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            role: $data['role'],
            status: $data['status'],
            createdAt: $createdAt ? (string) $createdAt : null
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
            'createdAt' => $this->createdAt,
        ];
    }
}
