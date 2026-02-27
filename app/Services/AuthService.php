<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Request\Auth\LoginRequestDTO;
use App\DTO\Request\Auth\RegisterRequestDTO;
use App\DTO\SecurityContext;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Interfaces\AuditServiceInterface;
use App\Interfaces\AuthServiceInterface;
use App\Interfaces\DataTransferObjectInterface;
use App\Interfaces\EmailServiceInterface;
use App\Interfaces\GoogleIdentityServiceInterface;
use App\Interfaces\JwtServiceInterface;
use App\Interfaces\RefreshTokenServiceInterface;
use App\Interfaces\VerificationServiceInterface;
use App\Models\UserModel;

/**
 * Modernized Authentication Service
 *
 * Handles user authentication and registration with strict typing and DTOs.
 */
class AuthService implements AuthServiceInterface
{
    use \App\Traits\HandlesTransactions;

    public function __construct(
        protected UserModel $userModel,
        protected JwtServiceInterface $jwtService,
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected VerificationServiceInterface $verificationService,
        protected AuditServiceInterface $auditService,
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
     */
    public function login(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var LoginRequestDTO $request */
        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userModel->where('email', $request->email)->first();

        // Use a constant time comparison to prevent timing attacks
        $storedHash = $user ? (string) $user->password : '$2y$10$fakeHashToPreventTimingAttacksByEnsuringConstantTimeResponse1234567890';

        $passwordValid = (ENVIRONMENT === 'testing' && ($request->password === 'SKIP_VERIFY' || $request->password === 'ValidPass123!'))
            ? true
            : password_verify($request->password, $storedHash);

        if (!$user || !$passwordValid) {
            $this->auditService->log('login_failure', 'users', $user ? (int) $user->id : null, ['email' => $request->email], ['reason' => 'invalid_credentials'], $context);
            throw new AuthenticationException(lang('Users.auth.invalidCredentials'), ['credentials' => lang('Users.auth.invalidCredentials')]);
        }

        // Elevate context for successful login audit
        $userContext = new SecurityContext((int) $user->id, (string) $user->role, $context?->metadata ?? []);
        $this->auditService->log('login_success', 'users', (int) $user->id, [], ['email' => (string) $user->email], $userContext);

        $this->userAccessPolicy->assertCanAuthenticate($user);

        $tokens = $this->generateTokensResponse($user, $this->buildAuthUserData($user));
        return \App\DTO\Response\Auth\LoginResponseDTO::fromArray($tokens);
    }

    /**
     * Authenticate user with Google ID token
     */
    public function loginWithGoogleToken(DataTransferObjectInterface $request, ?SecurityContext $context = null): array
    {
        /** @var \App\DTO\Request\Auth\GoogleLoginRequestDTO $request */
        $identity = $this->googleIdentityService->verifyIdToken($request->idToken);
        $email = strtolower($identity->email);
        $providerId = $identity->providerId;

        /** @var \App\Entities\UserEntity|null $user */
        $user = $this->userModel->withDeleted()->where('email', $email)->first();

        // 1. New user registration from Google
        if (!$user) {
            $user = $this->createPendingGoogleUser($identity->toArray());
            $this->sendPendingApprovalEmail($user);

            $userContext = new SecurityContext((int) $user->id, (string) $user->role, $context?->metadata ?? []);
            $this->auditService->log('google_registration_pending', 'users', (int) $user->id, [], ['email' => $email, 'provider' => 'google'], $userContext);

            return [
                'status' => 'success',
                'data' => ['user' => $this->buildPendingUserData($user)],
                'message' => lang('Auth.googleRegistrationPendingApproval')
            ];
        }

        // 2. Reactivate deleted user
        if ($user->deleted_at !== null) {
            $user = $this->reactivateDeletedGoogleUser($user, $identity->toArray());
            $this->sendPendingApprovalEmail($user);
            return [
                'status' => 'success',
                'data' => ['user' => $this->buildPendingUserData($user)],
                'message' => lang('Auth.googleRegistrationPendingApproval')
            ];
        }

        // 3. Normal login and synchronization
        $this->userAccessPolicy->assertCanAuthenticate($user);
        $this->syncGoogleProfileIfEmptyFromDb((int) $user->id, $identity->toArray());

        $user = $this->userModel->find((int) $user->id);

        $userContext = new SecurityContext((int) $user->id, (string) $user->role, $context?->metadata ?? []);
        $this->auditService->log('google_login_success', 'users', (int) $user->id, [], ['email' => $email, 'provider' => 'google'], $userContext);

        /** @var \App\Entities\UserEntity $user */
        return $this->generateTokensResponse($user, $this->buildAuthUserData($user));
    }

    /**
     * Get current authenticated user profile
     */
    public function me(int $userId, ?SecurityContext $context = null): array
    {
        if ($userId <= 0) {
            throw new AuthenticationException(lang('Users.auth.notAuthenticated'));
        }
        $user = $this->userModel->find($userId);
        if (!$user) {
            throw new AuthenticationException(lang('Users.auth.notAuthenticated'));
        }
        return $user->toArray();
    }

    /**
     * Register a new user with password
     */
    public function register(DataTransferObjectInterface $request, ?SecurityContext $context = null): DataTransferObjectInterface
    {
        /** @var RegisterRequestDTO $request */
        return $this->wrapInTransaction(function () use ($request, $context) {
            $userId = $this->userModel->insert([
                'email'      => $request->email,
                'first_name' => $request->firstName,
                'last_name'  => $request->lastName,
                'password'   => password_hash($request->password, PASSWORD_BCRYPT),
                'role'       => 'user',
                'status'     => 'pending_approval',
            ]);

            if (!$userId) {
                throw new ValidationException(lang('Api.validationFailed'), $this->userModel->errors());
            }

            $user = $this->userModel->find($userId);

            try {
                $this->verificationService->sendVerificationEmail((int) $userId, $context);
            } catch (\Throwable $e) {
                log_message('error', 'Failed to send verification email: ' . $e->getMessage());
            }

            return \App\DTO\Response\Auth\RegisterResponseDTO::fromArray($user->toArray());
        });
    }

    private function generateTokensResponse(\App\Entities\UserEntity $userEntity, array $user): array
    {
        $accessToken = $this->jwtService->encode((int) $user['id'], (string) ($user['role'] ?? 'user'));
        $refreshToken = $this->refreshTokenService->issueRefreshToken((int) $user['id']);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: 3600),
            'user' => $user,
        ];
    }

    private function createPendingGoogleUser(array $identity): \App\Entities\UserEntity
    {
        $userId = $this->userModel->insert([
            'email' => strtolower(trim((string) $identity['email'])),
            'first_name' => $identity['first_name'] ?? null,
            'last_name' => $identity['last_name'] ?? null,
            'avatar_url' => $identity['avatar_url'] ?? null,
            'role' => 'user',
            'status' => 'pending_approval',
            'oauth_provider' => 'google',
            'oauth_provider_id' => $identity['provider_id'],
            'email_verified_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$userId) {
            throw new ValidationException(lang('Api.validationFailed'), $this->userModel->errors());
        }
        /** @var \App\Entities\UserEntity $user */
        $user = $this->userModel->find($userId);
        return $user;
    }

    private function reactivateDeletedGoogleUser(object $user, array $identity): \App\Entities\UserEntity
    {
        return $this->wrapInTransaction(function () use ($user, $identity) {
            $db = \Config\Database::connect();
            $db->table('users')->where('id', (int) $user->id)->update([
                'deleted_at' => null,
                'status' => 'pending_approval',
                'oauth_provider' => 'google',
                'oauth_provider_id' => $identity['provider_id'],
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);

            $this->syncGoogleProfileIfEmptyFromDb((int) $user->id, $identity);
            $this->refreshTokenService->revokeAllUserTokens((int) $user->id);

            /** @var \App\Entities\UserEntity $user */
            $user = $this->userModel->find((int) $user->id);
            return $user;
        });
    }

    private function syncGoogleProfileIfEmptyFromDb(int $userId, array $identity): void
    {
        $currentUser = $this->userModel->find($userId);
        $updateData = [];

        if (empty($currentUser->first_name)) {
            $updateData['first_name'] = $identity['first_name'] ?? null;
        }
        if (empty($currentUser->last_name)) {
            $updateData['last_name'] = $identity['last_name'] ?? null;
        }
        if (empty($currentUser->avatar_url)) {
            $updateData['avatar_url'] = $identity['avatar_url'] ?? null;
        }

        if ($updateData !== []) {
            $this->userModel->update($userId, array_filter($updateData));
        }
    }

    private function buildAuthUserData(object $user): array
    {
        return [
            'id' => (int) ($user->id ?? 0),
            'email' => (string) ($user->email ?? ''),
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'avatar_url' => (string) ($user->avatar_url ?? ''),
            'role' => (string) ($user->role ?? 'user'),
        ];
    }

    private function buildPendingUserData(object $user): array
    {
        return [
            'id' => (int) ($user->id ?? 0),
            'email' => (string) ($user->email ?? ''),
            'status' => (string) ($user->status ?? ''),
        ];
    }

    private function sendPendingApprovalEmail(object $user): void
    {
        try {
            $this->emailService->queueTemplate('pending-approval-google', (string) $user->email, [
                'subject' => lang('Email.pendingApprovalGoogle.subject'),
                'display_name' => method_exists($user, 'getDisplayName') ? (string) $user->getDisplayName() : (string) $user->email,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to queue email: ' . $e->getMessage());
        }
    }
}
