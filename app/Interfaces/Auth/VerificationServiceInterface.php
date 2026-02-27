<?php

declare(strict_types=1);

namespace App\Interfaces\Auth;

use App\DTO\SecurityContext;

/**
 * Email Verification Service Interface
 */
interface VerificationServiceInterface
{
    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(int $userId, ?SecurityContext $context = null): bool;

    /**
     * Verify email with token
     */
    public function verifyEmail(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): bool;

    /**
     * Resend verification email
     */
    public function resendVerification(int $userId, ?SecurityContext $context = null): bool;
}
