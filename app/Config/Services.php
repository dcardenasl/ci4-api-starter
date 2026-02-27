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

        return new \App\Services\Auth\AuthService(
            static::userModel(),
            static::verificationService(),
            static::auditService(),
            new \App\Services\Auth\Support\AuthUserMapper(),
            new \App\Services\Auth\Support\GoogleAuthHandler(
                static::userModel(),
                static::refreshTokenService()
            ),
            new \App\Services\Auth\Support\SessionManager(
                static::jwtService(),
                static::refreshTokenService()
            ),
            static::userAccountGuard()
        );
    }

    public static function userService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        return new \App\Services\Users\UserService(
            static::userModel(),
            static::emailService(),
            static::auditService(),
            new \App\Libraries\Security\UserRoleGuard(),
            new \App\Services\Auth\UserInvitationService(
                new \App\Models\PasswordResetModel(),
                static::emailService()
            )
        );
    }

    public static function googleIdentityService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('googleIdentityService');
        }

        return new \App\Services\Auth\GoogleIdentityService(
            static::userModel(),
            static::jwtService(),
            static::auditService()
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

        return new \App\Services\Tokens\RefreshTokenService(
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            static::userModel(),
            static::userAccountGuard()
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

        return new \App\Services\Tokens\ApiKeyService(
            new \App\Models\ApiKeyModel()
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

        // EmailService requires MailerInterface (null for now) and QueueManager
        return new \App\Services\System\EmailService(
            null,
            new \App\Libraries\Queue\QueueManager()
        );
    }

    public static function auditService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        return new \App\Services\System\AuditService(
            new \App\Models\AuditLogModel()
        );
    }

    public static function metricsService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('metricsService');
        }

        return new \App\Services\System\MetricsService(
            new \App\Models\RequestLogModel(),
            new \App\Models\MetricModel()
        );
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
}
