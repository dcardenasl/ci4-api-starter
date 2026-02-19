<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\VerificationServiceInterface;
use App\Libraries\ApiResponse;
use App\Models\UserModel;
use App\Traits\ResolvesWebAppLinks;
use CodeIgniter\I18n\Time;

class VerificationService implements VerificationServiceInterface
{
    use ResolvesWebAppLinks;

    public function __construct(
        protected UserModel $userModel,
        protected EmailServiceInterface $emailService
    ) {
    }

    /**
     * Send verification email to user
     *
     * @param int $userId
     * @return array<string, mixed>
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

        return ApiResponse::success(
            ['message' => lang('Verification.sentMessage')],
            lang('Verification.emailSent')
        );
    }

    /**
     * Verify email with token
     *
     * @param array $data Contains 'token'
     * @return array<string, mixed>
     */
    public function verifyEmail(array $data): array
    {
        $token = $data['token'] ?? '';

        if (empty($token)) {
            throw new BadRequestException(
                lang('Verification.tokenRequired'),
                ['token' => lang('Verification.tokenRequired')]
            );
        }

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
     * @param array $data Contains 'user_id'
     * @return array<string, mixed>
     */
    public function resendVerification(array $data): array
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $user = $this->userModel->find($userId);

        if (! $user) {
            throw new NotFoundException(lang('Verification.userNotFound'));
        }

        // Check if already verified
        if ($user->email_verified_at !== null) {
            throw new ConflictException(lang('Verification.alreadyVerified'));
        }

        // Send new verification email
        return $this->sendVerificationEmail($userId, $data);
    }
}
