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

    public static function googleAuthHandler(\App\Interfaces\Users\UserRepositoryInterface $userRepository)
    {
        return new \App\Services\Auth\Support\GoogleAuthHandler(
            $userRepository,
            static::refreshTokenService()
        );
    }

    public static function registerUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository)
    {
        return new \App\Services\Auth\Actions\RegisterUserAction(
            $userRepository,
            static::verificationService(),
            static::emailService()
        );
    }

    public static function googleLoginAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository)
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

    public static function userService(bool $getShared = true)
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

    public static function userRoleGuard(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userRoleGuard');
        }

        return new \App\Libraries\Security\UserRoleGuard(
            static::securityAuditLogger()
        );
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

    public static function createUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository)
    {
        return new \App\Services\Users\Actions\CreateUserAction(
            $userRepository,
            static::userInvitationService()
        );
    }

    public static function approveUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository)
    {
        return new \App\Services\Users\Actions\ApproveUserAction(
            $userRepository,
            static::auditService(),
            static::emailService()
        );
    }

    public static function updateUserAction(\App\Interfaces\Users\UserRepositoryInterface $userRepository)
    {
        return new \App\Services\Users\Actions\UpdateUserAction(
            $userRepository,
            static::userRoleGuard()
        );
    }

    public static function googleIdentityService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('googleIdentityService');
        }

        return new \App\Services\Auth\GoogleIdentityService(
            config('Api')->googleClientId
        );
    }

    public static function passwordResetService(bool $getShared = true)
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

    public static function verificationService(bool $getShared = true)
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

        $apiConfig = config('Api');
        $secretKey = $apiConfig->jwtSecretKey;
        $ttl = $apiConfig->jwtAccessTokenTtl;
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

        $apiConfig = config('Api');
        $refreshTokenTtl = $apiConfig->jwtRefreshTokenTtl;
        $accessTokenTtl = $apiConfig->jwtAccessTokenTtl;

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

        $apiConfig = config('Api');
        return new \App\Services\Tokens\TokenRevocationService(
            new \App\Models\TokenBlacklistModel(),
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            static::auditService(),
            static::cache(),
            static::bearerTokenService(),
            $apiConfig->jwtAccessTokenTtl,
            $apiConfig->jwtRevocationCacheTtl
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
            static::tokenRevocationService(),
            static::requestDtoFactory()
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
            static::fileRepository(),
            static::fileResponseMapper(),
            $storage,
            static::auditService(),
            new \App\Libraries\Files\FilenameGenerator($storage),
            new \App\Libraries\Files\MultipartProcessor(),
            new \App\Libraries\Files\Base64Processor(),
            static::virusScannerService()
        );
    }

    public static function fileResponseMapper(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('fileResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\Files\FileResponseDTO::class
        );
    }

    public static function virusScannerService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('virusScannerService');
        }

        return new \App\Services\Files\ClamAvScannerService(
            static::logger(),
            (bool) env('FILES_VIRUS_SCAN_ENABLED', false),
            (string) env('FILES_CLAMAV_ADDRESS', 'tcp://127.0.0.1:3310')
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
            static::auditRepository(),
            static::auditResponseMapper(),
            static::auditWriter(),
            static::queueManager(),
            config('Audit'),
            ENVIRONMENT !== 'testing',
            '127.0.0.1',
            'system',
            static::auditPayloadSanitizer()
        );
    }

    public static function auditWriter(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auditWriter');
        }

        return new \App\Services\System\AuditWriter(
            static::auditRepository()
        );
    }

    public static function auditPayloadSanitizer(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auditPayloadSanitizer');
        }

        return new \App\Services\System\AuditPayloadSanitizer();
    }

    public static function requestAuditContextFactory(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('requestAuditContextFactory');
        }

        return new \App\Support\RequestAuditContextFactory();
    }

    public static function requestDataCollector(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('requestDataCollector');
        }

        return new \App\Support\RequestDataCollector();
    }

    public static function requestDtoFactory(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('requestDtoFactory');
        }

        return new \App\Support\RequestDtoFactory(service('validation'));
    }

    public static function responseDtoFactory(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('responseDtoFactory');
        }

        return new \App\Support\ResponseDtoFactory();
    }

    public static function securityAuditLogger(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('securityAuditLogger');
        }

        return new \App\Services\System\SecurityAuditLogger(
            static::auditService(),
            static::requestAuditContextFactory()
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

        $apiConfig = config('Api');
        $slowQueryThreshold = $apiConfig->slowQueryThreshold;
        $p95Target = $apiConfig->sloP95TargetMs;

        return new \App\Services\System\MetricsService(
            new \App\Models\RequestLogModel(),
            new \App\Models\MetricModel(),
            $slowQueryThreshold,
            $p95Target
        );
    }

    public static function catalogService(bool $getShared = true): \App\Interfaces\System\CatalogServiceInterface
    {
        if ($getShared) {
            return static::getSharedInstance('catalogService');
        }

        return new \App\Services\System\CatalogService(
            static::auditRepository()
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
     | REPOSITORIES
     |--------------------------------------------------------------------------
     */

    public static function demoproductRepository(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('demoproductRepository');
        }

        return new \App\Repositories\GenericRepository(new \App\Models\DemoproductModel());
    }

    public static function userRepository(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userRepository');
        }

        return new \App\Repositories\Users\UserRepository(static::userModel());
    }

    public static function auditRepository(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auditRepository');
        }

        return new \App\Repositories\System\AuditRepository(model(\App\Models\AuditLogModel::class));
    }

    public static function fileRepository(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('fileRepository');
        }

        return new \App\Repositories\Files\FileRepository(model(\App\Models\FileModel::class));
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
            static::demoproductRepository(),
            static::demoproductResponseMapper()
        );
    }

}
