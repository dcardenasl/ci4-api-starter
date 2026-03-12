<?php

declare(strict_types=1);

namespace Config;

trait AuthIdentityServices
{
    public static function authService(bool $getShared = true): \App\Interfaces\Auth\AuthServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        $userRepository = static::userRepository();

        return new \App\Services\Auth\AuthService(
            $userRepository,
            static::registerUserAction($userRepository),
            static::googleLoginAction($userRepository),
            static::auditService(),
            static::authUserMapper(),
            static::sessionManager(),
            static::userAccountGuard(),
            ENVIRONMENT === 'testing'
        );
    }

    public static function authUserMapper(bool $getShared = true): \App\Services\Auth\Support\AuthUserMapper
    {
        if ($getShared) {
            return static::getSharedInstance('authUserMapper');
        }

        return new \App\Services\Auth\Support\AuthUserMapper();
    }

    public static function sessionManager(bool $getShared = true): \App\Services\Auth\Support\SessionManager
    {
        if ($getShared) {
            return static::getSharedInstance('sessionManager');
        }

        $accessTokenTtl = (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600));

        return new \App\Services\Auth\Support\SessionManager(
            static::jwtService(),
            static::refreshTokenService(),
            $accessTokenTtl
        );
    }

    public static function googleAuthHandler(\App\Interfaces\Users\UserRepositoryInterface $userRepository): \App\Services\Auth\Support\GoogleAuthHandler
    {
        return new \App\Services\Auth\Support\GoogleAuthHandler(
            $userRepository,
            static::refreshTokenService()
        );
    }

    public static function registerUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository): \App\Services\Auth\Actions\RegisterUserAction
    {
        return new \App\Services\Auth\Actions\RegisterUserAction(
            $userRepository,
            static::verificationService(),
            static::emailService()
        );
    }

    public static function googleLoginAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository): \App\Services\Auth\Actions\GoogleLoginAction
    {
        return new \App\Services\Auth\Actions\GoogleLoginAction(
            $userRepository,
            static::googleIdentityService(),
            static::googleAuthHandler($userRepository),
            static::sessionManager(),
            static::authUserMapper(),
            static::userAccountGuard(),
            static::auditService(),
            static::emailService()
        );
    }

    public static function userService(bool $getShared = true): \App\Interfaces\Users\UserServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        $userRepository = static::userRepository();

        return new \App\Services\Users\UserService(
            $userRepository,
            static::userResponseMapper(),
            static::userRoleGuard(),
            static::approveUserAction($userRepository),
            static::createUserAction($userRepository),
            static::updateUserAction($userRepository)
        );
    }

    public static function userRoleGuard(bool $getShared = true): \App\Libraries\Security\UserRoleGuard
    {
        if ($getShared) {
            return static::getSharedInstance('userRoleGuard');
        }

        return new \App\Libraries\Security\UserRoleGuard(
            static::securityAuditLogger()
        );
    }

    public static function userInvitationService(bool $getShared = true): \App\Services\Auth\UserInvitationService
    {
        if ($getShared) {
            return static::getSharedInstance('userInvitationService');
        }

        return new \App\Services\Auth\UserInvitationService(
            new \App\Models\PasswordResetModel(),
            static::emailService()
        );
    }

    public static function userResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('userResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Users\UserResponseDTO::class
        );
    }

    public static function createUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository): \App\Services\Users\Actions\CreateUserAction
    {
        return new \App\Services\Users\Actions\CreateUserAction(
            $userRepository,
            static::userInvitationService()
        );
    }

    public static function approveUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository): \App\Services\Users\Actions\ApproveUserAction
    {
        return new \App\Services\Users\Actions\ApproveUserAction(
            $userRepository,
            static::auditService(),
            static::emailService()
        );
    }

    public static function updateUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository): \App\Services\Users\Actions\UpdateUserAction
    {
        return new \App\Services\Users\Actions\UpdateUserAction(
            $userRepository,
            static::userRoleGuard()
        );
    }

    public static function googleIdentityService(bool $getShared = true): \App\Interfaces\Auth\GoogleIdentityServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('googleIdentityService');
        }

        return new \App\Services\Auth\GoogleIdentityService(
            config('Api')->googleClientId
        );
    }

    public static function passwordResetService(bool $getShared = true): \App\Interfaces\Auth\PasswordResetServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('passwordResetService');
        }

        return new \App\Services\Auth\PasswordResetService(
            static::userRepository(),
            new \App\Models\PasswordResetModel(),
            static::emailService(),
            static::refreshTokenService(),
            static::auditService()
        );
    }

    public static function verificationService(bool $getShared = true): \App\Interfaces\Auth\VerificationServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('verificationService');
        }

        return new \App\Services\Auth\VerificationService(
            static::userRepository(),
            static::emailService(),
            static::auditService()
        );
    }

    public static function userAccountGuard(bool $getShared = true): \App\Services\Users\UserAccountGuard
    {
        if ($getShared) {
            return static::getSharedInstance('userAccountGuard');
        }

        return new \App\Services\Users\UserAccountGuard();
    }

    public static function userAccessPolicyService(bool $getShared = true): \App\Services\Users\UserAccountGuard
    {
        return static::userAccountGuard($getShared);
    }
}
