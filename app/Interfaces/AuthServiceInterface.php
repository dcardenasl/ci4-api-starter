<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Authentication Service Interface
 *
 * Contract for authentication and registration operations
 */
interface AuthServiceInterface
{
    /**
     * Authenticate user with credentials
     *
     * @param array $data Login credentials (email, password)
     * @return array Result with user data or errors
     */
    public function login(array $data): array;

    /**
     * Authenticate user and return JWT token with refresh token
     *
     * @param array $data Login credentials
     * @return array Result with access token, refresh token, and user data
     */
    public function loginWithToken(array $data): array;

    /**
     * Register a new user with password
     *
     * @param array $data Registration data (email, password, names)
     * @return array Result with created user data or errors
     */
    public function register(array $data): array;

    /**
     * Register new user (legacy method, no tokens returned)
     *
     * @param array $data Registration data
     * @return array Result with created user data
     */
    public function registerWithToken(array $data): array;
}
