<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\Request\Users\UserStoreRequestDTO;
use App\DTO\SecurityContext;
use App\Exceptions\ValidationException;
use App\Models\UserModel;
use App\Services\Auth\UserInvitationService;

class CreateUserAction
{
    public function __construct(
        protected UserModel $userModel,
        protected UserInvitationService $invitationService
    ) {
    }

    public function execute(UserStoreRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
    {
        $actorRole = $context?->user_role ?? 'user';
        $adminId = $context?->user_id;
        $isPrivilegedCreator = in_array($actorRole, ['admin', 'superadmin'], true);
        $status = $isPrivilegedCreator ? 'active' : 'invited';
        $now = date('Y-m-d H:i:s');
        $generatedPassword = bin2hex(random_bytes(24)) . 'Aa1!';

        $data = [
            'email'       => $request->email,
            'first_name'  => $request->first_name,
            'last_name'   => $request->last_name,
            'password'    => password_hash($generatedPassword, PASSWORD_BCRYPT),
            'role'        => $request->role,
            'status'      => $status,
            'approved_at' => $isPrivilegedCreator ? $now : null,
            'approved_by' => $isPrivilegedCreator ? $adminId : null,
            'invited_at'  => $now,
            'invited_by'  => $adminId,
        ];

        $userId = $this->userModel->insert($data);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userModel->errors());
        }

        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new ValidationException(lang('Api.validationFailed'), ['user' => lang('Api.resourceNotFound')]);
        }

        try {
            $this->invitationService->sendInvitation($user);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to send invitation email: ' . $e->getMessage());
        }

        return $user;
    }
}
