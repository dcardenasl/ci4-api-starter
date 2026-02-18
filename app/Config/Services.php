<?php

namespace Config;

use App\HTTP\ApiRequest;
use CodeIgniter\Config\BaseService;
use CodeIgniter\HTTP\UserAgent;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * The IncomingRequest class models an HTTP request.
     *
     * @return ApiRequest
     */
    public static function incomingrequest(?App $config = null, bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('request', $config);
        }

        $config ??= config(App::class);

        return new ApiRequest(
            $config,
            static::get('uri'),
            'php://input',
            new UserAgent(),
        );
    }

    /**
     * User Model
     *
     * Provides UserModel instance
     *
     * @param bool $getShared
     * @return \App\Models\UserModel
     */
    public static function userModel(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userModel');
        }

        return new \App\Models\UserModel();
    }

    /**
     * User Service
     *
     * Proporciona UserService con todas sus dependencias inyectadas
     *
     * @param bool $getShared
     * @return \App\Services\UserService
     */
    public static function userService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('userService');
        }

        return new \App\Services\UserService(
            static::userModel(),
            static::emailService(),
            new \App\Models\PasswordResetModel()
        );
    }

    /**
     * Auth Service
     *
     * Provides authentication and registration functionality
     *
     * @param bool $getShared
     * @return \App\Services\AuthService
     */
    public static function authService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        return new \App\Services\AuthService(
            new \App\Models\UserModel(),
            static::jwtService(),
            static::refreshTokenService(),
            static::verificationService()
        );
    }

    /**
     * JWT Service
     *
     * Provides JWT token encoding and decoding functionality
     *
     * @param bool $getShared
     * @return \App\Services\JwtService
     */
    public static function jwtService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('jwtService');
        }

        return new \App\Services\JwtService();
    }

    /**
     * Email Service
     *
     * Provides email functionality using Symfony Mailer
     *
     * @param bool $getShared
     * @return \App\Services\EmailService
     */
    public static function emailService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('emailService');
        }

        return new \App\Services\EmailService();
    }

    /**
     * Queue Manager
     *
     * Provides queue management functionality
     *
     * @param bool $getShared
     * @return \App\Libraries\Queue\QueueManager
     */
    public static function queueManager(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('queueManager');
        }

        return new \App\Libraries\Queue\QueueManager();
    }

    /**
     * Verification Service
     *
     * Provides email verification functionality
     *
     * @param bool $getShared
     * @return \App\Services\VerificationService
     */
    public static function verificationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('verificationService');
        }

        return new \App\Services\VerificationService(
            new \App\Models\UserModel(),
            static::emailService()
        );
    }

    /**
     * Password Reset Service
     *
     * Provides password reset functionality
     *
     * @param bool $getShared
     * @return \App\Services\PasswordResetService
     */
    public static function passwordResetService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('passwordResetService');
        }

        return new \App\Services\PasswordResetService(
            new \App\Models\UserModel(),
            new \App\Models\PasswordResetModel(),
            static::emailService()
        );
    }

    /**
     * Token Revocation Service
     *
     * Provides token revocation and blacklist functionality
     *
     * @param bool $getShared
     * @return \App\Services\TokenRevocationService
     */
    public static function tokenRevocationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('tokenRevocationService');
        }

        return new \App\Services\TokenRevocationService(
            new \App\Models\TokenBlacklistModel(),
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            static::cache()
        );
    }

    /**
     * Refresh Token Service
     *
     * Manages refresh token lifecycle
     *
     * @param bool $getShared
     * @return \App\Services\RefreshTokenService
     */
    public static function refreshTokenService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('refreshTokenService');
        }

        return new \App\Services\RefreshTokenService(
            new \App\Models\RefreshTokenModel(),
            static::jwtService(),
            new \App\Models\UserModel()
        );
    }

    /**
     * File Service
     *
     * Provides file upload, download, and deletion with storage abstraction
     *
     * @param bool $getShared
     * @return \App\Services\FileService
     */
    public static function fileService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('fileService');
        }

        return new \App\Services\FileService(
            new \App\Models\FileModel(),
            new \App\Libraries\Storage\StorageManager()
        );
    }

    /**
     * Audit Service
     *
     * Provides audit logging functionality
     *
     * @param bool $getShared
     * @return \App\Services\AuditService
     */
    public static function auditService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('auditService');
        }

        return new \App\Services\AuditService(
            new \App\Models\AuditLogModel()
        );
    }

    /**
     * Storage Manager
     *
     * Provides unified file storage interface
     *
     * @param bool $getShared
     * @return \App\Libraries\Storage\StorageManager
     */
    public static function storageManager(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('storageManager');
        }

        return new \App\Libraries\Storage\StorageManager();
    }

    /**
     * Input Validation Service
     *
     * Provides centralized input validation functionality
     *
     * @param bool $getShared
     * @return \App\Services\InputValidationService
     */
    public static function inputValidationService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('inputValidationService');
        }

        return new \App\Services\InputValidationService();
    }

    /**
     * API Key Model
     *
     * Provides ApiKeyModel instance
     *
     * @param bool $getShared
     * @return \App\Models\ApiKeyModel
     */
    public static function apiKeyModel(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyModel');
        }

        return new \App\Models\ApiKeyModel();
    }

    /**
     * API Key Service
     *
     * Provides ApiKeyService with all dependencies injected
     *
     * @param bool $getShared
     * @return \App\Services\ApiKeyService
     */
    public static function apiKeyService(bool $getShared = true)
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyService');
        }

        return new \App\Services\ApiKeyService(
            static::apiKeyModel()
        );
    }
}
