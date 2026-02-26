<?php

declare(strict_types=1);

namespace App\DTO\Response\Auth;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Register Response DTO
 *
 * Ensures the API contract is respected for registration responses.
 */
readonly class RegisterResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public int $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $role,
        public string $status,
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
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'role' => $this->role,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
