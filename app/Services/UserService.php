<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\UserServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\PasswordResetModel;
use App\Models\UserModel;

/**
 * User Service
 *
 * Handles CRUD operations for users.
 * Authentication methods have been moved to AuthService.
 */
class UserService implements UserServiceInterface
{
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

        // Apply filters if provided
        if (!empty($data['filter']) && is_array($data['filter'])) {
            $builder->filter($data['filter']);
        }

        // Apply search if provided
        if (!empty($data['search'])) {
            $builder->search($data['search']);
        }

        // Apply sorting if provided
        if (!empty($data['sort'])) {
            $builder->sort($data['sort']);
        }

        // Get pagination parameters
        $page = isset($data['page']) ? max((int) $data['page'], 1) : 1;
        $limit = isset($data['limit']) ? (int) $data['limit'] : (int) env('PAGINATION_DEFAULT_LIMIT', 20);

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
        if (!isset($data['id'])) {
            throw new BadRequestException(
                'Invalid request',
                ['id' => lang('Users.idRequired')]
            );
        }

        $user = $this->userModel->find($data['id']);

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
        validateOrFail($data, 'user', 'store');

        $passwordProvided = !empty($data['password']);
        $sendInvite = array_key_exists('send_invite', $data)
            ? filter_var($data['send_invite'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : ! $passwordProvided;
        $sendInvite = $sendInvite ?? false;

        if (! $sendInvite && ! $passwordProvided) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                ['password' => lang('Users.passwordRequired')]
            );
        }

        // Validaciones de reglas de negocio (más allá de integridad de datos)
        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $businessErrors
            );
        }

        $adminId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $now = date('Y-m-d H:i:s');

        $passwordToHash = $data['password'] ?? null;
        if (! $passwordToHash) {
            $passwordToHash = bin2hex(random_bytes(24)) . 'A1!';
        }

        // Prepare data for insertion with hashed password
        $insertData = [
            'email'      => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password'   => password_hash($passwordToHash, PASSWORD_BCRYPT),
            'role'       => $data['role'] ?? 'user',
            'status'     => 'active',
            'approved_at' => $adminId ? $now : null,
            'approved_by' => $adminId,
            'invited_at'  => $sendInvite ? $now : null,
            'invited_by'  => $sendInvite ? $adminId : null,
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

        if ($sendInvite && $user) {
            try {
                $this->sendInvitationEmail($user);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to send invitation email: ' . $e->getMessage());
            }
            return ApiResponse::created($user->toArray(), lang('Users.invitationSent'));
        }

        return ApiResponse::created($user->toArray());
    }

    /**
     * Actualizar un usuario existente
     */
    public function update(array $data): array
    {
        if (!isset($data['id'])) {
            throw new BadRequestException(
                'Invalid request',
                ['id' => lang('Users.idRequired')]
            );
        }

        $id = (int) $data['id'];

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
        if (!isset($data['id'])) {
            throw new BadRequestException(
                'Invalid request',
                ['id' => lang('Users.idRequired')]
            );
        }

        $id = (int) $data['id'];

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
     * Validaciones de reglas de negocio específicas
     * Separadas de las reglas de integridad del Model
     */
    protected function validateBusinessRules(array $data): array
    {
        $errors = [];

        // Ejemplo: validar dominio de email permitido
        // if (isset($data['email']) && !$this->isAllowedEmailDomain($data['email'])) {
        //     $errors['email'] = 'Dominio de email no permitido';
        // }

        return $errors;
    }

    /**
     * Approve a pending user
     */
    public function approve(array $data): array
    {
        if (!isset($data['id'])) {
            throw new BadRequestException(
                'Invalid request',
                ['id' => lang('Users.idRequired')]
            );
        }

        $id = (int) $data['id'];
        $adminId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        $user = $this->userModel->find($id);
        if (! $user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        if (($user->status ?? null) !== 'active') {
            $this->userModel->update($id, [
                'status' => 'active',
                'approved_at' => date('Y-m-d H:i:s'),
                'approved_by' => $adminId,
            ]);
        }

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
    protected function sendInvitationEmail(object $user): void
    {
        if (empty($user->email)) {
            return;
        }

        $token = bin2hex(random_bytes(32));

        $this->passwordResetModel->where('email', $user->email)->delete();
        $this->passwordResetModel->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $baseUrl = rtrim(env('app.baseURL', base_url()), '/');
        $resetLink = "{$baseUrl}/api/v1/auth/reset-password?token={$token}&email=" . urlencode($user->email);

        $displayName = method_exists($user, 'getDisplayName') ? $user->getDisplayName() : 'User';

        $this->emailService->queueTemplate('invitation', $user->email, [
            'subject' => lang('Email.invitation.subject'),
            'display_name' => $displayName,
            'reset_link' => $resetLink,
            'expires_in' => '60 minutes',
        ]);
    }
}
