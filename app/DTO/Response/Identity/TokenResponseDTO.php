<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\Interfaces\DataTransferObjectInterface;
use OpenApi\Attributes as OA;

/**
 * Token Response DTO
 */
#[OA\Schema(
    schema: 'TokenResponse',
    title: 'Token Response',
    description: 'Refresh token operation result',
    required: ['accessToken', 'refreshToken', 'expiresIn']
)]
readonly class TokenResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        #[OA\Property(property: 'accessToken', description: 'New JWT access token')]
        public string $accessToken,
        #[OA\Property(property: 'refreshToken', description: 'New opaque refresh token')]
        public string $refreshToken,
        #[OA\Property(property: 'expiresIn', description: 'Token expiration in seconds', example: 3600)]
        public int $expiresIn,
        #[OA\Property(property: 'tokenType', description: 'Token type', example: 'Bearer')]
        public string $tokenType = 'Bearer'
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: (string) $data['access_token'],
            refreshToken: (string) $data['refresh_token'],
            expiresIn: (int) ($data['expires_in'] ?? 3600)
        );
    }

    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresIn' => $this->expiresIn,
            'tokenType' => $this->tokenType,
        ];
    }
}
