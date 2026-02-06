<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * User Entity
 *
 * Represents a user in the system with computed properties and proper casting.
 */
class UserEntity extends Entity
{
    /**
     * @var array<string, string> Field to attribute mapping
     */
    protected $datamap = [];

    /**
     * @var list<string> Date fields for automatic conversion
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'email_verified_at',
        'verification_token_expires',
    ];

    /**
     * @var array<string, string> Type casting for attributes
     */
    protected $casts = [
        'id'   => 'integer',
        'role' => 'string',
    ];

    /**
     * @var array Fields to hide from serialization
     */
    protected array $hidden = [
        'password',
        'email_verification_token',
        'verification_token_expires',
    ];

    /**
     * Convert entity to array, hiding sensitive fields
     *
     * @param bool $onlyChanged Return only changed fields
     * @param bool $cast        Apply casting
     * @param bool $recursive   Recursively convert nested entities
     * @return array
     */
    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $data = parent::toArray($onlyChanged, $cast, $recursive);

        // Remove hidden fields
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * Convert to array with specific fields only
     *
     * @param array<string> $fields Fields to include
     * @return array
     */
    public function toArrayOnly(array $fields): array
    {
        $data = $this->toArray();
        return array_intersect_key($data, array_flip($fields));
    }

    /**
     * Check if the user's email is verified
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if the user has admin role
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get user initials (first and last name initials)
     *
     * @return string
     */
    public function getInitials(): string
    {
        $first = trim((string) ($this->first_name ?? ''));
        $last = trim((string) ($this->last_name ?? ''));

        if ($first === '' && $last === '') {
            if (!empty($this->email)) {
                return strtoupper(substr($this->email, 0, 2));
            }
            return '';
        }

        $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
        return trim($initials);
    }

    /**
     * Get display name (first/last name or email local part)
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        $first = trim((string) ($this->first_name ?? ''));
        $last = trim((string) ($this->last_name ?? ''));
        $full = trim($first . ' ' . $last);

        if ($full !== '') {
            return $full;
        }

        if (!empty($this->email)) {
            return explode('@', $this->email)[0];
        }

        return '';
    }

    /**
     * Get masked email for display
     *
     * @return string
     */
    public function getMaskedEmail(): string
    {
        if (empty($this->email)) {
            return '';
        }

        // Use helper if available, otherwise basic masking
        if (function_exists('mask_email')) {
            return mask_email($this->email);
        }

        [$local, $domain] = explode('@', $this->email, 2);
        $masked = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 0));
        return $masked . '@' . $domain;
    }

    /**
     * Check if verification token is expired
     *
     * @return bool
     */
    public function isVerificationTokenExpired(): bool
    {
        if (empty($this->verification_token_expires)) {
            return true;
        }

        return strtotime($this->verification_token_expires->format('Y-m-d H:i:s')) < time();
    }

    /**
     * Get the account age in days
     *
     * @return int
     */
    public function getAccountAgeDays(): int
    {
        if (empty($this->created_at)) {
            return 0;
        }

        $created = strtotime($this->created_at->format('Y-m-d H:i:s'));
        return (int) floor((time() - $created) / 86400);
    }

    /**
     * Set password (hash if not already hashed)
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
        if (function_exists('hash_password')) {
            $this->attributes['password'] = hash_password($password);
        } else {
            $this->attributes['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        return $this;
    }

    /**
     * Hash and set a new password
     *
     * Use this method when you explicitly want to hash a password.
     *
     * @param string $plainPassword Plain text password to hash
     * @return $this
     */
    public function hashAndSetPassword(string $plainPassword): self
    {
        if (function_exists('hash_password')) {
            $this->attributes['password'] = hash_password($plainPassword);
        } else {
            $this->attributes['password'] = password_hash($plainPassword, PASSWORD_BCRYPT);
        }

        return $this;
    }
}
