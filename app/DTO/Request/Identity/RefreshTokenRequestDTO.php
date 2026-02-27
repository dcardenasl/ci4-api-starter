<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use App\DTO\Request\BaseRequestDTO;

/**
 * Refresh Token Request DTO
 *
 * Validates the refresh token string.
 */
readonly class RefreshTokenRequestDTO extends BaseRequestDTO
{
    public string $refreshToken;

    protected function rules(): array
    {
        return [
            'refreshToken' => 'required|string|min_length[10]',
        ];
    }

    protected function map(array $data): void
    {
        $this->refreshToken = (string) ($data['refreshToken'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'refreshToken' => $this->refreshToken,
        ];
    }
}
