<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTO\Response\Users\UserResponseDTO;
use App\DTO\SecurityContext;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\System\AuditServiceInterface;
use App\Interfaces\System\EmailServiceInterface;
use App\Interfaces\Users\UserServiceInterface;
use App\Libraries\Security\UserRoleGuard;
use App\Models\UserModel;
use App\Services\Auth\UserInvitationService;
use App\Services\Core\BaseCrudService;
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

    protected string $responseDtoClass = UserResponseDTO::class;

    public function __construct(
        protected UserModel $userModel,
        protected EmailServiceInterface $emailService,
        protected AuditServiceInterface $auditService,
        protected UserRoleGuard $roleGuard,
        protected UserInvitationService $invitationService
    ) {
        $this->model = $userModel;
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
            $adminId = $context?->userId;

            // 1. Security Check
            $this->roleGuard->assertCanAssignRole($actorRole, (string) $request->role);

            // 2. Prepare Data
            $now = date('Y-m-d H:i:s');
            $generatedPassword = bin2hex(random_bytes(24)) . 'Aa1!';

            $userId = $this->model->insert([
                'email'      => $request->email,
                'first_name' => $request->firstName,
                'last_name'  => $request->lastName,
                'password'   => password_hash($generatedPassword, PASSWORD_BCRYPT),
                'role'       => $request->role,
                'status'     => 'invited',
                'approved_at' => $now,
                'approved_by' => $adminId,
                'invited_at'  => $now,
                'invited_by'  => $adminId,
                'email_verified_at' => $now,
            ]);

            if (!$userId) {
                throw new ValidationException(lang('Api.validationFailed'), $this->model->errors());
            }

            /** @var \App\Entities\UserEntity $user */
            $user = $this->model->find($userId);

            // 3. Trigger Invitation Flow
            try {
                $this->invitationService->sendInvitation($user);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to send invitation email: ' . $e->getMessage());
            }

            return $this->mapToResponse($user);
        });
    }

    /**
     * Update an existing user
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        $data = $request->toArray();
        $actorRole = $context?->role ?? 'user';
        $actorId = $context?->userId;

        /** @var \App\Entities\UserEntity|null $targetUser */
        $targetUser = $this->model->find($id);
        if (!$targetUser) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        // 1. Security Check (Management)
        $this->roleGuard->assertCanManageTarget($actorRole, $actorId, $id, (string) $targetUser->role);

        // 2. Security Check (Role Change)
        if (isset($data['role'])) {
            $this->roleGuard->assertCanChangeRole($actorRole, (string) $targetUser->role, (string) $data['role']);
        }

        // 3. Process Update
        $updateData = array_filter([
            'email'      => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password'   => isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null,
            'role'       => $data['role'] ?? null,
        ], fn ($value) => $value !== null);

        $this->model->update($id, $updateData);

        /** @var \App\Entities\UserEntity $updatedUser */
        $updatedUser = $this->model->find($id);
        return $this->mapToResponse($updatedUser);
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
