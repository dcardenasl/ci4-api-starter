<?php

declare(strict_types=1);

namespace App\Services\Auth\Actions;

use App\DTO\Request\Auth\GoogleLoginRequestDTO;
use App\DTO\SecurityContext;
use App\Entities\UserEntity;
use App\Interfaces\Auth\GoogleIdentityServiceInterface;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Auth\Support\AuthUserMapper;
use App\Services\Auth\Support\GoogleAuthHandler;
use App\Services\Auth\Support\SessionManager;
use App\Services\Users\UserAccountGuard;
use App\Support\OperationResult;

class GoogleLoginAction
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected GoogleIdentityServiceInterface $googleIdentityService,
        protected GoogleAuthHandler $googleHandler,
        protected SessionManager $sessionManager,
        protected AuthUserMapper $userMapper,
        protected UserAccountGuard $userAccessPolicy,
        protected AuditServiceInterface $auditService,
        protected EmailServiceInterface $emailService
    ) {
    }

    public function execute(GoogleLoginRequestDTO $request, ?SecurityContext $context = null): OperationResult
    {
        $identity = $this->googleIdentityService->verifyIdToken($request->id_token);
        $email = strtolower($identity->email);

        /** @var UserEntity|null $user */
        $user = $this->userRepository->findByEmailWithDeleted($email);

        if (!$user) {
            $pending = $this->googleHandler->createPendingUser($identity->toArray());
            $this->sendPendingApprovalEmail($pending);

            $userContext = new SecurityContext((int) $pending->id, (string) $pending->role, $context?->metadata ?? []);
            $this->auditService->log(
                'google_registration_pending',
                'users',
                (int) $pending->id,
                [],
                ['email' => $email, 'provider' => 'google'],
                $userContext
            );

            return OperationResult::accepted(
                ['user' => $this->userMapper->mapPending($pending)],
                lang('Auth.googleRegistrationPendingApproval')
            );
        }

        if ($user->deleted_at !== null) {
            $user = $this->googleHandler->reactivateDeletedUser($user, $identity->toArray());
            $this->sendPendingApprovalEmail($user);

            return OperationResult::accepted(
                ['user' => $this->userMapper->mapPending($user)],
                lang('Auth.googleRegistrationPendingApproval')
            );
        }

        if (($user->status ?? null) === 'active') {
            $updateData = [];

            if (($user->oauth_provider ?? null) === null) {
                $updateData['oauth_provider'] = 'google';
            }
            if (($user->oauth_provider ?? null) === 'google' && empty($user->oauth_provider_id)) {
                $updateData['oauth_provider_id'] = $identity->provider_id;
            }
            if ($user->email_verified_at === null) {
                $updateData['email_verified_at'] = date('Y-m-d H:i:s');
            }
            if (($user->invited_at ?? null) !== null) {
                $updateData['invited_at'] = null;
                $updateData['invited_by'] = null;
            }

            if ($updateData !== []) {
                $this->userRepository->update((int) $user->id, $updateData);
                /** @var UserEntity|null $refreshed */
                $refreshed = $this->userRepository->find((int) $user->id);
                if ($refreshed === null) {
                    throw new \RuntimeException(lang('Auth.googleUserMissing'));
                }
                $user = $refreshed;
            }
        }

        $this->userAccessPolicy->assertCanAuthenticate($user);
        $this->googleHandler->syncProfileIfEmpty((int) $user->id, $identity->toArray());

        /** @var UserEntity|null $freshUser */
        $freshUser = $this->userRepository->find((int) $user->id);
        if ($freshUser === null) {
            throw new \RuntimeException(lang('Auth.googleUserMissing'));
        }

        $userContext = new SecurityContext((int) $freshUser->id, (string) $freshUser->role, $context?->metadata ?? []);
        $this->auditService->log(
            'google_login_success',
            'users',
            (int) $freshUser->id,
            [],
            ['email' => $email, 'provider' => 'google'],
            $userContext
        );

        return OperationResult::success(
            $this->sessionManager->generateSessionResponse($this->userMapper->mapAuthenticated($freshUser))
        );
    }

    private function sendPendingApprovalEmail(object $user): void
    {
        try {
            $this->emailService->queueTemplate('pending-approval-google', (string) $user->email, [
                'subject' => lang('Email.pendingApprovalGoogle.subject'),
                'display_name' => method_exists($user, 'getDisplayName') ? (string) $user->getDisplayName() : (string) $user->email,
            ]);
        } catch (\Throwable $exception) {
            log_message('error', 'Failed to queue email: ' . $exception->getMessage());
        }
    }
}
