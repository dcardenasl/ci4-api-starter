<?php

declare(strict_types=1);

namespace App\Services\Auth\Actions;

use App\DTO\Request\Auth\RegisterRequestDTO;
use App\DTO\SecurityContext;
use App\Exceptions\ValidationException;
use App\Interfaces\Auth\VerificationServiceInterface;
use App\Models\UserModel;

class RegisterUserAction
{
    public function __construct(
        protected UserModel $userModel,
        protected VerificationServiceInterface $verificationService
    ) {
    }

    public function execute(RegisterRequestDTO $request, ?SecurityContext $context = null): \App\Entities\UserEntity
    {
        $userId = $this->userModel->insert([
            'email'      => $request->email,
            'first_name' => $request->firstName,
            'last_name'  => $request->lastName,
            'password'   => password_hash($request->password, PASSWORD_BCRYPT),
            'role'       => 'user',
            'status'     => 'pending_approval',
        ]);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userModel->errors());
        }

        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userModel->find($userId);
        if ($user === null) {
            throw new ValidationException(lang('Api.validationFailed'), ['user' => lang('Api.resourceNotFound')]);
        }

        try {
            $this->verificationService->sendVerificationEmail((int) $userId, $context);
        } catch (\Throwable $exception) {
            log_message('error', 'Failed to send verification email: ' . $exception->getMessage());
        }

        return $user;
    }
}
