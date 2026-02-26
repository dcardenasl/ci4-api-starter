<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Auth\LoginRequestDTO;
use App\DTO\Request\Auth\RegisterRequestDTO;
use App\DTO\Response\Auth\LoginResponseDTO;
use App\DTO\Response\Auth\RegisterResponseDTO;

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
     * @param LoginRequestDTO $request Login credentials
     * @return LoginResponseDTO Result with tokens and user data
     */
    public function login(LoginRequestDTO $request): LoginResponseDTO;

    /**
     * Authenticate user and return JWT token with refresh token
     *
     * @param array $data Login credentials
     * @return array Result with access token, refresh token, and user data
     */
    public function loginWithToken(array $data): array;

    /**
     * Authenticate user with Google ID token and return JWT tokens
     *
     * @param array $data Login data with id_token
     * @return array Result with tokens or pending-approval payload
     */
    public function loginWithGoogleToken(array $data): array;

    /**
     * Get the current authenticated user profile.
     *
     * @param array $data Request data with user_id
     * @return array
     */
    public function me(array $data): array;

    /**
     * Register a new user with password
     *
     * @param RegisterRequestDTO $request Registration data
     * @return RegisterResponseDTO Result with created user data
     */
    public function register(RegisterRequestDTO $request): RegisterResponseDTO;

    /**
     * Register new user (legacy method, no tokens returned)
     *
     * @param array $data Registration data
     * @return array Result with created user data
     */
    public function registerWithToken(array $data): array;
}
