<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\Users\UserRepositoryInterface;
use App\Services\Iam\UserRoleAssignmentService;

class UpdateUserAction
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected UserRoleAssignmentService $userRoleAssignmentService
    ) {
    }

    public function execute(int $userId, UserUpdateRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
    {
        /** @var \App\Entities\UserEntity|null $targetUser */
        $targetUser = $this->userRepository->find($userId);
        if ($targetUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $updateData = $this->buildUpdateData($request);
        $hasFieldUpdates = $updateData !== [];
        $hasRoleUpdates = $request->role_ids !== null;

        if (! $hasFieldUpdates && ! $hasRoleUpdates) {
            throw new ValidationException(lang('Api.validationFailed'), ['update' => lang('Api.noFieldsToUpdate')]);
        }

        if ($hasFieldUpdates) {
            $this->userRepository->update($userId, $updateData);
        }

        if ($hasRoleUpdates) {
            $this->userRoleAssignmentService->syncRoles(
                $userId,
                $request->role_ids ?? [],
                $context?->user_id
            );
        }

        /** @var \App\Entities\UserEntity|null $updatedUser */
        $updatedUser = $this->userRepository->find($userId);
        if ($updatedUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return $updatedUser;
    }

    private function buildUpdateData(UserUpdateRequestDTO $request): array
    {
        $data = [];

        if ($request->email !== null) {
            $data['email'] = $request->email;
        }
        if ($request->first_name !== null) {
            $data['first_name'] = $request->first_name;
        }
        if ($request->last_name !== null) {
            $data['last_name'] = $request->last_name;
        }
        if ($request->password !== null) {
            $data['password'] = password_hash($request->password, PASSWORD_BCRYPT);
        }
        if ($request->avatar_url !== null) {
            $data['avatar_url'] = $request->avatar_url;
        }

        return $data;
    }
}
