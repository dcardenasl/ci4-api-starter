<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Libraries\ApiResponse;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Traits\ResolvesWebAppLinks;

class PasswordResetService implements PasswordResetServiceInterface
{
    use ResolvesWebAppLinks;

    public function __construct(
        protected UserModel $userModel,
        protected PasswordResetModel $passwordResetModel,
        protected EmailServiceInterface $emailService
    ) {
    }

    /**
     * Send password reset link to email
     *
     * @param array $data Contains 'email'
     * @return array<string, mixed>
     */
    public function sendResetLink(array $data): array
    {
        $email = $data['email'] ?? '';
        // Validate email (support international domain names)
        // Try converting IDN to ASCII for validation
        $emailToValidate = $email;
        if (strpos($email, '@') !== false) {
            [$localPart, $domain] = explode('@', $email, 2);
            // Convert international domain to punycode for validation
            $asciiDomain = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($asciiDomain !== false) {
                $emailToValidate = $localPart . '@' . $asciiDomain;
            }
        }

        if (empty($email) || ! filter_var($emailToValidate, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(
                lang('PasswordReset.emailRequired'),
                ['email' => lang('PasswordReset.emailRequired')]
            );
        }

        // Find user by email
        $user = $this->userModel->where('email', $email)->first();

        // Always return success to prevent email enumeration
        // But only send email if user exists
        if ($user) {
            // Generate reset token
            $token = generate_token();

            // Delete any existing reset tokens for this email
            $this->passwordResetModel->where('email', $email)->delete();

            // Store reset token
            $this->passwordResetModel->insert([
                'email' => $email,
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Build reset link
            $clientBaseUrl = isset($data['client_base_url']) ? (string) $data['client_base_url'] : null;
            $resetLink = $this->buildResetPasswordUrl($token, $email, $clientBaseUrl);

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
     * @param array $data Contains 'token' and 'email'
     * @return array<string, mixed>
     */
    public function validateToken(array $data): array
    {
        $token = $data['token'] ?? '';
        $email = $data['email'] ?? '';

        if (empty($token) || empty($email)) {
            throw new BadRequestException(
                lang('PasswordReset.tokenRequired'),
                ['token' => lang('PasswordReset.tokenRequired')]
            );
        }

        // Clean expired tokens
        $this->passwordResetModel->cleanExpired(60);

        // Check if token is valid
        if (! $this->passwordResetModel->isValidToken($email, $token, 60)) {
            throw new NotFoundException(lang('PasswordReset.invalidToken'));
        }

        return ApiResponse::success(['valid' => true], lang('PasswordReset.tokenValid'));
    }

    /**
     * Reset password using token
     *
     * @param array $data Contains 'token', 'email', 'password'
     * @return array<string, mixed>
     */
    public function resetPassword(array $data): array
    {
        $token = $data['token'] ?? '';
        $email = $data['email'] ?? '';
        $newPassword = $data['password'] ?? '';

        // Validate inputs
        if (empty($token) || empty($email) || empty($newPassword)) {
            throw new BadRequestException(
                lang('PasswordReset.allFieldsRequired'),
                ['password' => lang('PasswordReset.allFieldsRequired')]
            );
        }

        // Validate password strength
        $passwordErrors = [];

        if (strlen($newPassword) < 8) {
            $passwordErrors['password'] = lang('PasswordReset.passwordMinLength');
        } elseif (strlen($newPassword) > 128) {
            $passwordErrors['password'] = lang('PasswordReset.passwordMaxLength');
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/', $newPassword)) {
            $passwordErrors['password'] = lang('PasswordReset.passwordComplexity');
        }

        if (!empty($passwordErrors)) {
            throw new ValidationException(
                lang('PasswordReset.passwordValidationFailed'),
                $passwordErrors
            );
        }

        // Clean expired tokens
        $this->passwordResetModel->cleanExpired(60);

        // Validate token
        if (! $this->passwordResetModel->isValidToken($email, $token, 60)) {
            throw new NotFoundException(lang('PasswordReset.invalidToken'));
        }

        // Find user
        $user = $this->userModel->where('email', $email)->first();

        if (! $user) {
            throw new NotFoundException(lang('PasswordReset.userNotFound'));
        }

        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update password
        $updateData = ['password' => $hashedPassword];

        if (($user->status ?? null) === 'invited') {
            $updateData['email_verified_at'] = date('Y-m-d H:i:s');
            $updateData['invited_at'] = null;
            $updateData['invited_by'] = null;
            $updateData['status'] = 'active';
        }

        $this->userModel->update($user->id, $updateData);

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
