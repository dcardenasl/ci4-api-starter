<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DTO\Request\Identity\RefreshTokenRequestDTO;
use App\DTO\Response\Identity\TokenResponseDTO;
use App\Support\OperationResult;

/**
 * Refresh Token Service Interface
 *
 * Contract for refresh token lifecycle management
 */
interface RefreshTokenServiceInterface
{
    /**
     * Issue a new refresh token
     */
    public function issueRefreshToken(int $userId): string;

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken(RefreshTokenRequestDTO $request): TokenResponseDTO;

    /**
     * Revoke a refresh token
     */
    public function revoke(array $data): OperationResult;

    /**
     * Revoke all user's refresh tokens
     */
    public function revokeAllUserTokens(int $userId): OperationResult;
}
