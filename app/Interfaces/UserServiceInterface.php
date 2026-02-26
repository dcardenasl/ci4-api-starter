<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * User Service Interface
 *
 * Defines the contract for user CRUD operations with strict typing.
 */
interface UserServiceInterface
{
    /**
     * Get all users with pagination and filtering
     */
    public function index(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Get a single user by ID
     */
    public function show(int $id): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Create a new user (Admin only)
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Update an existing user
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Delete a user (Soft delete)
     */
    public function destroy(int $id): array;

    /**
     * Approve a pending user
     */
    public function approve(int $id, ?int $adminId = null, ?string $clientBaseUrl = null): \App\Interfaces\DataTransferObjectInterface;
}
