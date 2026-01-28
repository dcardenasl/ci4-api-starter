<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use App\Entities\UserEntity;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Exceptions\BadRequestException;
use App\Interfaces\UserServiceInterface;

class UserService implements UserServiceInterface
{
    protected UserModel $userModel;

    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
    }

    /**
     * Obtener todos los usuarios
     */
    public function index(array $data): array
    {
        $users = $this->userModel->findAll();

        return [
            'status' => 'success',
            'data' => array_map(fn($user) => $user->toArray(), $users),
        ];
    }

    /**
     * Obtener un usuario por ID
     */
    public function show(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => lang('Users.idRequired')]];
        }

        $user = $this->userModel->find($data['id']);

        if (!$user) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(array $data): array
    {
        // Validaciones de reglas de negocio (más allá de integridad de datos)
        // Ejemplo: verificar dominio de email permitido, consultar API externa, etc.
        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            return ['errors' => $businessErrors];
        }

        // Model maneja validación y timestamps automáticamente
        $userId = $this->userModel->insert([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
        ]);

        if (!$userId) {
            // Obtener errores de validación del Model
            return ['errors' => $this->userModel->errors()];
        }

        $user = $this->userModel->find($userId);

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Actualizar un usuario existente
     */
    public function update(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => lang('Users.idRequired')]];
        }

        $id = (int) $data['id'];

        // Verificar si el usuario existe
        if (!$this->userModel->find($id)) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        // Regla de negocio: al menos un campo requerido
        if (empty($data['email']) && empty($data['username'])) {
            return ['errors' => ['fields' => lang('Users.fieldRequired')]];
        }

        // Preparar datos de actualización
        $updateData = array_filter([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
        ], fn($value) => $value !== null);

        // Model maneja validación y updated_at automáticamente
        $success = $this->userModel->update($id, $updateData);

        if (!$success) {
            return ['errors' => $this->userModel->errors()];
        }

        $user = $this->userModel->find($id);

        return [
            'status' => 'success',
            'data' => $user->toArray(),
        ];
    }

    /**
     * Eliminar un usuario (soft delete)
     */
    public function destroy(array $data): array
    {
        if (!isset($data['id'])) {
            return ['errors' => ['id' => lang('Users.idRequired')]];
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

        return [
            'status' => 'success',
            'message' => lang('Users.deletedSuccess'),
        ];
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
     * Authenticate user with credentials
     * Protected against timing attacks by always verifying password hash
     */
    public function login(array $data): array
    {
        if (empty($data['username']) || empty($data['password'])) {
            return ['errors' => ['credentials' => lang('Users.auth.credentialsRequired')]];
        }

        $user = $this->userModel
            ->where('username', $data['username'])
            ->orWhere('email', $data['username'])
            ->first();

        // Use a fake hash for non-existent users to prevent timing attacks
        // This ensures password_verify() is always called, keeping response time constant
        $storedHash = $user
            ? $user->password
            : '$2y$10$fakeHashToPreventTimingAttacksByEnsuringConstantTimeResponse1234567890';

        $passwordValid = password_verify($data['password'], $storedHash);

        if (!$user || !$passwordValid) {
            return ['errors' => ['credentials' => lang('Users.auth.invalidCredentials')]];
        }

        return [
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }

    /**
     * Register a new user with password
     */
    public function register(array $data): array
    {
        if (empty($data['password'])) {
            return ['errors' => ['password' => lang('Users.passwordRequired')]];
        }

        // Validate password strength using model rules
        if (!$this->userModel->validate($data)) {
            return ['errors' => $this->userModel->errors()];
        }

        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            return ['errors' => $businessErrors];
        }

        $userId = $this->userModel->insert([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'     => 'user', // Always 'user' for self-registration (security fix)
        ], false); // Skip validation since we already validated above

        if (!$userId) {
            return ['errors' => $this->userModel->errors()];
        }

        $user = $this->userModel->find($userId);

        return [
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }

    /**
     * Authenticate user and return JWT token
     *
     * This method combines login authentication with token generation.
     * Used by AuthController for login endpoint.
     *
     * @param array $data Login credentials
     * @return array Result with token and user data, or errors
     */
    public function loginWithToken(array $data): array
    {
        $result = $this->login($data);

        if (isset($result['errors'])) {
            return $result;
        }

        $user = $result['data'];
        $jwtService = \Config\Services::jwtService();
        $token = $jwtService->encode($user['id'], $user['role']);

        return [
            'status' => 'success',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ];
    }

    /**
     * Register new user and return JWT token
     *
     * This method combines user registration with token generation.
     * Used by AuthController for register endpoint.
     *
     * @param array $data Registration data
     * @return array Result with token and user data, or errors
     */
    public function registerWithToken(array $data): array
    {
        $result = $this->register($data);

        if (isset($result['errors'])) {
            return $result;
        }

        $user = $result['data'];
        $jwtService = \Config\Services::jwtService();
        $token = $jwtService->encode($user['id'], $user['role']);

        return [
            'status' => 'success',
            'data' => [
                'token' => $token,
                'user' => $user,
            ],
        ];
    }
}
