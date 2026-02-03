<?php

namespace App\Services;

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
            return ApiResponse::error(['user' => lang('Verification.userNotFound')], lang('Verification.userNotFound'), 404);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return ApiResponse::error(['email' => lang('Verification.alreadyVerified')], lang('Verification.alreadyVerified'));
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
            return ApiResponse::error(['token' => lang('Verification.invalidToken')], lang('Verification.invalidToken'), 400);
        }

        // Check if token expired
        if ($user->verification_token_expires && strtotime($user->verification_token_expires) < time()) {
            return ApiResponse::error(['token' => lang('Verification.tokenExpired')], lang('Verification.tokenExpired'), 400);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return ApiResponse::success(['message' => lang('Verification.alreadyVerifiedMsg')], lang('Verification.alreadyVerified'));
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
            return ApiResponse::error(['user' => lang('Verification.userNotFound')], lang('Verification.userNotFound'), 404);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return ApiResponse::error(['email' => lang('Verification.alreadyVerified')], lang('Verification.alreadyVerified'));
        }

        // Send new verification email
        return $this->sendVerificationEmail($userId);
    }
}
