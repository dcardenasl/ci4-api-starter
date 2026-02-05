<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Interfaces\UserServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
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
        protected UserModel $userModel
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
        // Validate all fields before hashing password
        if (!$this->userModel->validate($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
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

        // Prepare data for insertion with hashed password
        $insertData = [
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'password' => isset($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : null,
            'role'     => $data['role'] ?? 'user',
        ];

        // Model maneja validación y timestamps automáticamente
        $userId = $this->userModel->insert($insertData);

        if (!$userId) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors() ?: ['general' => 'Failed to create user']
            );
        }

        $user = $this->userModel->find($userId);

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
        if (empty($data['email']) && empty($data['username'])) {
            throw new BadRequestException(
                'Invalid request',
                ['fields' => lang('Users.fieldRequired')]
            );
        }

        // Preparar datos de actualización
        $updateData = array_filter([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
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
}
