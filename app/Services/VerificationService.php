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
            return ApiResponse::error(['user' => 'User not found'], 'User not found', 404);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return ApiResponse::error(['email' => 'Email already verified'], 'Email already verified');
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
            'subject' => 'Verify Your Email Address',
            'username' => $user->username,
            'verification_link' => $verificationLink,
            'expires_at' => date('F j, Y g:i A', strtotime($expiresAt)),
        ]);

        return ApiResponse::success(
            ['message' => 'Verification email sent'],
            'Verification email sent. Please check your inbox.'
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
            return ApiResponse::validationError(['token' => 'Verification token is required']);
        }

        // Find user by token
        $user = $this->userModel
            ->where('email_verification_token', $token)
            ->first();

        if (! $user) {
            return ApiResponse::error(['token' => 'Invalid verification token'], 'Invalid or expired token', 400);
        }

        // Check if token expired
        if ($user->verification_token_expires && strtotime($user->verification_token_expires) < time()) {
            return ApiResponse::error(['token' => 'Verification token has expired'], 'Token has expired', 400);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return ApiResponse::success(['message' => 'Email already verified'], 'Email already verified');
        }

        // Mark email as verified
        $this->userModel->update($user->id, [
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
            'verification_token_expires' => null,
        ]);

        return ApiResponse::success(
            ['message' => 'Email verified successfully'],
            'Your email has been verified successfully'
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
            return ApiResponse::error(['user' => 'User not found'], 'User not found', 404);
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            return ApiResponse::error(['email' => 'Email already verified'], 'Email already verified');
        }

        // Send new verification email
        return $this->sendVerificationEmail($userId);
    }
}
