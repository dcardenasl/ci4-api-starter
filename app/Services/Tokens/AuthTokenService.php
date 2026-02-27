<?php

declare(strict_types=1);

namespace App\Services\Tokens;

use App\DTO\SecurityContext;
use App\Exceptions\AuthenticationException;
use App\Interfaces\Tokens\RefreshTokenServiceInterface;
use App\Interfaces\Tokens\TokenRevocationServiceInterface;
use App\Support\OperationResult;

/**
 * Modernized Auth Token Service
 *
 * Facade for token management operations.
 */
class AuthTokenService implements \App\Interfaces\Tokens\AuthTokenServiceInterface
{
    public function __construct(
        protected RefreshTokenServiceInterface $refreshTokenService,
        protected TokenRevocationServiceInterface $tokenRevocationService
    ) {
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(\App\DTO\Request\Identity\RefreshTokenRequestDTO $request, ?SecurityContext $context = null): \App\Interfaces\DataTransferObjectInterface
    {
        return $this->refreshTokenService->refreshAccessToken($request);
    }

    /**
     * Revoke current access token from authorization header
     */
    public function revokeToken(string $authorizationHeader, ?SecurityContext $context = null): OperationResult
    {
        $this->tokenRevocationService->revokeAccessToken([
            'authorization_header' => $authorizationHeader
        ], $context);

        return OperationResult::success(null, lang('Tokens.tokenRevokedSuccess'));
    }

    /**
     * Revoke all user tokens
     */
    public function revokeAllUserTokens(int $userId, ?SecurityContext $context = null): OperationResult
    {
        if ($userId <= 0) {
            throw new AuthenticationException(lang('Auth.authRequired'));
        }

        $this->tokenRevocationService->revokeAllUserTokens($userId, $context);

        return OperationResult::success(null, lang('Tokens.allUserTokensRevoked'));
    }
}
