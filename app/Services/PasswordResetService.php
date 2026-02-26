<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Traits\ResolvesWebAppLinks;

/**
 * Modernized Password Reset Service
 */
class PasswordResetService implements PasswordResetServiceInterface
{
    use ResolvesWebAppLinks;
    use \App\Traits\HandlesTransactions;

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
    public function sendResetLink(DataTransferObjectInterface $request): array
    {
        /** @var \App\DTO\Request\Identity\ForgotPasswordRequestDTO $request */
        $email = $request->email;
        $user = $this->userModel->where('email', $email)->first();

        if ($user instanceof \App\Entities\UserEntity) {
            $this->auditService->log('password_reset_request', 'users', (int) $user->id, [], ['email' => $email]);

            $token = bin2hex(random_bytes(32));
            $this->passwordResetModel->where('email', $email)->delete();
            $this->passwordResetModel->insert(['email' => $email, 'token' => $token, 'created_at' => date('Y-m-d H:i:s')]);

            $resetLink = $this->buildResetPasswordUrl($token, $email, null);
            $this->emailService->queueTemplate('password-reset', $email, [
                'subject' => lang('Email.passwordReset.subject'),
                'reset_link' => $resetLink,
                'expires_in' => '60 minutes',
            ]);
        } else {
            $deletedUser = $this->userModel->withDeleted()->where('email', $email)->first();
            if ($deletedUser instanceof \App\Entities\UserEntity && $deletedUser->deleted_at !== null) {
                $this->reactivateDeletedUserForApproval($deletedUser, $email);
            }
        }

        return ['status' => 'success', 'message' => lang('PasswordReset.sentMessage')];
    }

    /**
     * Validate reset token
     */
    public function validateToken(DataTransferObjectInterface $request): array
    {
        /** @var \App\DTO\Request\Identity\PasswordResetTokenValidationDTO $request */
        $this->passwordResetModel->cleanExpired(60);

        if (!$this->passwordResetModel->isValidToken($request->email, $request->token, 60)) {
            throw new NotFoundException(lang('PasswordReset.invalidToken'));
        }

        return ['status' => 'success', 'valid' => true, 'message' => lang('PasswordReset.tokenValid')];
    }

    /**
     * Reset password using token
     */
    public function resetPassword(DataTransferObjectInterface $request): DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Identity\ResetPasswordRequestDTO $request */
        $this->passwordResetModel->cleanExpired(60);

        if (!$this->passwordResetModel->isValidToken($request->email, $request->token, 60)) {
            throw new NotFoundException(lang('PasswordReset.invalidToken'));
        }

        $user = $this->userModel->where('email', $request->email)->first();
        if (!$user) {
            throw new NotFoundException(lang('PasswordReset.userNotFound'));
        }

        return $this->wrapInTransaction(function () use ($user, $request) {
            $updateData = ['password' => password_hash($request->password, PASSWORD_BCRYPT)];

            if (($user->status ?? null) === 'invited') {
                $updateData['email_verified_at'] = date('Y-m-d H:i:s');
                $updateData['invited_at'] = null;
                $updateData['invited_by'] = null;
                $updateData['status'] = 'active';
            }

            $this->userModel->update($user->id, $updateData);
            $this->auditService->log('password_reset_success', 'users', (int) $user->id, [], ['email' => $user->email], (int) $user->id);
            $this->passwordResetModel->where('email', $request->email)->where('token', $request->token)->delete();

            return \App\DTO\Response\Identity\PasswordResetResponseDTO::fromArray(['message' => lang('PasswordReset.resetMessage')]);
        });
    }

    private function reactivateDeletedUserForApproval(\App\Entities\UserEntity $user, string $email): void
    {
        $this->wrapInTransaction(function () use ($user, $email) {
            $db = \Config\Database::connect();
            $db->table('users')->where('id', (int) $user->id)->update([
                'deleted_at'  => null,
                'status'      => 'pending_approval',
                'approved_at' => null,
                'approved_by' => null,
            ]);
            $this->refreshTokenService->revokeAllUserTokens((int) $user->id);
            $this->passwordResetModel->where('email', $email)->delete();
            $this->auditService->log('account_reactivation_requested', 'users', (int) $user->id, [], ['email' => $email]);
        });
    }
}
