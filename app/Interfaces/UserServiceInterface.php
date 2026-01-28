<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * User Service Interface
 *
 * Defines the contract for user-related business logic operations.
 * Implementations must provide CRUD operations and authentication methods.
 */
interface UserServiceInterface
{
    /**
     * Get all users
     *
     * @param array $data Request data
     * @return array Result with list of users
     */
    public function index(array $data): array;

    /**
     * Get a specific user by ID
     *
     * @param array $data Request data containing 'id'
     * @return array Result with user data or errors
     */
    public function show(array $data): array;

    /**
     * Create a new user
     *
     * @param array $data User data (email, username)
     * @return array Result with created user data or errors
     */
    public function store(array $data): array;

    /**
     * Update an existing user
     *
     * @param array $data User data including 'id'
     * @return array Result with updated user data or errors
     */
    public function update(array $data): array;

    /**
     * Delete a user (soft delete)
     *
     * @param array $data Request data containing 'id'
     * @return array Result with success message or errors
     */
    public function destroy(array $data): array;

    /**
     * Authenticate user with credentials
     *
     * @param array $data Login credentials (username/email, password)
     * @return array Result with user data or errors
     */
    public function login(array $data): array;

    /**
     * Register a new user with password
     *
     * @param array $data Registration data (username, email, password)
     * @return array Result with created user data or errors
     */
    public function register(array $data): array;

    /**
     * Authenticate user and return JWT token
     *
     * @param array $data Login credentials
     * @return array Result with token and user data, or errors
     */
    public function loginWithToken(array $data): array;

    /**
     * Register new user and return JWT token
     *
     * @param array $data Registration data
     * @return array Result with token and user data, or errors
     */
    public function registerWithToken(array $data): array;
}
