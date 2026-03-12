<?php

declare(strict_types=1);

namespace App\DTO\Request\Catalog;

use App\DTO\Request\BaseRequestDTO;

readonly class DemoproductCreateRequestDTO extends BaseRequestDTO
{
    public string $name;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max_length[255]',
        ];
    }

    protected function map(array $data): void
    {
        $this->name = (string) ($data['name'] ?? '');
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
        ];
    }
}
