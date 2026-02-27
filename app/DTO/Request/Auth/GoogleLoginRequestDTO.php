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

    public function __construct(array $data)
    {
        if (isset($data['id_token']) && !isset($data['idToken'])) {
            $data['idToken'] = $data['id_token'];
        }

        parent::__construct($data);
    }

    protected function rules(): array
    {
        return [
            'idToken' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->idToken = (string) ($data['idToken'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'idToken' => $this->idToken,
        ];
    }
}
