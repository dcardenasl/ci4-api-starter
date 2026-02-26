<?php

declare(strict_types=1);

namespace App\Interfaces;

/**
 * Email Verification Service Interface
 */
interface VerificationServiceInterface
{
    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(int $userId, array $data = []): array;

    /**
     * Verify email with token
     */
    public function verifyEmail(\App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface;

    /**
     * Resend verification email
     */
    public function resendVerification(int $userId, array $data = []): array;
}
