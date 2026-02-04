<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Libraries\ApiResponse;
use App\Models\UserModel;

class VerificationService
{
    protected UserModel $userModel;
    protected EmailService $emailService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->emailService = new EmailService();
    }

    /**
     * Send verification email to user
     *
     * @param int $userId
     * @return array<string, mixed>
     */
    public function sendVerificationEmail(int $userId): array
    {
        $user = $this->userModel->find($userId);

        if (! $user) {
            throw new NotFoundException(lang('Verification.userNotFound'));
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            throw new ConflictException(lang('Verification.alreadyVerified'));
        }

        // Generate verification token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Update user with token
        $this->userModel->update($userId, [
            'email_verification_token' => $token,
            'verification_token_expires' => $expiresAt,
        ]);

        // Build verification link
        $baseUrl = rtrim(env('app.baseURL', base_url()), '/');
        $verificationLink = "{$baseUrl}/api/v1/auth/verify-email?token={$token}";

        // Queue verification email
        $this->emailService->queueTemplate('verification', $user->email, [
            'subject' => lang('Email.verification.subject'),
            'username' => $user->username,
            'verification_link' => $verificationLink,
            'expires_at' => date('F j, Y g:i A', strtotime($expiresAt)),
        ]);

        return ApiResponse::success(
            ['message' => lang('Verification.sentMessage')],
            lang('Verification.emailSent')
        );
    }

    /**
     * Verify email with token
     *
     * @param string $token
     * @return array<string, mixed>
     */
    public function verifyEmail(string $token): array
    {
        if (empty($token)) {
            return ApiResponse::validationError(['token' => lang('Verification.tokenRequired')]);
        }

        // Find user by token
        $user = $this->userModel
            ->where('email_verification_token', $token)
            ->first();

        if (! $user) {
            throw new NotFoundException(lang('Verification.invalidToken'));
        }

        // Check if token expired
        if ($user->verification_token_expires && strtotime($user->verification_token_expires) < time()) {
            throw new BadRequestException(lang('Verification.tokenExpired'));
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            throw new ConflictException(lang('Verification.alreadyVerified'));
        }

        // Mark email as verified
        $this->userModel->update($user->id, [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
            'verification_token_expires' => null,
        ]);

        return ApiResponse::success(
            ['message' => lang('Verification.verifiedMessage')],
            lang('Verification.emailVerified')
        );
    }

    /**
     * Resend verification email
     *
     * @param int $userId
     * @return array<string, mixed>
     */
    public function resendVerification(int $userId): array
    {
        $user = $this->userModel->find($userId);

        if (! $user) {
            throw new NotFoundException(lang('Verification.userNotFound'));
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            throw new ConflictException(lang('Verification.alreadyVerified'));
        }

        // Send new verification email
        return $this->sendVerificationEmail($userId);
    }
}
