<?php

declare(strict_types=1);

namespace App\DTO\Request\Identity;

use App\DTO\Request\BaseRequestDTO;

readonly class RevokeAccessTokenRequestDTO extends BaseRequestDTO
{
    public string $authorization_header;

    public function rules(): array
    {
        return [
            'authorization_header' => 'required|string',
        ];
    }

    protected function map(array $data): void
    {
        $this->authorization_header = (string) ($data['authorization_header'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'authorization_header' => $this->authorization_header,
        ];
    }
}
