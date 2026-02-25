<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
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
        protected EmailServiceInterface $emailService,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected AuditServiceInterface $auditService
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
        validateOrFail($data, 'auth', 'forgot_password');
        $email = (string) ($data['email'] ?? '');

        // Find active (non-deleted) user by email
        $user = $this->userModel->where('email', $email)->first();

        // Always return success to prevent email enumeration
        // But only send email if user exists and is not deleted
        if ($user) {
            // Log password reset request
            $this->auditService->log(
                'password_reset_request',
                'users',
                (int) $user->id,
                [],
                ['email' => $email]
            );

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
        } else {
            // Check soft-deleted users and request reactivation (without exposing existence)
            $deletedUser = $this->userModel
                ->withDeleted()
                ->where('email', $email)
                ->first();

            if ($deletedUser && ($deletedUser->deleted_at ?? null) !== null) {
                $this->reactivateDeletedUserForApproval($deletedUser, $email);
            }
        }

        // Always return success message (security best practice)
        return ApiResponse::success(
            ['message' => lang('PasswordReset.sentMessage')],
            lang('PasswordReset.linkSent')
        );
    }

    /**
     * Reactivate a soft-deleted user and mark account pending admin approval.
     * Public API response stays generic to avoid email enumeration.
     *
     * @param object $user
     * @param string $email
     * @return void
     */
    private function reactivateDeletedUserForApproval(object $user, string $email): void
    {
        $db = \Config\Database::connect();

        try {
            $db->transStart();

            $db->table('users')
                ->where('id', (int) $user->id)
                ->update([
                    'deleted_at' => null,
                    'status' => 'pending_approval',
                    'approved_at' => null,
                    'approved_by' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->refreshTokenService->revokeAllUserTokens((int) $user->id);

            // Remove stale reset tokens so account flow restarts cleanly.
            $this->passwordResetModel->where('email', $email)->delete();

            $db->transComplete();

            if ($db->transStatus() === false) {
                log_message('error', 'Failed to reactivate deleted user account. Email: ' . $email);
                return;
            }

            // Log account reactivation request
            $this->auditService->log(
                'account_reactivation_requested',
                'users',
                (int) $user->id,
                [],
                ['email' => $email]
            );

            log_message('info', lang('PasswordReset.reactivationRequested') . '. user_id: ' . $user->id);
        } catch (\Throwable $e) {
            log_message(
                'error',
                'Error processing deleted user reactivation. Email: '
                . $email
                . '. Error: '
                . $e->getMessage()
            );
        }
    }

    /**
     * Validate reset token
     *
     * @param array $data Contains 'token' and 'email'
     * @return array<string, mixed>
     */
    public function validateToken(array $data): array
    {
        validateOrFail($data, 'auth', 'password_reset_validate_token');
        $token = (string) ($data['token'] ?? '');
        $email = (string) ($data['email'] ?? '');

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
        validateOrFail($data, 'auth', 'password_reset');
        $token = (string) ($data['token'] ?? '');
        $email = (string) ($data['email'] ?? '');
        $newPassword = (string) ($data['password'] ?? '');

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

        // Log password reset success
        $this->auditService->log(
            'password_reset_success',
            'users',
            (int) $user->id,
            [],
            ['email' => $user->email],
            (int) $user->id
        );

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
