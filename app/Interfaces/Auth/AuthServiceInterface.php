<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use App\DTO\SecurityContext;
use App\Support\OperationResult;

/**
 * Modernized Authentication Service Interface
 *
 * Enforces strict typing with self-validating DTOs.
 */
interface AuthServiceInterface
{
    /**
     * Authenticate user with credentials
     */
    public function login(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Authenticate user with Google ID token
     */
    public function loginWithGoogleToken(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): OperationResult;

    /**
     * Get the current authenticated user profile
     */
    public function me(int $userId, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Register a new user with password
     */
    public function register(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface;
}
