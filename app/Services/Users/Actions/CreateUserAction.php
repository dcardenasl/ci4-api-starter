<?php

declare(strict_types=1);

namespace App\Services\Users\Actions;

use App\DTO\Request\Users\UserCreateRequestDTO;
use App\DTO\SecurityContext;
use App\Exceptions\ValidationException;
use App\Interfaces\Users\UserRepositoryInterface;
use CodeIgniter\Events\Events;

class CreateUserAction
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(UserCreateRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
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
            'approved_at' => $status === 'active' ? $now : null,
            'approved_by' => $isPrivilegedCreator ? $adminId : null,
            'invited_at'  => $now,
            'invited_by'  => $adminId,
        ];

        $userId = $this->userRepository->insert($data);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userRepository->errors());
        }

        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userRepository->find((int) $userId);
        if ($user === null) {
            throw new ValidationException(lang('Api.validationFailed'), ['user' => lang('Api.resourceNotFound')]);
        }

        // Trigger Domain Event for side effects (email, logs, etc.)
        Events::trigger('user.created', $user, $context);

        return $user;
    }
}
