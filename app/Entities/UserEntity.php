<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * User Entity
 *
 * Represents a single user in the system.
 * Handles data mapping and business logic for user attributes.
 */
class UserEntity extends Entity
{
    /**
     * Define map for attributes
     *
     * @var array<string, string>
     */
    protected $datamap = [
        'firstName' => 'first_name',
        'lastName'  => 'last_name',
        'avatarUrl' => 'avatar_url',
    ];

    /**
     * Define date fields
     *
     * @var list<string>
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'approved_at',
        'invited_at',
        'last_login_at',
    ];

    /**
     * Define attribute types
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id'          => 'integer',
        'is_active'   => 'boolean',
        'approved_by' => 'integer',
        'invited_by'  => 'integer',
    ];

    /**
     * Get user full name
     */
    public function getFullName(): string
    {
        $firstName = (string) ($this->attributes['first_name'] ?? '');
        $lastName  = (string) ($this->attributes['last_name'] ?? '');

        return trim($firstName . ' ' . $lastName);
    }

    /**
     * Check if user is an admin or superadmin
     */
    public function isAdmin(): bool
    {
        $role = (string) ($this->attributes['role'] ?? '');

        return in_array($role, ['admin', 'superadmin'], true);
    }

    /**
     * Check if user is a superadmin
     */
    public function isSuperAdmin(): bool
    {
        return ($this->attributes['role'] ?? '') === 'superadmin';
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }

    /**
     * Check if user email is verified
     */
    public function isVerified(): bool
    {
        return ($this->attributes['status'] ?? '') === 'active';
    }

    /**
     * Get a displayable name for the user
     */
    public function getDisplayName(): string
    {
        $name = $this->getFullName();

        return ($name !== '') ? $name : (string) ($this->attributes['email'] ?? '');
    }

    /**
     * Password Mutator
     *
     * This is a mutator that automatically hashes plain text passwords.
     * If the password is already hashed (bcrypt format), it's stored as-is.
     *
     * @param string $password Plain text or already hashed password
     * @return $this
     */
    public function setPassword(string $password): self
    {
        // Check if password is already hashed (bcrypt starts with $2y$, $2a$, or $2b$)
        if (preg_match('/^\$2[ayb]\$/', $password)) {
            $this->attributes['password'] = $password;
            return $this;
        }

        // Hash plain text password
        $this->attributes['password'] = password_hash($password, PASSWORD_BCRYPT);

        return $this;
    }

    /**
     * Get avatar URL or default fallback
     */
    public function getAvatarUrl(): string
    {
        return (string) ($this->attributes['avatar_url'] ?? '');
    }
}
