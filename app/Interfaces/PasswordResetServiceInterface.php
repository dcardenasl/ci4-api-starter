<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Password Reset Service Interface
 *
 * Contract for password reset functionality
 */
interface PasswordResetServiceInterface
{
    /**
     * Send password reset link to email
     *
     * @param array $data Contains 'email'
     * @return array<string, mixed>
     */
    public function sendResetLink(array $data): array;

    /**
     * Validate reset token
     *
     * @param array $data Contains 'token' and 'email'
     * @return array<string, mixed>
     */
    public function validateToken(array $data): array;

    /**
     * Reset password using token
     *
     * @param array $data Contains 'token', 'email', 'password'
     * @return array<string, mixed>
     */
    public function resetPassword(array $data): array;
}
