<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Verification Service Interface
 *
 * Contract for email verification functionality
 */
interface VerificationServiceInterface
{
    /**
     * Send verification email to user
     *
     * @param int $userId
     * @return array<string, mixed>
     */
    public function sendVerificationEmail(int $userId, array $data = []): array;

    /**
     * Verify email with token
     *
     * @param array $data Contains 'token'
     * @return array<string, mixed>
     */
    public function verifyEmail(array $data): array;

    /**
     * Resend verification email
     *
     * @param array $data Contains 'user_id'
     * @return array<string, mixed>
     */
    public function resendVerification(array $data): array;
}
