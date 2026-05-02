<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Interfaces\Mappers\ResponseMapperInterface;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Interfaces\Users\UserServiceInterface;
use App\Services\Core\BaseCrudService;
use App\Services\Users\Actions\ApproveUserAction;
use App\Services\Users\Actions\CreateUserAction;
use App\Services\Users\Actions\UpdateUserAction;

/**
 * User Service
 *
 * Handles CRUD operations for users. Authorization is enforced at the route
 * level via the `permission` filter (e.g. `permission:users.write`); the
 * service trusts that any reachable call has already passed those gates.
 */
class UserService extends BaseCrudService implements UserServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        ResponseMapperInterface $responseMapper,
        protected ApproveUserAction $approveUserAction,
        protected CreateUserAction $createUserAction,
        protected UpdateUserAction $updateUserAction
    ) {
        parent::__construct($userRepository, $responseMapper);
    }

    /**
     * Create a new user
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Users\UserCreateRequestDTO $request */
        $context ??= SecurityContext::anonymous();
        return $this->wrapInTransaction(function () use ($request, $context) {
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
        if ($context !== null && ! $context->hasPermission('users.read') && ! $context->isUser($id)) {
            throw new \App\Exceptions\AuthorizationException(lang('Auth.insufficientPermissions'));
        }

        return parent::show($id, $context);
    }

    /**
     * Delete a user
     */
    public function destroy(int $id, ?SecurityContext $context = null): bool
    {
        $targetUser = $this->repository->find($id);
        if (!$targetUser) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return parent::destroy($id, $context);
    }
}
