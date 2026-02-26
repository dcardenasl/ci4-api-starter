<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\UserServiceInterface;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Traits\AppliesQueryOptions;
use App\Traits\ResolvesWebAppLinks;

/**
 * User Service
 *
 * Handles CRUD operations for users.
 * Authentication methods have been moved to AuthService.
 */
class UserService extends BaseCrudService implements UserServiceInterface
{
    use AppliesQueryOptions;
    use ResolvesWebAppLinks;
    use \App\Traits\ValidatesRequiredFields;

    protected string $responseDtoClass = \App\DTO\Response\Users\UserResponseDTO::class;

    public function __construct(
        protected UserModel $userModel,
        protected EmailServiceInterface $emailService,
        protected PasswordResetModel $passwordResetModel,
        protected AuditServiceInterface $auditService
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
     * Create a new user (Overriding store because it has specific business logic)
     */
    public function store(\App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\DTO\Request\Users\UserStoreRequestDTO $request */
        try {
            return $this->wrapInTransaction(function () use ($request) {
                $apiRequest = \Config\Services::request();
                $actorRole = $apiRequest instanceof \App\HTTP\ApiRequest ? (string) $apiRequest->getAuthUserRole() : '';
                $adminId = $apiRequest instanceof \App\HTTP\ApiRequest ? $apiRequest->getAuthUserId() : null;

                $requestedRole = (string) $request->role;

                $this->assertAdminCanAssignRole($actorRole, $requestedRole);

                $now = date('Y-m-d H:i:s');
                $generatedPassword = bin2hex(random_bytes(24)) . 'Aa1!';

                // Prepare data for insertion
                $insertData = [
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
                ];

                $userId = $this->model->insert($insertData);

                if (!$userId) {
                    throw new ValidationException(
                        lang('Api.validationFailed'),
                        $this->model->errors() ?: ['general' => lang('Users.createError')]
                    );
                }

                /** @var \App\Entities\UserEntity $user */
                $user = $this->model->find($userId);

                try {
                    $this->sendInvitationEmail($user);

                } catch (\Throwable $e) {
                    log_message('error', 'Failed to send invitation email: ' . $e->getMessage());
                }

                return $this->mapToResponse($user);
            });
        } catch (\Throwable $e) {
            echo "\nFATAL ERROR IN USER SERVICE STORE: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            throw $e;
        }
    }

    /**
     * Update an existing user (Specific logic for roles and permissions)
     */
    public function update(int $id, \App\Interfaces\DataTransferObjectInterface $request): \App\Interfaces\DataTransferObjectInterface
    {
        $data = $request->toArray();
        $actorRole = (string) ($data['user_role'] ?? '');
        $actorId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        // Check if the user exists
        /** @var \App\Entities\UserEntity|null $targetUser */
        $targetUser = $this->model->find($id);
        if (!$targetUser) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $targetRole = (string) ($targetUser->role ?? 'user');
        $this->assertAdminCanManageTarget($actorRole, $actorId, $id, $targetRole);

        if (array_key_exists('role', $data)) {
            $requestedRole = (string) $data['role'];
            $this->assertAdminCanChangeRole($actorRole, $targetRole, $requestedRole);
        }

        // Prepare update data
        $updateData = array_filter([
            'email'      => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password'   => isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null,
            'role'       => $data['role'] ?? null,
        ], fn ($value) => $value !== null);

        $updateData['id'] = $id;

        $this->model->update($id, $updateData);

        /** @var \App\Entities\UserEntity $updatedUser */
        $updatedUser = $this->model->find($id);
        return $this->mapToResponse($updatedUser);
    }

    /**
     * Approve a pending user
     */
    public function approve(int $id, ?int $adminId = null, ?string $clientBaseUrl = null): \App\Interfaces\DataTransferObjectInterface
    {
        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->model->find($id);
        if (!$user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $status = (string) ($user->status ?? '');

        if ($status === 'active') {
            throw new \App\Exceptions\ConflictException(lang('Users.alreadyApproved'));
        }

        if ($status === 'invited') {
            throw new \App\Exceptions\ConflictException(lang('Users.cannotApproveInvited'));
        }

        if ($status !== 'pending_approval') {
            throw new \App\Exceptions\ConflictException(lang('Users.invalidApprovalState'));
        }

        return $this->wrapInTransaction(function () use ($id, $adminId, $status, $user, $clientBaseUrl) {
            $this->model->update($id, [
                'status' => 'active',
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => $adminId,
            ]);

            // Log user approval
            $this->auditService->log(
                'user_approved',
                'users',
                $id,
                ['status' => $status],
                ['status' => 'active'],
                $adminId
            );

            $this->emailService->queueTemplate('account-approved', (string) $user->email, [
                'subject' => lang('Email.accountApproved.subject'),
                'display_name' => method_exists($user, 'getDisplayName') ? (string) $user->getDisplayName() : 'User',
                'login_link' => $this->buildLoginUrl($clientBaseUrl),
            ]);

            /** @var object $finalUser */
            $finalUser = $this->model->find($id);
            return $this->mapToResponse($finalUser);
        });
    }

    /**
     * Delete a user
     */
    public function destroy(int $id): array
    {
        $request = \Config\Services::request();
        $actorRole = $request instanceof \App\HTTP\ApiRequest ? (string) $request->getAuthUserRole() : '';
        $actorId = $request instanceof \App\HTTP\ApiRequest ? (int) $request->getAuthUserId() : 0;

        $targetUser = $this->model->find($id);
        if (!$targetUser) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $this->assertAdminCanManageTarget($actorRole, $actorId, $id, (string) $targetUser->role);

        return parent::destroy($id);
    }

    /**
     * Send invitation email using password reset flow
     *
     * @param \App\Entities\UserEntity $user
     * @return void
     */
    protected function sendInvitationEmail(\App\Entities\UserEntity $user, ?string $clientBaseUrl = null): void
    {
        $email = (string) ($user->email ?? '');
        if ($email === '') {
            return;
        }

        $token = generate_token();

        $this->passwordResetModel->where('email', $email)->delete();
        $this->passwordResetModel->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $resetLink = $this->buildResetPasswordUrl($token, $email, $clientBaseUrl);

        $displayName = method_exists($user, 'getDisplayName') ? (string) $user->getDisplayName() : 'User';

        $this->emailService->queueTemplate('invitation', $email, [
            'subject' => lang('Email.invitation.subject'),
            'display_name' => $displayName,
            'reset_link' => $resetLink,
            'expires_in' => '60 minutes',
        ]);
    }

    private function assertAdminCanAssignRole(string $actorRole, string $requestedRole): void
    {
        if (
            $actorRole === 'admin'
            && in_array($requestedRole, ['admin', 'superadmin'], true)
        ) {
            throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
        }
    }

    private function assertAdminCanManageTarget(string $actorRole, ?int $actorId, int $targetId, string $targetRole): void
    {
        if (
            $actorRole === 'admin'
            && in_array($targetRole, ['admin', 'superadmin'], true)
            && ($actorId === null || $targetId !== $actorId)
        ) {
            throw new AuthorizationException(lang('Users.adminCannotManagePrivileged'));
        }
    }

    private function assertAdminCanChangeRole(string $actorRole, string $currentRole, string $requestedRole): void
    {
        if ($actorRole !== 'admin') {
            return;
        }

        if ($requestedRole === 'superadmin') {
            throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
        }

        if ($requestedRole === 'admin' && $currentRole !== 'admin') {
            throw new AuthorizationException(lang('Users.adminCannotAssignPrivilegedRole'));
        }
    }
}
