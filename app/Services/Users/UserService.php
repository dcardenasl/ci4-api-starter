<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\Users\UserServiceInterface;
use App\Libraries\Security\UserRoleGuard;
use App\Models\UserModel;
use App\Services\Core\BaseCrudService;
use App\Services\Users\Actions\ApproveUserAction;
use App\Services\Users\Actions\CreateUserAction;
use App\Services\Users\Actions\UpdateUserAction;
use App\Traits\AppliesQueryOptions;

/**
 * User Service (Refactored)
 *
 * Handles CRUD operations for users by delegating security and invitation logic
 * to specialized components.
 */
class UserService extends BaseCrudService implements UserServiceInterface
{
    use AppliesQueryOptions;
    use \App\Traits\ValidatesRequiredFields;

    protected ApproveUserAction $approveUserAction;
    protected CreateUserAction $createUserAction;
    protected UpdateUserAction $updateUserAction;

    public function __construct(
        protected UserModel $userModel,
        ResponseMapperInterface $responseMapper,
        protected UserRoleGuard $roleGuard,
        ApproveUserAction $approveUserAction,
        CreateUserAction $createUserAction,
        UpdateUserAction $updateUserAction
    ) {
        parent::__construct($responseMapper);
        $this->model = $userModel;
        $this->approveUserAction = $approveUserAction;
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
            $actorRole = $context?->user_role ?? 'user';
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
        return $this->wrapInTransaction(function () use ($id, $context, $clientBaseUrl) {
            $approvedUser = $this->approveUserAction->execute($id, $context, $clientBaseUrl);

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

        $this->roleGuard->assertCanManageTarget($context?->user_role ?? 'user', $context?->user_id, $id, (string) $targetUser->role);

        return parent::destroy($id, $context);
    }
}
