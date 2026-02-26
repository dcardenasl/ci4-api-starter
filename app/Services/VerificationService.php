<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\VerificationServiceInterface;
use App\Models\UserModel;
use App\Traits\ResolvesWebAppLinks;
use App\Traits\ValidatesRequiredFields;
use CodeIgniter\I18n\Time;

/**
 * Verification Service
 *
 * Handles email verification flow
 */
class VerificationService implements VerificationServiceInterface
{
    use ResolvesWebAppLinks;
    use ValidatesRequiredFields;

    public function __construct(
        protected UserModel $userModel,
        protected EmailServiceInterface $emailService,
        protected AuditServiceInterface $auditService
    ) {
    }

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(int $userId, array $data = []): array
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
        $token = generate_token();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Update user with token
        $this->userModel->update($userId, [
            'email_verification_token' => $token,
            'verification_token_expires' => $expiresAt,
        ]);

        // Log verification email sent
        $this->auditService->log(
            'verification_email_sent',
            'users',
            (int) $user->id,
            [],
            ['email' => $user->email]
        );

        // Build verification link
        $clientBaseUrl = isset($data['client_base_url']) ? (string) $data['client_base_url'] : null;
        $verificationLink = $this->buildVerificationUrl($token, $clientBaseUrl);

        // Queue verification email
        $displayName = 'User';
        if (is_object($user) && method_exists($user, 'getDisplayName')) {
            $displayName = $user->getDisplayName();
        } elseif (is_object($user) && !empty($user->email)) {
            $displayName = explode('@', $user->email)[0];
        }

        $this->emailService->queueTemplate('verification', $user->email, [
            'subject' => lang('Email.verification.subject'),
            'display_name' => $displayName,
            'verification_link' => $verificationLink,
            'expires_at' => date('F j, Y g:i A', strtotime($expiresAt)),
        ]);

        return [
            'status' => 'success',
            'message' => lang('Verification.sentMessage')
        ];
    }

    /**
     * Verify email with token
     */
    public function verifyEmail(\App\DTO\Request\Identity\VerificationRequestDTO $request): \App\DTO\Response\Identity\VerificationResponseDTO
    {
        $token = $request->token;

        // Find user by token
        $user = $this->userModel
            ->where('email_verification_token', $token)
            ->first();

        if (! $user) {
            throw new NotFoundException(lang('Verification.invalidToken'));
        }

        // Check if token expired
        $expiresAt = $user->verification_token_expires ?? null;
        if ($expiresAt instanceof Time) {
            $expiresAt = $expiresAt->toDateTimeString();
        }
        if (! empty($expiresAt) && strtotime((string) $expiresAt) < time()) {
            throw new BadRequestException(lang('Verification.tokenExpired'));
        }

        // Update user status
        $now = date('Y-m-d H:i:s');
        $this->userModel->update($user->id, [
            'email_verified_at' => $now,
            'email_verification_token' => null,
            'verification_token_expires' => null,
        ]);

        // Log successful verification
        $this->auditService->log(
            'email_verified',
            'users',
            (int) $user->id,
            [],
            ['email' => $user->email]
        );

        return \App\DTO\Response\Identity\VerificationResponseDTO::fromArray([
            'message' => lang('Verification.success'),
            'user_id' => $user->id,
            'email' => $user->email,
            'verified_at' => $now,
        ]);
    }

    /**
     * Resend verification email
     */
    public function resendVerification(array $data): array
    {
        $userId = $this->validateRequiredId($data, 'user_id');

        return $this->sendVerificationEmail($userId, $data);
    }
}
