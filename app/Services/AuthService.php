<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuthServiceInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\GoogleIdentityServiceInterface;
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
        protected VerificationServiceInterface $verificationService,
        protected ?UserAccessPolicyService $userAccessPolicy = null,
        protected ?GoogleIdentityServiceInterface $googleIdentityService = null,
        protected ?EmailServiceInterface $emailService = null
    ) {
        $this->userAccessPolicy ??= \Config\Services::userAccessPolicyService();
        $this->googleIdentityService ??= \Config\Services::googleIdentityService();
        $this->emailService ??= \Config\Services::emailService();
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
                lang('Users.auth.invalidCredentials'),
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
                lang('Users.auth.invalidCredentials'),
                ['credentials' => lang('Users.auth.invalidCredentials')]
            );
        }

        return ApiResponse::success($this->buildAuthUserData($user));
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
                lang('Users.auth.invalidCredentials'),
                ['credentials' => lang('Users.auth.invalidCredentials')]
            );
        }

        $this->userAccessPolicy->assertCanAuthenticate($userEntity);

        return $this->generateTokensResponse($userEntity, $user);
    }

    /**
     * Authenticate user with Google ID token.
     *
     * @param array $data Request data with id_token
     * @return array
     */
    public function loginWithGoogleToken(array $data): array
    {
        validateOrFail($data, 'auth', 'google_login');

        $identity = $this->googleIdentityService->verifyIdToken((string) ($data['id_token'] ?? ''));

        $email = strtolower(trim((string) ($identity['email'] ?? '')));
        if ($email === '') {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['email' => lang('Auth.googleInvalidToken')]
            );
        }

        $providerId = trim((string) ($identity['provider_id'] ?? ''));
        if ($providerId === '') {
            throw new AuthenticationException(
                lang('Auth.googleInvalidToken'),
                ['id_token' => lang('Auth.googleInvalidToken')]
            );
        }

        $user = $this->userModel
            ->withDeleted()
            ->where('email', $email)
            ->first();

        if (! $user) {
            $user = $this->createPendingGoogleUser($identity);
            $this->sendPendingApprovalEmail($user);

            return ApiResponse::success(
                ['user' => $this->buildPendingUserData($user)],
                lang('Auth.googleRegistrationPendingApproval')
            );
        }

        $oauthProvider = $user->oauth_provider ?? null;
        $oauthProviderId = $user->oauth_provider_id ?? null;

        if ($oauthProvider !== null && $oauthProvider !== 'google') {
            throw new ConflictException(
                lang('Auth.googleProviderMismatch'),
                ['oauth_provider' => lang('Auth.googleProviderMismatch')]
            );
        }

        if ($oauthProvider === 'google' && $oauthProviderId !== null && $oauthProviderId !== '' && $oauthProviderId !== $providerId) {
            throw new ConflictException(
                lang('Auth.googleProviderIdentityMismatch'),
                ['oauth_provider_id' => lang('Auth.googleProviderIdentityMismatch')]
            );
        }

        if (($user->deleted_at ?? null) !== null) {
            $user = $this->reactivateDeletedGoogleUser($user, $identity);
            $this->sendPendingApprovalEmail($user);

            return ApiResponse::success(
                ['user' => $this->buildPendingUserData($user)],
                lang('Auth.googleRegistrationPendingApproval')
            );
        }

        if (($user->status ?? null) === 'pending_approval') {
            throw new AuthorizationException(
                lang('Auth.accountPendingApproval'),
                ['status' => lang('Auth.accountPendingApproval')]
            );
        }

        $updateData = [];

        if ($oauthProvider === null) {
            $updateData['oauth_provider'] = 'google';
        }

        if ($oauthProviderId === null || $oauthProviderId === '') {
            $updateData['oauth_provider_id'] = $providerId;
        }

        $this->syncGoogleProfileIfEmpty($updateData, $user, $identity);

        if ($user->email_verified_at === null) {
            $updateData['email_verified_at'] = date('Y-m-d H:i:s');
        }

        if (($user->status ?? null) === 'invited') {
            $updateData['status'] = 'active';
            $updateData['invited_at'] = null;
            $updateData['invited_by'] = null;
            if ($user->approved_at === null) {
                $updateData['approved_at'] = date('Y-m-d H:i:s');
            }
        }

        if ($updateData !== []) {
            $this->userModel->update((int) $user->id, $updateData);
            $user = $this->userModel->find((int) $user->id) ?? $user;
        }

        if (($user->status ?? null) !== 'active') {
            throw new AuthorizationException(
                lang('Auth.accountPendingApproval'),
                ['status' => lang('Auth.accountPendingApproval')]
            );
        }

        $this->userAccessPolicy->assertCanAuthenticate($user);

        return $this->generateTokensResponse($user, $this->buildAuthUserData($user));
    }

    /**
     * Get current authenticated user profile.
     *
     * @param array $data
     * @return array
     */
    public function me(array $data): array
    {
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : 0;

        if ($userId <= 0) {
            throw new AuthenticationException(lang('Users.auth.notAuthenticated'));
        }

        $user = $this->userModel->find($userId);
        if (! $user) {
            throw new AuthenticationException(lang('Users.auth.notAuthenticated'));
        }

        return ApiResponse::success($user->toArray());
    }

    /**
     * Generate tokens and build response
     *
     * @param \App\Entities\UserEntity $userEntity
     * @param array $user
     * @return array
     */
    private function generateTokensResponse($userEntity, array $user): array
    {
        $accessToken = $this->jwtService->encode((int) $user['id'], $user['role']);
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
                lang('Api.invalidRequest'),
                ['password' => lang('Users.passwordRequired')]
            );
        }

        // Validate request input (format, required fields, password strength)
        validateOrFail($data, 'auth', 'register');

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
            $this->verificationService->sendVerificationEmail((int) $userId, [
                'client_base_url' => $data['client_base_url'] ?? null,
            ]);
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

    private function createPendingGoogleUser(array $identity): object
    {
        $userId = $this->userModel->insert([
            'email' => strtolower(trim((string) $identity['email'])),
            'first_name' => $identity['first_name'] ?? null,
            'last_name' => $identity['last_name'] ?? null,
            'avatar_url' => $identity['avatar_url'] ?? null,
            'password' => null,
            'role' => 'user',
            'status' => 'pending_approval',
            'approved_at' => null,
            'approved_by' => null,
            'oauth_provider' => 'google',
            'oauth_provider_id' => $identity['provider_id'],
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        if (! $userId) {
            throw new ValidationException(
                lang('Api.validationFailed'),
                $this->userModel->errors()
            );
        }

        $user = $this->userModel->find($userId);
        if (! $user) {
            throw new ValidationException(lang('Api.validationFailed'));
        }

        return $user;
    }

    private function reactivateDeletedGoogleUser(object $user, array $identity): object
    {
        $db = \Config\Database::connect();

        try {
            $db->transStart();

            $db->table('users')
                ->where('id', (int) $user->id)
                ->update([
                    'deleted_at' => null,
                    'status' => 'pending_approval',
                    'approved_at' => null,
                    'approved_by' => null,
                    'oauth_provider' => 'google',
                    'oauth_provider_id' => $identity['provider_id'],
                    'email_verified_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            $this->syncGoogleProfileIfEmptyFromDb((int) $user->id, $identity);
            $this->refreshTokenService->revokeAllUserTokens((int) $user->id);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException(lang('Api.requestFailed'));
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to reactivate Google user: ' . $e->getMessage());
            throw new ValidationException(lang('Api.validationFailed'));
        }

        $reactivatedUser = $this->userModel->find((int) $user->id);
        if (! $reactivatedUser) {
            throw new ValidationException(lang('Api.validationFailed'));
        }

        return $reactivatedUser;
    }

    private function syncGoogleProfileIfEmpty(array &$updateData, object $user, array $identity): void
    {
        if (($user->first_name ?? null) === null || trim((string) $user->first_name) === '') {
            $firstName = trim((string) ($identity['first_name'] ?? ''));
            if ($firstName !== '') {
                $updateData['first_name'] = $firstName;
            }
        }

        if (($user->last_name ?? null) === null || trim((string) $user->last_name) === '') {
            $lastName = trim((string) ($identity['last_name'] ?? ''));
            if ($lastName !== '') {
                $updateData['last_name'] = $lastName;
            }
        }

        if (($user->avatar_url ?? null) === null || trim((string) $user->avatar_url) === '') {
            $avatarUrl = trim((string) ($identity['avatar_url'] ?? ''));
            if ($avatarUrl !== '') {
                $updateData['avatar_url'] = $avatarUrl;
            }
        }
    }

    private function syncGoogleProfileIfEmptyFromDb(int $userId, array $identity): void
    {
        $currentUser = $this->userModel->find($userId);
        if (! $currentUser) {
            return;
        }

        $updateData = [];
        $this->syncGoogleProfileIfEmpty($updateData, $currentUser, $identity);

        if ($updateData !== []) {
            $this->userModel->update($userId, $updateData);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuthUserData(object $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar_url' => $user->avatar_url,
            'role' => $user->role,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPendingUserData(object $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
        ];
    }

    private function sendPendingApprovalEmail(object $user): void
    {
        if (empty($user->email)) {
            return;
        }

        try {
            $displayName = method_exists($user, 'getDisplayName')
                ? $user->getDisplayName()
                : (string) ($user->email ?? 'User');

            $this->emailService->queueTemplate('pending-approval-google', (string) $user->email, [
                'subject' => lang('Email.pendingApprovalGoogle.subject'),
                'display_name' => $displayName,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to queue pending approval Google email: ' . $e->getMessage());
        }
    }
}
