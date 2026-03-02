<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserServiceInterface;
use App\Libraries\Security\UserRoleGuard;
use App\Models\UserModel;
use App\Services\Core\BaseCrudService;
use App\Services\Users\Actions\CreateUserAction;
use App\Services\Users\Actions\UpdateUserAction;
use App\Traits\AppliesQueryOptions;
use App\Traits\ResolvesWebAppLinks;

/**
 * User Service (Refactored)
 *
 * Handles CRUD operations for users by delegating security and invitation logic
 * to specialized components.
 */
class UserService extends BaseCrudService implements UserServiceInterface
{
    use AppliesQueryOptions;
    use ResolvesWebAppLinks;
    use \App\Traits\ValidatesRequiredFields;

    protected CreateUserAction $createUserAction;
    protected UpdateUserAction $updateUserAction;

    public function __construct(
        protected UserModel $userModel,
        ResponseMapperInterface $responseMapper,
        protected EmailServiceInterface $emailService,
        protected AuditServiceInterface $auditService,
        protected UserRoleGuard $roleGuard,
        CreateUserAction $createUserAction,
        UpdateUserAction $updateUserAction
    ) {
        parent::__construct($responseMapper);
        $this->model = $userModel;
        $this->createUserAction = $createUserAction;
        $this->updateUserAction = $updateUserAction;
    }

    /**
     * Enforce security criteria for user listings
     */
    protected function applyBaseCriteria(\CodeIgniter\Model $model): void
    {
        $model->where('role !=', 'superadmin');
    }

    /**
     * Create a new user
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Users\UserStoreRequestDTO $request */
        return $this->wrapInTransaction(function () use ($request, $context) {
            $actorRole = $context?->role ?? 'user';
            $this->roleGuard->assertCanAssignRole($actorRole, (string) $request->role);

            /** @var \App\Entities\UserEntity $user */
            $user = $this->createUserAction->execute($request, $context);

            return $this->mapToResponse($user);
        });
    }

    /**
     * Update an existing user
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Users\UserUpdateRequestDTO $request */
        return $this->wrapInTransaction(function () use ($id, $request, $context) {
            /** @var \App\Entities\UserEntity $updatedUser */
            $updatedUser = $this->updateUserAction->execute($id, $request, $context);
            return $this->mapToResponse($updatedUser);
        });
    }

    /**
     * Approve a pending user
     */
    public function approve(int $id, ?SecurityContext $context = null, ?string $clientBaseUrl = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->model->find($id);
        if (!$user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        // Business Rule Check
        $status = (string) $user->status;
        if ($status === 'active') {
            throw new \App\Exceptions\ConflictException(lang('Users.alreadyApproved'));
        }
        if ($status === 'invited') {
            throw new \App\Exceptions\ConflictException(lang('Users.cannotApproveInvited'));
        }
        if ($status !== 'pending_approval') {
            throw new \App\Exceptions\ConflictException(lang('Users.invalidApprovalState'));
        }

        return $this->wrapInTransaction(function () use ($id, $context, $status, $user, $clientBaseUrl) {
            $this->model->update($id, [
                'status' => 'active',
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => $context?->userId,
            ]);

            $this->auditService->log('user_approved', 'users', $id, ['status' => $status], ['status' => 'active'], $context);

            $this->emailService->queueTemplate('account-approved', (string) $user->email, [
                'subject' => lang('Email.accountApproved.subject'),
                'display_name' => $user->getDisplayName(),
                'login_link' => $this->buildLoginUrl($clientBaseUrl),
            ]);

            /** @var \App\Entities\UserEntity $approvedUser */
            $approvedUser = $this->model->find($id);
            return $this->mapToResponse($approvedUser);
        });
    }

    /**
     * Get user profile with authorization
     */
    public function show(int $id, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        if ($context !== null && !$context->isAdmin() && !$context->isUser($id)) {
            throw new \App\Exceptions\AuthorizationException(lang('Auth.insufficientPermissions'));
        }

        return parent::show($id, $context);
    }

    /**
     * Delete a user
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        $targetUser = $this->model->find($id);
        if (!$targetUser) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $this->roleGuard->assertCanManageTarget($context?->role ?? 'user', $context?->userId, $id, (string) $targetUser->role);

        return parent::destroy($id, $context);
    }
}
