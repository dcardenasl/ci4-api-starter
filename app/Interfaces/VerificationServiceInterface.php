<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Identity\VerificationRequestDTO;
use App\DTO\Response\Identity\VerificationResponseDTO;

/**
 * Verification Service Interface
 *
 * Contract for email verification functionality
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
    public function verifyEmail(VerificationRequestDTO $request): VerificationResponseDTO;

    /**
     * Resend verification email
     */
    public function resendVerification(array $data): array;
}
