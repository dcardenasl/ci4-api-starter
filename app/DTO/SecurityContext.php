<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Security Context DTO
 *
 * Encapsulates the identity and permissions of the actor performing an operation.
 * Keeps business DTOs clean of session/auth metadata.
 */
readonly class SecurityContext
{
    public function __construct(
        public ?int $user_id = null,
        public ?string $user_role = null,
        public array $metadata = []
    ) {
    }

    /**
     * Check if the actor is an administrator
     */
    public function isAdmin(): bool
    {
        return in_array($this->user_role, ['admin', 'superadmin'], true);
    }

    /**
     * Check if the actor is a superadmin
     */
    public function isSuperadmin(): bool
    {
        return $this->user_role === 'superadmin';
    }

    /**
     * Check if the context belongs to a specific user
     */
    public function isUser(int $id): bool
    {
        return $this->user_id === $id;
    }

    /**
     * Create an anonymous context
     */
    public static function anonymous(): self
    {
        return new self();
    }
}
