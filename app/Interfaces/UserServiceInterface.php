<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Users\UserIndexRequestDTO;
use App\DTO\Response\Users\UserResponseDTO;

/**
 * User Service Interface
 *
 * Defines the contract for user CRUD operations.
 * Authentication methods have been moved to AuthServiceInterface.
 */
interface UserServiceInterface
{
    /**
     * Get all users with pagination and filtering
     */
    public function index(UserIndexRequestDTO $request): array;

    /**
     * Get a single user by ID
     */
    public function show(array $data): UserResponseDTO;

    /**
     * Create a new user (Admin only)
     */
    public function store(array $data): UserResponseDTO;

    /**
     * Update an existing user
     */
    public function update(array $data): UserResponseDTO;

    /**
     * Delete a user (Soft delete)
     */
    public function destroy(array $data): array;

    /**
     * Approve a pending user
     */
    public function approve(array $data): UserResponseDTO;
}
