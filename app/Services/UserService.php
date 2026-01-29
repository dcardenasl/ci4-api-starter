<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Interfaces\UserServiceInterface;
use App\Libraries\ApiResponse;
use App\Libraries\Query\QueryBuilder;
use App\Models\UserModel;

class UserService implements UserServiceInterface
{
    protected UserModel $userModel;

    public function __construct(UserModel $userModel)
    {
        $this->userModel = $userModel;
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
            return ApiResponse::error(
                ['id' => lang('Users.idRequired')],
                'Invalid request'
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
        // Validaciones de reglas de negocio (más allá de integridad de datos)
        // Ejemplo: verificar dominio de email permitido, consultar API externa, etc.
        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            return ApiResponse::validationError($businessErrors);
        }

        // Model maneja validación y timestamps automáticamente
        $userId = $this->userModel->insert([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
        ]);

        if (!$userId) {
            // Obtener errores de validación del Model
            return ApiResponse::validationError($this->userModel->errors());
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
            return ApiResponse::error(
                ['id' => lang('Users.idRequired')],
                'Invalid request'
            );
        }

        $id = (int) $data['id'];

        // Verificar si el usuario existe
        if (!$this->userModel->find($id)) {
            throw new NotFoundException(lang('Users.notFound'));
        }

        // Regla de negocio: al menos un campo requerido
        if (empty($data['email']) && empty($data['username'])) {
            return ApiResponse::error(
                ['fields' => lang('Users.fieldRequired')],
                'Invalid request'
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
            return ApiResponse::validationError($this->userModel->errors());
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
            return ApiResponse::error(
                ['id' => lang('Users.idRequired')],
                'Invalid request'
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
     * Authenticate user with credentials
     * Protected against timing attacks by always verifying password hash
     */
    public function login(array $data): array
    {
        if (empty($data['username']) || empty($data['password'])) {
            return ApiResponse::error(
                ['credentials' => lang('Users.auth.credentialsRequired')],
                'Invalid credentials'
            );
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
            return ApiResponse::error(
                ['credentials' => lang('Users.auth.invalidCredentials')],
                'Invalid credentials'
            );
        }

        return ApiResponse::success([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    /**
     * Register a new user with password
     */
    public function register(array $data): array
    {
        if (empty($data['password'])) {
            return ApiResponse::error(
                ['password' => lang('Users.passwordRequired')],
                'Invalid request'
            );
        }

        // Validate password strength using model rules
        if (!$this->userModel->validate($data)) {
            return ApiResponse::validationError($this->userModel->errors());
        }

        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            return ApiResponse::validationError($businessErrors);
        }

        $userId = $this->userModel->insert([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'     => 'user', // Always 'user' for self-registration (security fix)
        ]); // Returns the inserted ID

        if (!$userId) {
            return ApiResponse::validationError($this->userModel->errors());
        }

        $user = $this->userModel->find($userId);

        return ApiResponse::created([
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    /**
     * Authenticate user and return JWT token with refresh token
     *
     * This method combines login authentication with token generation.
     * Used by AuthController for login endpoint.
     *
     * @param array $data Login credentials
     * @return array Result with access token, refresh token, and user data
     */
    public function loginWithToken(array $data): array
    {
        $result = $this->login($data);

        if (isset($result['errors'])) {
            return $result;
        }

        $user = $result['data'];

        // Generate access token
        $jwtService = \Config\Services::jwtService();
        $accessToken = $jwtService->encode((int) $user['id'], $user['role']);

        // Generate refresh token
        $refreshTokenService = new RefreshTokenService(new \App\Models\RefreshTokenModel());
        $refreshToken = $refreshTokenService->issueRefreshToken((int) $user['id']);

        return ApiResponse::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) env('JWT_ACCESS_TOKEN_TTL', 3600),
            'user' => $user,
        ]);
    }

    /**
     * Register new user and return JWT tokens
     *
     * This method combines user registration with token generation.
     * Used by AuthController for register endpoint.
     *
     * @param array $data Registration data
     * @return array Result with access token, refresh token, and user data
     */
    public function registerWithToken(array $data): array
    {
        $result = $this->register($data);

        if (isset($result['errors'])) {
            return $result;
        }

        $user = $result['data'];

        // Generate access token
        $jwtService = \Config\Services::jwtService();
        $accessToken = $jwtService->encode((int) $user['id'], $user['role']);

        // Generate refresh token
        $refreshTokenService = new RefreshTokenService(new \App\Models\RefreshTokenModel());
        $refreshToken = $refreshTokenService->issueRefreshToken((int) $user['id']);

        // Send verification email
        $verificationService = new VerificationService();
        $verificationService->sendVerificationEmail((int) $user['id']);

        return ApiResponse::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) env('JWT_ACCESS_TOKEN_TTL', 3600),
            'user' => $user,
            'message' => 'Registration successful. Please check your email to verify your account.',
        ]);
    }
}
