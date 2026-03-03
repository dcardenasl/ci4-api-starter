<?php

declare(strict_types=1);

namespace App\Services\Auth\Support;

use App\Exceptions\ValidationException;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Models\UserModel;

/**
 * Google Auth Handler
 *
 * Manages the specific lifecycle of users authenticating via Google OAuth.
 */
class GoogleAuthHandler
{
    use \App\Traits\HandlesTransactions;

    public function __construct(
        protected UserModel $userModel,
        protected RefreshTokenServiceInterface $refreshTokenService
    ) {
    }

    /**
     * Create a new user in pending state from Google identity
     */
    public function createPendingUser(array $identity): \App\Entities\UserEntity
    {
        $userId = $this->userModel->insert([
            'email' => strtolower(trim((string) $identity['email'])),
            'first_name' => $identity['first_name'] ?? null,
            'last_name' => $identity['last_name'] ?? null,
            'avatar_url' => $identity['avatar_url'] ?? null,
            'role' => 'user',
            'status' => 'pending_approval',
            'oauth_provider' => 'google',
            'oauth_provider_id' => $identity['provider_id'],
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userModel->errors());
        }

        /** @var \App\Entities\UserEntity $user */
        $user = $this->userModel->find($userId);
        return $user;
    }

    /**
     * Reactivate a soft-deleted user coming from Google
     */
    public function reactivateDeletedUser(object $user, array $identity): \App\Entities\UserEntity
    {
        return $this->wrapInTransaction(function () use ($user, $identity) {
            $this->userModel->withDeleted()->update((int) $user->id, [
                'deleted_at' => null,
                'status' => 'pending_approval',
                'oauth_provider' => 'google',
                'oauth_provider_id' => $identity['provider_id'],
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);

            $this->syncProfileIfEmpty((int) $user->id, $identity);
            $this->refreshTokenService->revokeAllUserTokens((int) $user->id);

            /** @var \App\Entities\UserEntity $updatedUser */
            $updatedUser = $this->userModel->find((int) $user->id);
            return $updatedUser;
        });
    }

    /**
     * Synchronize profile data if the database record has empty fields
     */
    public function syncProfileIfEmpty(int $userId, array $identity): void
    {
        $currentUser = $this->userModel->find($userId);
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
            $this->userModel->update($userId, array_filter($updateData));
        }
    }
}
