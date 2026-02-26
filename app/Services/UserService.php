<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\UserServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
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
class UserService implements UserServiceInterface
{
    use AppliesQueryOptions;
    use ResolvesWebAppLinks;
    use \App\Traits\ValidatesRequiredFields;

    public function __construct(
        protected UserModel $userModel,
        protected EmailServiceInterface $emailService,
        protected PasswordResetModel $passwordResetModel,
        protected AuditServiceInterface $auditService
    ) {
    }

    /**
     * Get all users with pagination, filters, search, and ordering
     */
    public function index(\App\DTO\Request\Users\UserIndexRequestDTO $request): array
    {
        $builder = new QueryBuilder($this->userModel);
        $this->userModel->where('role !=', 'superadmin');

        $this->applyQueryOptions($builder, $request->toArray());

        // Paginate results
        $result = $builder->paginate($request->page, $request->perPage);

        // Convert entities to Response DTOs
        $result['data'] = array_map(
            fn ($user) => \App\DTO\Response\Users\UserResponseDTO::fromArray($user->toArray()),
            $result['data']
        );

        return [
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['perPage']
        ];
    }

    /**
     * Get a user by ID
     */
    public function show(array $data): \App\DTO\Response\Users\UserResponseDTO
    {
        $id = $this->validateRequiredId($data);

        $user = $this->userModel->find($id);

        if (!$user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return \App\DTO\Response\Users\UserResponseDTO::fromArray($user->toArray());
    }

    /**
     * Create a new user
     */
    public function store(array $data): \App\DTO\Response\Users\UserResponseDTO
    {
        validateOrFail($data, 'user', 'store_admin');
        $actorRole = (string) ($data['user_role'] ?? '');
        $requestedRole = (string) ($data['role'] ?? 'user');

        $this->assertAdminCanAssignRole($actorRole, $requestedRole);

        if (array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== '') {
            throw new ValidationException(
                lang('Api.validationFailed'),
                ['password' => lang('Users.adminPasswordForbidden')]
            );
        }

        $adminId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $now = date('Y-m-d H:i:s');
        $generatedPassword = bin2hex(random_bytes(24)) . 'Aa1!';

        // Prepare data for insertion with hashed password
        $insertData = [
            'email'      => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password'   => password_hash($generatedPassword, PASSWORD_BCRYPT),
            'role'       => $data['role'] ?? 'user',
            'status'     => 'invited',
            'approved_at' => $now,
            'approved_by' => $adminId,
            'invited_at'  => $now,
            'invited_by'  => $adminId,
            'email_verified_at' => $now,
        ];

        // Model handles validation and timestamps automatically
        $userId = $this->userModel->insert($insertData);

        if (!$userId) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors() ?: ['general' => lang('Users.createError')]
            );
        }

        $user = $this->userModel->find($userId);

        if (! $user) {
            throw new \RuntimeException(lang('Users.createError'));
        }

        try {
            $this->sendInvitationEmail(
                $user,
                isset($data['client_base_url']) ? (string) $data['client_base_url'] : null
            );
        } catch (\Throwable $e) {
            log_message('error', 'Failed to send invitation email: ' . $e->getMessage());
        }

        return \App\DTO\Response\Users\UserResponseDTO::fromArray($user->toArray());
    }

    /**
     * Update an existing user
     */
    public function update(array $data): \App\DTO\Response\Users\UserResponseDTO
    {
        $id = $this->validateRequiredId($data);
        validateOrFail($data, 'user', 'update');
        $actorRole = (string) ($data['user_role'] ?? '');
        $actorId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        // Check if the user exists
        $targetUser = $this->userModel->find($id);
        if (!$targetUser) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $targetRole = (string) ($targetUser->role ?? 'user');
        $this->assertAdminCanManageTarget($actorRole, $actorId, $id, $targetRole);

        if (array_key_exists('role', $data)) {
            $requestedRole = (string) $data['role'];
            $this->assertAdminCanChangeRole($actorRole, $targetRole, $requestedRole);
        }

        // Business rule: at least one field required
        if (
            empty($data['email']) &&
            empty($data['first_name']) &&
            empty($data['last_name']) &&
            empty($data['password']) &&
            empty($data['role'])
        ) {
            throw new BadRequestException(
                lang('Api.invalidRequest'),
                ['fields' => lang('Users.fieldRequired')]
            );
        }

        // Prepare update data
        $updateData = array_filter([
            'email'      => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password'   => isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null,
            'role'       => $data['role'] ?? null,
        ], fn ($value) => $value !== null);

        // Important: Include ID so that is_unique validation ignores this record
        $updateData['id'] = $id;

        // Model handles validation and updated_at automatically
        $success = $this->userModel->update($id, $updateData);

        if (!$success) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
        }

        $user = $this->userModel->find($id);

        return \App\DTO\Response\Users\UserResponseDTO::fromArray($user->toArray());
    }

    /**
     * Delete a user (soft delete)
     */
    public function destroy(array $data): array
    {
        $id = $this->validateRequiredId($data);
        $actorRole = (string) ($data['user_role'] ?? '');

        // Check if the user exists
        $targetUser = $this->userModel->find($id);
        if (!$targetUser) {
            throw new NotFoundException(lang('Users.notFound'));
        }
        $targetRole = (string) ($targetUser->role ?? 'user');

        if ($actorRole === 'admin' && $targetRole !== 'user') {
            throw new AuthorizationException(lang('Users.adminCannotManagePrivileged'));
        }

        // Perform soft delete (thanks to useSoftDeletes = true)
        if (!$this->userModel->delete($id)) {
            throw new \RuntimeException(lang('Users.deleteError'));
        }

        return ApiResponse::deleted(lang('Users.deletedSuccess'));
    }

    /**
     * Approve a pending user
     */
    public function approve(array $data): \App\DTO\Response\Users\UserResponseDTO
    {
        $id = $this->validateRequiredId($data);
        $adminId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        $user = $this->userModel->find($id);
        if (! $user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        $status = $user->status ?? null;

        if ($status === 'active') {
            throw new ConflictException(lang('Users.alreadyApproved'));
        }

        if ($status === 'invited') {
            throw new ConflictException(lang('Users.cannotApproveInvited'));
        }

        if ($status !== 'pending_approval') {
            throw new ConflictException(lang('Users.invalidApprovalState'));
        }

        $this->userModel->update($id, [
            'status' => 'active',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $adminId,
        ]);

        // Log user approval as a business event
        $this->auditService->log(
            'user_approved',
            'users',
            $id,
            ['status' => $status],
            ['status' => 'active'],
            $adminId
        );

        $user = $this->userModel->find($id);
        if ($user) {
            $clientBaseUrl = isset($data['client_base_url']) ? (string) $data['client_base_url'] : null;
            $this->emailService->queueTemplate('account-approved', $user->email, [
                'subject' => lang('Email.accountApproved.subject'),
                'display_name' => method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'User',
                'login_link' => $this->buildLoginUrl($clientBaseUrl),
            ]);
        }

        return \App\DTO\Response\Users\UserResponseDTO::fromArray($user->toArray());
    }

    /**
     * Send invitation email using password reset flow
     *
     * @param object $user
     * @return void
     */
    protected function sendInvitationEmail(object $user, ?string $clientBaseUrl = null): void
    {
        if (empty($user->email)) {
            return;
        }

        $token = generate_token();

        $this->passwordResetModel->where('email', $user->email)->delete();
        $this->passwordResetModel->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $resetLink = $this->buildResetPasswordUrl($token, (string) $user->email, $clientBaseUrl);

        $displayName = method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'User';

        $this->emailService->queueTemplate('invitation', $user->email, [
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
