<?php

declare(strict_types=1);

namespace App\Interfaces\Users;

use App\DTO\SecurityContext;

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
    public function index(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Get a single user by ID
     */
    public function show(int $id, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Create a new user (Admin only)
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Update an existing user
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Delete a user (Soft delete)
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool;

    /**
     * Approve a pending user
     */
    public function approve(int $id, ?SecurityContext $context = null, ?string $clientBaseUrl = null): \App\Interfaces\DataTransferObjectInterface;
}
