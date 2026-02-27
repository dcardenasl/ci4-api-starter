<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTO\SecurityContext;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Models\UserModel;
use App\Traits\ResolvesWebAppLinks;
use CodeIgniter\I18n\Time;

/**
 * Modernized Verification Service
 */
class VerificationService implements \App\Interfaces\Auth\VerificationServiceInterface
{
    use ResolvesWebAppLinks;
    use \App\Traits\HandlesTransactions;

    public function __construct(
        protected UserModel $userModel,
        protected EmailServiceInterface $emailService,
        protected AuditServiceInterface $auditService
    ) {
    }

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(int $userId, ?SecurityContext $context = null): bool
    {
        $user = $this->userModel->find($userId);

        if (!$user instanceof \App\Entities\UserEntity) {
            throw new NotFoundException(lang('Verification.userNotFound'));
        }

        if ($user->email_verified_at !== null) {
            throw new ConflictException(lang('Verification.alreadyVerified'));
        }

        $token = bin2hex(random_bytes(32));
        $timestamp = strtotime('+24 hours');

        // Ensure $timestamp is int for date()
        $finalTimestamp = is_int($timestamp) ? $timestamp : (time() + 86400);
        $expiresAt = date('Y-m-d H:i:s', $finalTimestamp);

        $this->userModel->update($userId, [
            'email_verification_token' => $token,
            'verification_token_expires' => $expiresAt,
        ]);

        $this->auditService->log('verification_email_sent', 'users', (int) $user->id, [], ['email' => $user->email], $context);

        $verificationLink = $this->buildVerificationUrl($token);

        $this->emailService->queueTemplate('verification', (string) $user->email, [
            'subject' => lang('Email.verification.subject'),
            'display_name' => method_exists($user, 'getDisplayName') ? (string) $user->getDisplayName() : (string) $user->email,
            'verification_link' => $verificationLink,
            'expires_at' => date('F j, Y g:i A', strtotime($expiresAt)),
        ]);

        return true;
    }

    public function verifyEmail(DataTransferObjectInterface $request, ?SecurityContext $context = null): bool
    {
        /** @var \App\DTO\Request\Identity\VerificationRequestDTO $request */
        $user = $this->userModel->where('email_verification_token', $request->token)->first();

        if (! $user) {
            throw new NotFoundException(lang('Verification.invalidToken'));
        }

        /** @var \App\Entities\UserEntity $user */

        $expiresAtVal = $user->verification_token_expires;
        $expiresAtStr = '';

        if ($expiresAtVal instanceof Time) {
            $expiresAtStr = $expiresAtVal->toDateTimeString();
        } elseif (is_string($expiresAtVal)) {
            $expiresAtStr = $expiresAtVal;
        }

        if ($expiresAtStr !== '') {
            $expiresAtTimestamp = strtotime($expiresAtStr);
            if (is_int($expiresAtTimestamp) && $expiresAtTimestamp < time()) {
                throw new BadRequestException(lang('Verification.tokenExpired'));
            }
        }

        $now = date('Y-m-d H:i:s');

        $this->wrapInTransaction(function () use ($user, $now, $context) {
            $this->userModel->update($user->id, [
                'email_verified_at' => $now,
                'email_verification_token' => null,
                'verification_token_expires' => null,
            ]);

            $this->auditService->log('email_verified', 'users', (int) $user->id, [], ['email' => $user->email], $context);
        });

        return true;
    }

    /**
     * Resend verification email
     */
    public function resendVerification(int $userId, ?SecurityContext $context = null): bool
    {
        return $this->sendVerificationEmail($userId, $context);
    }
}
