<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Password Reset Service Interface
 */
interface PasswordResetServiceInterface
{
    /**
     * Send password reset link to email
     */
    public function sendResetLink(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Validate reset token
     */
    public function validateToken(\App\Interfaces\DataTransferObjectInterface $request): array;

    /**
     * Reset password using token
     */
    public function resetPassword(\App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;
}
