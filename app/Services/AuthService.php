<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuthServiceInterface;
use App\Interfaces\JwtServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Interfaces\VerificationServiceInterface;
use App\Libraries\ApiResponse;
use App\Models\UserModel;

/**
 * Authentication Service
 *
 * Handles user authentication and registration operations.
 * Separated from UserService following Single Responsibility Principle.
 */
class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected UserModel $userModel,
        protected JwtServiceInterface $jwtService,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected VerificationServiceInterface $verificationService
    ) {
    }

    /**
     * Authenticate user with credentials
     * Protected against timing attacks by always verifying password hash
     *
     * @param array $data Login credentials (username/email, password)
     * @return array Result with user data
     */
    public function login(array $data): array
    {
        if (empty($data['username']) || empty($data['password'])) {
            throw new AuthenticationException(
                'Invalid credentials',
                ['credentials' => lang('Users.auth.credentialsRequired')]
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
            throw new AuthenticationException(
                'Invalid credentials',
                ['credentials' => lang('Users.auth.invalidCredentials')]
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
     * Authenticate user and return JWT token with refresh token
     *
     * @param array $data Login credentials
     * @return array Result with access token, refresh token, and user data
     */
    public function loginWithToken(array $data): array
    {
        $result = $this->login($data);

        // login() now throws exceptions on error, so if we get here, it's successful
        $user = $result['data'];

        // Generate access token
        $accessToken = $this->jwtService->encode((int) $user['id'], $user['role']);

        // Generate refresh token
        $refreshToken = $this->refreshTokenService->issueRefreshToken((int) $user['id']);

        return ApiResponse::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600)),
            'user' => $user,
        ]);
    }

    /**
     * Register a new user with password
     *
     * @param array $data Registration data (username, email, password)
     * @return array Result with created user data
     */
    public function register(array $data): array
    {
        if (empty($data['password'])) {
            throw new BadRequestException(
                'Invalid request',
                ['password' => lang('Users.passwordRequired')]
            );
        }

        // Validate password strength using model rules
        if (!$this->userModel->validate($data)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
        }

        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $businessErrors
            );
        }

        $userId = $this->userModel->insert([
            'email'    => $data['email'] ?? null,
            'username' => $data['username'] ?? null,
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'     => 'user', // Always 'user' for self-registration (security fix)
        ]);

        if (!$userId) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
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
     * Register new user and return JWT tokens
     *
     * @param array $data Registration data
     * @return array Result with access token, refresh token, and user data
     */
    public function registerWithToken(array $data): array
    {
        $result = $this->register($data);

        // register() now throws exceptions on error, so if we get here, it's successful
        $user = $result['data'];

        // Generate access token
        $accessToken = $this->jwtService->encode((int) $user['id'], $user['role']);

        // Generate refresh token
        $refreshToken = $this->refreshTokenService->issueRefreshToken((int) $user['id']);

        // Send verification email (don't fail registration if email fails)
        try {
            $this->verificationService->sendVerificationEmail((int) $user['id']);
        } catch (\Throwable $e) {
            // Log error but don't fail registration
            log_message('error', 'Failed to send verification email: ' . $e->getMessage());
        }

        return ApiResponse::success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600)),
            'user' => $user,
            'message' => 'Registration successful. Please check your email to verify your account.',
        ]);
    }

    /**
     * Validaciones de reglas de negocio específicas
     * Separadas de las reglas de integridad del Model
     *
     * @param array $data
     * @return array
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
