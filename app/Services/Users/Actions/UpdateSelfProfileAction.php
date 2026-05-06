<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\Request\Auth\UpdateMeRequestDTO;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\Users\UserRepositoryInterface;

/**
 * Self-profile update action.
 *
 * Authorization is implicit (the caller is operating on their own user id),
 * so this action does NOT consult IamAuthorizationService. The endpoint that
 * invokes it (`PATCH /api/v1/auth/me`) authenticates via JWT and pulls the
 * subject id from the security context — it never accepts an arbitrary id.
 */
class UpdateSelfProfileAction
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(int $userId, UpdateMeRequestDTO $request): \App\Entities\UserEntity
    {
        /** @var \App\Entities\UserEntity|null $targetUser */
        $targetUser = $this->userRepository->find($userId);
        if ($targetUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $updateData = $this->buildUpdateData($request);
        if ($updateData === []) {
            throw new ValidationException(lang('Api.validationFailed'), ['update' => lang('Api.noFieldsToUpdate')]);
        }

        $this->userRepository->update($userId, $updateData);

        /** @var \App\Entities\UserEntity|null $updatedUser */
        $updatedUser = $this->userRepository->find($userId);
        if ($updatedUser === null) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return $updatedUser;
    }

    /**
     * @return array<string, string>
     */
    private function buildUpdateData(UpdateMeRequestDTO $request): array
    {
        $data = [];

        if ($request->first_name !== null && $request->first_name !== '') {
            $data['first_name'] = $request->first_name;
        }
        if ($request->last_name !== null && $request->last_name !== '') {
            $data['last_name'] = $request->last_name;
        }
        if ($request->avatar_url !== null && $request->avatar_url !== '') {
            $data['avatar_url'] = $request->avatar_url;
        }

        return $data;
    }
}
