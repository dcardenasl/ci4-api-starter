<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
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
     * @param array $data Login credentials (email, password)
     * @return array Result with user data
     */
    public function login(array $data): array
    {
        if (empty($data['email']) || empty($data['password'])) {
            throw new AuthenticationException(
                'Invalid credentials',
                ['credentials' => lang('Users.auth.credentialsRequired')]
            );
        }

        $user = $this->userModel
            ->where('email', $data['email'])
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
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar_url' => $user->avatar_url,
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
        $userEntity = $this->userModel->find((int) $user['id']);

        if (! $userEntity) {
            throw new AuthenticationException(
                'Invalid credentials',
                ['credentials' => lang('Users.auth.invalidCredentials')]
            );
        }

        if (($userEntity->status ?? null) === 'invited') {
            throw new AuthorizationException(
                'Account setup required',
                ['status' => lang('Auth.accountSetupRequired')]
            );
        }

        if (($userEntity->status ?? null) !== 'active') {
            throw new AuthorizationException(
                'Account pending approval',
                ['status' => lang('Auth.accountPendingApproval')]
            );
        }

        $isGoogleOAuth = ($userEntity->oauth_provider ?? null) === 'google';
        if (
            is_email_verification_required()
            && $userEntity->email_verified_at === null
            && ! $isGoogleOAuth
        ) {
            throw new AuthenticationException(
                'Email not verified',
                ['email' => lang('Auth.emailNotVerified')]
            );
        }

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
     * @param array $data Registration data (email, password, names)
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

        // Validate request input (format, required fields, password strength)
        validateOrFail($data, 'auth', 'register');

        $businessErrors = $this->validateBusinessRules($data);
        if (!empty($businessErrors)) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $businessErrors
            );
        }

        $userId = $this->userModel->insert([
            'email'      => $data['email'] ?? null,
            'first_name' => $data['first_name'] ?? null,
            'last_name'  => $data['last_name'] ?? null,
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role'     => 'user', // Always 'user' for self-registration (security fix)
            'status'   => 'pending_approval',
            'approved_at' => null,
            'approved_by' => null,
        ]);

        if (!$userId) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
        }

        $user = $this->userModel->find($userId);

        // Send verification email (don't fail registration if email fails)
        try {
            $this->verificationService->sendVerificationEmail((int) $userId);
        } catch (\Throwable $e) {
            // Log error but don't fail registration
            log_message('error', 'Failed to send verification email: ' . $e->getMessage());
        }

        $message = lang('Auth.registrationPendingApproval');
        if (! is_email_verification_required()) {
            $message = lang('Auth.registrationPendingApprovalNoVerification');
        }

        return ApiResponse::created([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar_url' => $user->avatar_url,
                'role' => $user->role,
            ],
        ], $message);
    }

    /**
     * Register new user and return JWT tokens
     *
     * @param array $data Registration data
     * @return array Result with access token, refresh token, and user data
     */
    public function registerWithToken(array $data): array
    {
        return $this->register($data);
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
