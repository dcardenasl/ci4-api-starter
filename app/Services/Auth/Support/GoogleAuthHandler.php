<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

use App\Exceptions\ValidationException;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;

/**
 * Google Auth Handler
 *
 * Manages the specific lifecycle of users authenticating via Google OAuth.
 */
class GoogleAuthHandler
{
    use \App\Traits\HandlesTransactions;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected RefreshTokenServiceInterface $refreshTokenService
    ) {
    }

    /**
     * Create a new user in pending state from Google identity
     */
    public function createPendingUser(array $identity): \App\Entities\UserEntity
    {
        $requiresVerification = is_email_verification_required();
        $status = $requiresVerification ? 'pending_approval' : 'active';
        $now = date('Y-m-d H:i:s');

        $userId = $this->userRepository->insert([
            'email' => strtolower(trim((string) $identity['email'])),
            'first_name' => $identity['first_name'] ?? null,
            'last_name' => $identity['last_name'] ?? null,
            'avatar_url' => $identity['avatar_url'] ?? null,
            'role' => 'user',
            'status' => $status,
            'oauth_provider' => 'google',
            'oauth_provider_id' => $identity['provider_id'],
            'email_verified_at' => $now,
            'approved_at' => $status === 'active' ? $now : null,
        ]);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userRepository->errors());
        }

        /** @var \App\Entities\UserEntity $user */
        $user = $this->userRepository->find((int) $userId);
        return $user;
    }

    /**
     * Reactivate a soft-deleted user coming from Google
     */
    public function reactivateDeletedUser(object $user, array $identity): \App\Entities\UserEntity
    {
        return $this->wrapInTransaction(function () use ($user, $identity) {
            $requiresVerification = is_email_verification_required();
            $status = $requiresVerification ? 'pending_approval' : 'active';
            $now = date('Y-m-d H:i:s');

            $this->userRepository->restore((int) $user->id, [
                'status' => $status,
                'oauth_provider' => 'google',
                'oauth_provider_id' => $identity['provider_id'],
                'email_verified_at' => $now,
                'approved_at' => $status === 'active' ? $now : null,
            ]);

            $this->syncProfileIfEmpty((int) $user->id, $identity);
            $this->refreshTokenService->revokeAllUserTokens((int) $user->id);

            /** @var \App\Entities\UserEntity|null $updatedUser */
            $updatedUser = $this->userRepository->find((int) $user->id);

            if (!$updatedUser instanceof \App\Entities\UserEntity) {
                // If not found by standard find (e.g. restoration took a moment or failed quietly),
                // we try to find it with deleted just to satisfy the return type contract,
                // but this shouldn't normally happen.
                $withDeleted = $this->userRepository->getModel()->withDeleted()->find((int) $user->id);
                if ($withDeleted instanceof \App\Entities\UserEntity) {
                    return $withDeleted;
                }

                throw new \RuntimeException(lang('Auth.googleUserMissing'));
            }

            return $updatedUser;
        });
    }

    /**
     * Synchronize profile data if the database record has empty fields
     */
    public function syncProfileIfEmpty(int $userId, array $identity): void
    {
        $currentUser = $this->userRepository->find($userId);
        if (!$currentUser) {
            return;
        }

        $updateData = [];

        if (empty($currentUser->first_name)) {
            $updateData['first_name'] = $identity['first_name'] ?? null;
        }
        if (empty($currentUser->last_name)) {
            $updateData['last_name'] = $identity['last_name'] ?? null;
        }
        if (empty($currentUser->avatar_url)) {
            $updateData['avatar_url'] = $identity['avatar_url'] ?? null;
        }

        if ($updateData !== []) {
            $this->userRepository->update($userId, array_filter($updateData));
        }
    }
}
