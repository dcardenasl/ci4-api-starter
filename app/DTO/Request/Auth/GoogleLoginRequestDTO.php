<?php

declare(strict_types=1);

namespace App\DTO\Request\Auth;

use App\DTO\Request\BaseRequestDTO;

/**
 * Google Login Request DTO
 *
 * Validates Google ID token for authentication.
 */
readonly class GoogleLoginRequestDTO extends BaseRequestDTO
{
    public string $idToken;

    protected function rules(): array
    {
        return [
            'id_token' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->idToken = (string) $data['id_token'];
    }

    public function toArray(): array
    {
        return [
            'id_token' => $this->idToken,
        ];
    }
}
