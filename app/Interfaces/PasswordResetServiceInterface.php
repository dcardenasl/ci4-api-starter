<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Identity\ForgotPasswordRequestDTO;
use App\DTO\Request\Identity\ResetPasswordRequestDTO;
use App\DTO\Response\Identity\PasswordResetResponseDTO;

/**
 * Password Reset Service Interface
 *
 * Contract for password reset functionality
 */
interface PasswordResetServiceInterface
{
    /**
     * Send password reset link to email
     */
    public function sendResetLink(ForgotPasswordRequestDTO $request): array;

    /**
     * Validate reset token
     */
    public function validateToken(array $data): array;

    /**
     * Reset password using token
     */
    public function resetPassword(ResetPasswordRequestDTO $request): PasswordResetResponseDTO;
}
