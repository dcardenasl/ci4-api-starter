<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use dcardenasl\Ci4ApiCore\Dto\SecurityContext;
use dcardenasl\Ci4ApiCore\Support\OperationResult;

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
    public function login(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Authenticate user with Google ID token
     */
    public function loginWithGoogleToken(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): OperationResult;

    /**
     * Get the current authenticated user profile
     */
    public function me(int $user_id, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Register a new user with password
     */
    public function register(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;

    /**
     * Update the authenticated user's own profile (allowlist: first_name, last_name, avatar_url).
     */
    public function updateMe(\dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface $request, ?SecurityContext $context = null): \dcardenasl\Ci4ApiCore\Dto\DataTransferObjectInterface;
}
