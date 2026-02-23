<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\UserServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Traits\AppliesQueryOptions;
use App\Traits\ResolvesWebAppLinks;
use App\Traits\ValidatesRequiredFields;

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
    use ValidatesRequiredFields;
    public function __construct(
        protected UserModel $userModel,
        protected EmailServiceInterface $emailService,
        protected PasswordResetModel $passwordResetModel
    ) {
    }

    /**
     * Obtener todos los usuarios con paginación, filtros, búsqueda y ordenamiento
     */
    public function index(array $data): array
    {
        $builder = new QueryBuilder($this->userModel);

        $this->applyQueryOptions($builder, $data);

        [$page, $limit] = $this->resolvePagination(
            $data,
            (int) env('PAGINATION_DEFAULT_LIMIT', 20)
        );

        // Paginate results
        $result = $builder->paginate($page, $limit);

        // Convert entities to arrays
        $result['data'] = array_map(fn ($user) => $user->toArray(), $result['data']);

        return ApiResponse::paginated(
            $result['data'],
            $result['total'],
            $result['page'],
            $result['perPage']
        );
    }

    /**
     * Obtener un usuario por ID
     */
    public function show(array $data): array
    {
        $id = $this->validateRequiredId($data);

        $user = $this->userModel->find($id);

        if (!$user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return ApiResponse::success($user->toArray());
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(array $data): array
    {
        validateOrFail($data, 'user', 'store_admin');

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

        // Model maneja validación y timestamps automáticamente
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

        return ApiResponse::created($user->toArray(), lang('Users.invitationSent'));
    }

    /**
     * Actualizar un usuario existente
     */
    public function update(array $data): array
    {
        $id = $this->validateRequiredId($data);

        // Verificar si el usuario existe
        if (!$this->userModel->find($id)) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        // Regla de negocio: al menos un campo requerido
        if (
            empty($data['email']) &&
            empty($data['first_name']) &&
            empty($data['last_name']) &&
            empty($data['password']) &&
            empty($data['role'])
        ) {
            throw new BadRequestException(
                'Invalid request',
                ['fields' => lang('Users.fieldRequired')]
            );
        }

        // Preparar datos de actualización
        $updateData = array_filter([
            'email'      => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password'   => isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null,
            'role'       => $data['role'] ?? null,
        ], fn ($value) => $value !== null);

        // Model maneja validación y updated_at automáticamente
        $success = $this->userModel->update($id, $updateData);

        if (!$success) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
        }

        $user = $this->userModel->find($id);

        return ApiResponse::success($user->toArray());
    }

    /**
     * Eliminar un usuario (soft delete)
     */
    public function destroy(array $data): array
    {
        $id = $this->validateRequiredId($data);

        // Verificar si el usuario existe
        if (!$this->userModel->find($id)) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        // Realiza soft delete (gracias a useSoftDeletes = true)
        if (!$this->userModel->delete($id)) {
            throw new \RuntimeException(lang('Users.deleteError'));
        }

        return ApiResponse::deleted(lang('Users.deletedSuccess'));
    }

    /**
     * Approve a pending user
     */
    public function approve(array $data): array
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

        $user = $this->userModel->find($id);
        if ($user) {
            $this->emailService->queueTemplate('account-approved', $user->email, [
                'subject' => lang('Email.accountApproved.subject'),
                'display_name' => method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'User',
            ]);
        }

        return ApiResponse::success($user->toArray(), lang('Users.approvedSuccess'));
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
}
