<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\Request\Users\UserUpdateRequestDTO;
use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Libraries\Security\UserRoleGuard;
use App\Models\UserModel;

class UpdateUserAction
{
    public function __construct(
        protected UserModel $userModel,
        protected UserRoleGuard $roleGuard
    ) {
    }

    public function execute(int $userId, UserUpdateRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
    {
        /** @var \App\Entities\UserEntity|null $targetUser */
        $targetUser = $this->userModel->find($userId);
        if ($targetUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $actorRole = $context?->role ?? 'user';
        $actorId = $context?->userId;

        $this->roleGuard->assertCanManageTarget($actorRole, $actorId, $userId, (string) $targetUser->role);

        if ($request->role !== null) {
            $this->roleGuard->assertCanChangeRole(
                $actorRole,
                (string) $targetUser->role,
                (string) $request->role
            );
        }

        $updateData = $this->buildUpdateData($request);
        if ($updateData === []) {
            throw new ValidationException(lang('Api.validationFailed'), ['update' => lang('Api.noFieldsToUpdate')]);
        }

        $this->userModel->update($userId, $updateData);

        /** @var \App\Entities\UserEntity|null $updatedUser */
        $updatedUser = $this->userModel->find($userId);
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
        if ($request->firstName !== null) {
            $data['first_name'] = $request->firstName;
        }
        if ($request->lastName !== null) {
            $data['last_name'] = $request->lastName;
        }
        if ($request->password !== null) {
            $data['password'] = password_hash($request->password, PASSWORD_BCRYPT);
        }
        if ($request->role !== null) {
            $data['role'] = $request->role;
        }

        return $data;
    }
}
