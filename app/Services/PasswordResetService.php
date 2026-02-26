<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Traits\ResolvesWebAppLinks;

/**
 * Password Reset Service
 *
 * Handles the flow for recovering lost passwords.
 */
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
     */
    public function sendResetLink(\App\DTO\Request\Identity\ForgotPasswordRequestDTO $request): array
    {
        $email = $request->email;

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
            $resetLink = $this->buildResetPasswordUrl($token, $email, null);

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
        return [
            'status' => 'success',
            'message' => lang('PasswordReset.sentMessage')
        ];
    }

    /**
     * Reactivate a soft-deleted user and mark account pending admin approval.
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

        return [
            'status' => 'success',
            'valid' => true,
            'message' => lang('PasswordReset.tokenValid')
        ];
    }

    /**
     * Reset password using token
     */
    public function resetPassword(\App\DTO\Request\Identity\ResetPasswordRequestDTO $request): \App\DTO\Response\Identity\PasswordResetResponseDTO
    {
        $token = $request->token;
        $email = $request->email;
        $newPassword = $request->password;

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

        return \App\DTO\Response\Identity\PasswordResetResponseDTO::fromArray([
            'message' => lang('PasswordReset.resetMessage')
        ]);
    }
}
