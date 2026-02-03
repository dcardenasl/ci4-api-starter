<?php

namespace App\Services;

use App\Libraries\ApiResponse;
use App\Models\PasswordResetModel;
use App\Models\UserModel;

class PasswordResetService
{
    protected UserModel $userModel;
    protected PasswordResetModel $passwordResetModel;
    protected EmailService $emailService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->passwordResetModel = new PasswordResetModel();
        $this->emailService = new EmailService();
    }

    /**
     * Send password reset link to email
     *
     * @param string $email
     * @return array<string, mixed>
     */
    public function sendResetLink(string $email): array
    {
        // Validate email
        if (empty($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ApiResponse::validationError(['email' => lang('PasswordReset.emailRequired')]);
        }

        // Find user by email
        $user = $this->userModel->where('email', $email)->first();

        // Always return success to prevent email enumeration
        // But only send email if user exists
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));

            // Delete any existing reset tokens for this email
            $this->passwordResetModel->where('email', $email)->delete();

            // Store reset token
            $this->passwordResetModel->insert([
                'email' => $email,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Build reset link
            $baseUrl = rtrim(env('app.baseURL', base_url()), '/');
            $resetLink = "{$baseUrl}/api/v1/auth/reset-password?token={$token}&email=" . urlencode($email);

            // Queue password reset email
            $this->emailService->queueTemplate('password-reset', $email, [
                'subject' => lang('Email.passwordReset.subject'),
                'reset_link' => $resetLink,
                'expires_in' => '60 minutes',
            ]);
        }

        // Always return success message (security best practice)
        return ApiResponse::success(
            ['message' => lang('PasswordReset.sentMessage')],
            lang('PasswordReset.linkSent')
        );
    }

    /**
     * Validate reset token
     *
     * @param string $token
     * @param string $email
     * @return array<string, mixed>
     */
    public function validateToken(string $token, string $email): array
    {
        if (empty($token) || empty($email)) {
            return ApiResponse::validationError([
                'token' => lang('PasswordReset.tokenRequired'),
            ]);
        }

        // Clean expired tokens
        $this->passwordResetModel->cleanExpired(60);

        // Check if token is valid
        if (! $this->passwordResetModel->isValidToken($email, $token, 60)) {
            return ApiResponse::error(
                ['token' => lang('PasswordReset.invalidToken')],
                lang('PasswordReset.invalidToken'),
                400
            );
        }

        return ApiResponse::success(['valid' => true], lang('PasswordReset.tokenValid'));
    }

    /**
     * Reset password using token
     *
     * @param string $token
     * @param string $email
     * @param string $newPassword
     * @return array<string, mixed>
     */
    public function resetPassword(string $token, string $email, string $newPassword): array
    {
        // Validate inputs
        if (empty($token) || empty($email) || empty($newPassword)) {
            return ApiResponse::validationError([
                'password' => lang('PasswordReset.allFieldsRequired'),
            ]);
        }

        // Validate password strength
        if (strlen($newPassword) < 8) {
            return ApiResponse::validationError([
                'password' => lang('PasswordReset.passwordMinLength'),
            ]);
        }

        if (strlen($newPassword) > 128) {
            return ApiResponse::validationError([
                'password' => lang('PasswordReset.passwordMaxLength'),
            ]);
        }

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/', $newPassword)) {
            return ApiResponse::validationError([
                'password' => lang('PasswordReset.passwordComplexity'),
            ]);
        }

        // Clean expired tokens
        $this->passwordResetModel->cleanExpired(60);

        // Validate token
        if (! $this->passwordResetModel->isValidToken($email, $token, 60)) {
            return ApiResponse::error(
                ['token' => lang('PasswordReset.invalidToken')],
                lang('PasswordReset.invalidToken'),
                400
            );
        }

        // Find user
        $user = $this->userModel->where('email', $email)->first();

        if (! $user) {
            return ApiResponse::error(
                ['email' => lang('PasswordReset.userNotFound')],
                lang('PasswordReset.userNotFound'),
                404
            );
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update password
        $this->userModel->update($user->id, [
            'password' => $hashedPassword,
        ]);

        // Delete used reset token
        $this->passwordResetModel
            ->where('email', $email)
            ->where('token', $token)
            ->delete();

        return ApiResponse::success(
            ['message' => lang('PasswordReset.resetMessage')],
            lang('PasswordReset.passwordReset')
        );
    }
}
