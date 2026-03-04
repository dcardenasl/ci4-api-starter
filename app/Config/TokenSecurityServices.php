<?php

declare(strict_types=1);

namespace Config;

trait TokenSecurityServices
{
    public static function jwtService(bool $getShared = true): \App\Interfaces\Tokens\JwtServiceInterface
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

    public static function refreshTokenService(bool $getShared = true): \App\Interfaces\Tokens\RefreshTokenServiceInterface
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

    public static function tokenRevocationService(bool $getShared = true): \App\Interfaces\Tokens\TokenRevocationServiceInterface
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

    public static function bearerTokenService(bool $getShared = true): \App\Services\Tokens\BearerTokenService
    {
        if ($getShared) {
            return static::getSharedInstance('bearerTokenService');
        }

        return new \App\Services\Tokens\BearerTokenService();
    }

    public static function apiKeyService(bool $getShared = true): \App\Interfaces\Tokens\ApiKeyServiceInterface
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

    public static function apiKeyResponseMapper(bool $getShared = true): \App\Interfaces\Mappers\ResponseMapperInterface
    {
        if ($getShared) {
            return static::getSharedInstance('apiKeyResponseMapper');
        }

        return new \App\Services\Core\Mappers\DtoResponseMapper(
            \App\DTO\Response\ApiKeys\ApiKeyResponseDTO::class
        );
    }

    public static function createApiKeyAction(\App\Models\ApiKeyModel $apiKeyModel): \App\Services\Tokens\Actions\CreateApiKeyAction
    {
        return new \App\Services\Tokens\Actions\CreateApiKeyAction(
            $apiKeyModel
        );
    }

    public static function updateApiKeyAction(\App\Models\ApiKeyModel $apiKeyModel): \App\Services\Tokens\Actions\UpdateApiKeyAction
    {
        return new \App\Services\Tokens\Actions\UpdateApiKeyAction(
            $apiKeyModel
        );
    }

    public static function authTokenService(bool $getShared = true): \App\Interfaces\Tokens\AuthTokenServiceInterface
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
}
