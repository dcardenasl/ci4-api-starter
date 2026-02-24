<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * User Service Interface
 *
 * Defines the contract for user CRUD operations.
 * Authentication methods have been moved to AuthServiceInterface.
 */
interface UserServiceInterface extends CrudServiceContract
{
    /**
     * Get all users
     *
     * @param array $data Request data
     * @return array Result with list of users
     */
    /**
     * Approve a pending user
     *
     * @param array $data Request data containing 'id'
     * @return array Result with approved user data
     */
    public function approve(array $data): array;
}
