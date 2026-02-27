<?php

declare(strict_types=1);

namespace App\Interfaces\Tokens;

/**
 * JWT Service Interface
 *
 * Contract for JWT token encoding and decoding functionality
 */
interface JwtServiceInterface
{
    /**
     * Generate a JWT token with JTI (unique identifier)
     *
     * @param int $userId
     * @param string $role
     * @return string
     */
    public function encode(int $userId, string $role): string;

    /**
     * Decode and validate a JWT token
     *
     * @param string $token
     * @return object|null
     */
    public function decode(string $token): ?object;

    /**
     * Validate if a token is valid
     *
     * @param string $token
     * @return bool
     */
    public function validate(string $token): bool;

    /**
     * Extract user ID from token
     *
     * @param string $token
     * @return int|null
     */
    public function getUserId(string $token): ?int;

    /**
     * Extract role from token
     *
     * @param string $token
     * @return string|null
     */
    public function getRole(string $token): ?string;
}
