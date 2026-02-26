<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use App\DTO\Request\BaseRequestDTO;

/**
 * Verification Request DTO
 *
 * Validates the verification token provided in the URL or body.
 */
readonly class VerificationRequestDTO extends BaseRequestDTO
{
    public string $token;

    protected function rules(): array
    {
        return [
            'token' => 'required|string|min_length[10]',
        ];
    }

    protected function map(array $data): void
    {
        $this->token = (string) ($data['token'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
        ];
    }
}
