<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other objects/libraries that the system may
 * need to use. Core CodeIgniter services are located in the
 * system directory, but others can be found here.
 */
class Services extends BaseService
{
    /*
     |--------------------------------------------------------------------------
     | DOMAIN: AUTH & IDENTITY
     |--------------------------------------------------------------------------
     */

    public static function authService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        $userModel = static::userModel();

        return new \App\Services\Auth\AuthService(
            $userModel,
            static::registerUserAction($userModel),
            static::googleLoginAction($userModel),
            static::auditService(),
            static::authUserMapper(),
            static::sessionManager(),
            static::userAccountGuard()
        );
    }

    public static function authUserMapper(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('authUserMapper');
        }

        return new \App\Services\Auth\Support\AuthUserMapper();
    }

    public static function sessionManager(bool $getShared = true)
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

    public static function googleAuthHandler(\App\Models\UserModel $userModel)
    {
        return new \App\Services\Auth\Support\GoogleAuthHandler(
            $userModel,
            static::refreshTokenService()
        );
    }

    public static function registerUserAction(\App\Models\UserModel $userModel)
    {
        return new \App\Services\Auth\Actions\RegisterUserAction(
            $userModel,
            static::verificationService()
        );
    }

    public static function googleLoginAction(\App\Models\UserModel $userModel)
    {
        return new \App\Services\Auth\Actions\GoogleLoginAction(
            $userModel,
            static::googleIdentityService(),
            static::googleAuthHandler($userModel),
            static::sessionManager(),
            static::authUserMapper(),
            static::userAccountGuard(),
            static::auditService(),
            static::emailService()
        );
    }

    public static function userService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        $userModel = static::userModel();

        return new \App\Services\Users\UserService(
            $userModel,
            static::userResponseMapper(),
            static::userRoleGuard(),
            static::approveUserAction($userModel),
            static::createUserAction($userModel),
            static::updateUserAction($userModel)
        );
    }

    public static function userRoleGuard(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userRoleGuard');
        }

        return new \App\Libraries\Security\UserRoleGuard();
    }

    public static function userInvitationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userInvitationService');
        }

        return new \App\Services\Auth\UserInvitationService(
            new \App\Models\PasswordResetModel(),
            static::emailService()
        );
    }

    public static function userResponseMapper(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Users\UserResponseDTO::class
        );
    }

    public static function createUserAction(\App\Models\UserModel $userModel)
    {
        return new \App\Services\Users\Actions\CreateUserAction(
            $userModel,
            static::userInvitationService()
        );
    }

    public static function approveUserAction(\App\Models\UserModel $userModel)
    {
        return new \App\Services\Users\Actions\ApproveUserAction(
            $userModel,
            static::auditService(),
            static::emailService()
        );
    }

    public static function updateUserAction(\App\Models\UserModel $userModel)
    {
        return new \App\Services\Users\Actions\UpdateUserAction(
            $userModel,
            static::userRoleGuard()
        );
    }

    public static function googleIdentityService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('googleIdentityService');
        }

        return new \App\Services\Auth\GoogleIdentityService(
            trim((string) env('GOOGLE_CLIENT_ID', ''))
        );
    }

    public static function passwordResetService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('passwordResetService');
        }

        return new \App\Services\Auth\PasswordResetService(
            static::userModel(),
            new \App\Models\PasswordResetModel(),
            static::emailService(),
            static::refreshTokenService(),
            static::auditService()
        );
    }

    public static function verificationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('verificationService');
        }

        return new \App\Services\Auth\VerificationService(
            static::userModel(),
            static::emailService(),
            static::auditService()
        );
    }

    public static function userAccountGuard(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userAccountGuard');
        }

        return new \App\Services\Users\UserAccountGuard();
    }

    public static function userAccessPolicyService(bool $getShared = true)
    {
        return static::userAccountGuard($getShared);
    }

    /*
     |--------------------------------------------------------------------------
     | DOMAIN: TOKENS & SECURITY
     |--------------------------------------------------------------------------
     */

    public static function jwtService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('jwtService');
        }

        $secretKey = trim((string) (getenv('JWT_SECRET_KEY') ?: env('JWT_SECRET_KEY', '')));
        $ttl = (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600));
        $issuer = (string) env('app.baseURL', 'http://localhost:8080');

        return new \App\Services\Tokens\JwtService(
            $secretKey,
            $ttl,
            $issuer
        );
    }

    public static function refreshTokenService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('refreshTokenService');
        }

        $refreshTokenTtl = (int) (getenv('JWT_REFRESH_TOKEN_TTL') ?: env('JWT_REFRESH_TOKEN_TTL', 604800));
        $accessTokenTtl = (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: env('JWT_ACCESS_TOKEN_TTL', 3600));

        return new \App\Services\Tokens\RefreshTokenService(
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            static::userModel(),
            static::userAccountGuard(),
            $refreshTokenTtl,
            $accessTokenTtl
        );
    }

    public static function tokenRevocationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('tokenRevocationService');
        }

        return new \App\Services\Tokens\TokenRevocationService(
            new \App\Models\TokenBlacklistModel(),
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            static::auditService(),
            static::cache(),
            static::bearerTokenService(),
            (int) (getenv('JWT_ACCESS_TOKEN_TTL') ?: 3600),
            (int) (getenv('JWT_REVOCATION_CACHE_TTL') ?: 60)
        );
    }

    public static function bearerTokenService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('bearerTokenService');
        }

        return new \App\Services\Tokens\BearerTokenService();
    }

    public static function apiKeyService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyService');
        }

        $apiKeyModel = new \App\Models\ApiKeyModel();

        return new \App\Services\Tokens\ApiKeyService(
            $apiKeyModel,
            static::apiKeyResponseMapper(),
            static::createApiKeyAction($apiKeyModel),
            static::updateApiKeyAction($apiKeyModel)
        );
    }

    public static function apiKeyResponseMapper(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class
        );
    }

    public static function createApiKeyAction(\App\Models\ApiKeyModel $apiKeyModel)
    {
        return new \App\Services\Tokens\Actions\CreateApiKeyAction(
            $apiKeyModel
        );
    }

    public static function updateApiKeyAction(\App\Models\ApiKeyModel $apiKeyModel)
    {
        return new \App\Services\Tokens\Actions\UpdateApiKeyAction(
            $apiKeyModel
        );
    }

    public static function authTokenService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('authTokenService');
        }

        return new \App\Services\Tokens\AuthTokenService(
            static::refreshTokenService(),
            static::tokenRevocationService()
        );
    }

    /*
     |--------------------------------------------------------------------------
     | DOMAIN: FILES
     |--------------------------------------------------------------------------
     */

    public static function fileService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('fileService');
        }

        $storage = static::storageManager();

        return new \App\Services\Files\FileService(
            new \App\Models\FileModel(),
            $storage,
            static::auditService(),
            new \App\Libraries\Files\FilenameGenerator($storage),
            new \App\Libraries\Files\MultipartProcessor(),
            new \App\Libraries\Files\Base64Processor()
        );
    }

    public static function storageManager(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('storageManager');
        }

        return new \App\Libraries\Storage\StorageManager();
    }

    /*
     |--------------------------------------------------------------------------
     | DOMAIN: SYSTEM & MONITORING
     |--------------------------------------------------------------------------
     */

    public static function emailService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('emailService');
        }

        $fromAddress = (string) (env('EMAIL_FROM_ADDRESS') ?: 'no-reply@example.com');
        $fromName = (string) (env('EMAIL_FROM_NAME') ?: 'CI4 API');
        $defaultLocale = (string) config('App')->defaultLocale;

        // EmailService requires MailerInterface (null for now) and QueueManager
        return new \App\Services\System\EmailService(
            null,
            static::queueManager(),
            $fromAddress,
            $fromName,
            $defaultLocale
        );
    }

    public static function auditService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        return new \App\Services\System\AuditService(
            new \App\Models\AuditLogModel(),
            static::auditResponseMapper()
        );
    }

    public static function auditResponseMapper(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auditResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Audit\AuditResponseDTO::class
        );
    }

    public static function metricsService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('metricsService');
        }

        $slowQueryThreshold = (int) env('SLOW_QUERY_THRESHOLD', 1000);
        $p95Target = (int) env('SLO_API_P95_TARGET_MS', 500);

        return new \App\Services\System\MetricsService(
            new \App\Models\RequestLogModel(),
            new \App\Models\MetricModel(),
            $slowQueryThreshold,
            $p95Target
        );
    }

    public static function queueManager(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('queueManager');
        }

        return new \App\Libraries\Queue\QueueManager();
    }

    /*
     |--------------------------------------------------------------------------
     | MODELS (Shorthands)
     |--------------------------------------------------------------------------
     */

    public static function userModel(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userModel');
        }

        return new \App\Models\UserModel();
    }

    public static function apiKeyModel(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyModel');
        }

        return new \App\Models\ApiKeyModel();
    }

    /**
     * The Request Service
     *
     * @param \Config\App|bool $getShared
     */
    public static function request($getShared = true)
    {
        if (is_bool($getShared) && $getShared) {
            return static::getSharedInstance('request');
        }

        $config = $getShared instanceof \Config\App ? $getShared : config('App');

        return new \App\HTTP\ApiRequest(
            $config,
            static::uri(),
            'php://input',
            new \CodeIgniter\HTTP\UserAgent()
        );
    }
    public static function demoproductResponseMapper(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('demoproductResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Catalog\DemoproductResponseDTO::class
        );
    }

    public static function demoproductService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('demoproductService');
        }

        return new \App\Services\Catalog\DemoproductService(
            new \App\Models\DemoproductModel(),
            static::demoproductResponseMapper()
        );
    }

}
