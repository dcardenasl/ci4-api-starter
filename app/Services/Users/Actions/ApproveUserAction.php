<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\SecurityContext;
use App\Entities\UserEntity;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Traits\ResolvesWebAppLinks;

class ApproveUserAction
{
    use ResolvesWebAppLinks;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuditServiceInterface $auditService,
        protected EmailServiceInterface $emailService
    ) {
    }

    public function execute(int $id, ?SecurityContext $context = null, ?string $clientBaseUrl = null): UserEntity
    {
        /** @var UserEntity|null $user */
        $user = $this->userRepository->find($id);
        if ($user === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $status = (string) $user->status;
        if ($status === 'active') {
            throw new ConflictException(lang('Users.alreadyApproved'));
        }
        if ($status === 'invited') {
            throw new ConflictException(lang('Users.cannotApproveInvited'));
        }
        if ($status !== 'pending_approval') {
            throw new ConflictException(lang('Users.invalidApprovalState'));
        }

        $this->userRepository->update($id, [
            'status' => 'active',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $context?->user_id,
        ]);

        $this->auditService->log('user_approved', 'users', $id, ['status' => $status], ['status' => 'active'], $context);

        $this->emailService->queueTemplate('account-approved', (string) $user->email, [
            'subject' => lang('Email.accountApproved.subject'),
            'display_name' => $user->getDisplayName(),
            'login_link' => $this->buildLoginUrl($clientBaseUrl),
        ]);

        /** @var UserEntity|null $approvedUser */
        $approvedUser = $this->userRepository->find($id);
        if ($approvedUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return $approvedUser;
    }
}
