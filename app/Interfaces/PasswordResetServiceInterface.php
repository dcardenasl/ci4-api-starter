<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\SecurityContext;

/**
 * Password Reset Service Interface
 */
interface PasswordResetServiceInterface
{
    /**
     * Send password reset link to email
     */
    public function sendResetLink(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;

    /**
     * Validate reset token
     */
    public function validateToken(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;

    /**
     * Reset password using token
     */
    public function resetPassword(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;
}
