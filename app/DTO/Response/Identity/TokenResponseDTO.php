<?php

declare(strict_types=1);

namespace App\DTO\Response\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Token Response DTO
 */
readonly class TokenResponseDTO implements DataTransferObjectInterface
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
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
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_in' => $this->expiresIn,
            'token_type' => $this->tokenType,
        ];
    }
}
