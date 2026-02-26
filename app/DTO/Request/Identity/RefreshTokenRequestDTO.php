<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use App\Interfaces\DataTransferObjectInterface;

/**
 * Refresh Token Request DTO
 */
readonly class RefreshTokenRequestDTO implements DataTransferObjectInterface
{
    public string $refreshToken;

    public function __construct(array $data)
    {
        // REUTILIZACIÓN: Validación 'auth.refresh'
        validateOrFail($data, 'auth', 'refresh');

        $this->refreshToken = (string) $data['refresh_token'];
    }

    public function toArray(): array
    {
        return [
            'refresh_token' => $this->refreshToken,
        ];
    }
}
